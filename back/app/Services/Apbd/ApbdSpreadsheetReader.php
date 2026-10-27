<?php

namespace App\Services\Apbd;

use Generator;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

class ApbdSpreadsheetReader
{
    private const REQUIRED_COLUMNS = [
        'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
    ];

    /**
     * @param  list<string>|null  $requiredColumns
     * @param  list<string>|null  $allowedColumns
     * @return Generator<int, array{row_number: int, values: array<string, string>, source_original_data: array<string, string>}>
     */
    public function rows(UploadedFile $file, ?array $requiredColumns = null, ?array $allowedColumns = null): Generator
    {
        $requiredColumns ??= self::REQUIRED_COLUMNS;

        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            yield from $this->csvRows($file, $requiredColumns, $allowedColumns);

            return;
        }

        $prefix = file_get_contents($file->getRealPath(), false, null, 0, 512);

        if (is_string($prefix) && preg_match('/<(?:!doctype\s+html|html|table)\b/i', $prefix) === 1) {
            yield from $this->htmlRows($file, $requiredColumns, $allowedColumns);

            return;
        }

        yield from $this->xlsxRows($file, $requiredColumns, $allowedColumns);
    }

    /**
     * @param  list<string>  $requiredColumns
     * @param  list<string>|null  $allowedColumns
     * @return Generator<int, array{row_number: int, values: array<string, string>, source_original_data: array<string, string>}>
     */
    private function csvRows(UploadedFile $file, array $requiredColumns, ?array $allowedColumns): Generator
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('The uploaded CSV file could not be opened.');
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '');

            if ($headers === false) {
                throw new InvalidArgumentException('The CSV file is empty.');
            }

            $mapping = $this->headerMapping($headers, $requiredColumns, $allowedColumns);
            $rowNumber = 1;

            while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                yield $this->mappedRow($cells, $headers, $mapping, $rowNumber);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $requiredColumns
     * @param  list<string>|null  $allowedColumns
     * @return Generator<int, array{row_number: int, values: array<string, string>, source_original_data: array<string, string>}>
     */
    private function xlsxRows(UploadedFile $file, array $requiredColumns, ?array $allowedColumns): Generator
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLReader::class)) {
            throw new InvalidArgumentException('The server must enable the PHP zip and xmlreader extensions to import XLSX files.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new InvalidArgumentException('The uploaded file is not a readable XLSX workbook.');
        }

        $reader = null;
        $temporaryStream = null;

        try {
            $this->assertWorkbookSize($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $zeroFillStyles = $this->zeroFillStyles($zip);
            $openedReader = $this->openZipXmlReader($zip, $sheetPath);
            $reader = $openedReader['reader'];
            $temporaryStream = $openedReader['stream'];
            $mapping = null;
            $headers = [];
            $rowNumber = 0;
            $currentRowNumber = 0;
            $cells = [];
            $currentCell = null;
            $captureElement = null;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                    $currentRowNumber = (int) ($reader->getAttribute('r') ?: ++$rowNumber);
                    $rowNumber = max($rowNumber, $currentRowNumber);
                    $cells = [];
                } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c') {
                    $currentCell = [
                        'column' => $this->columnNumber((string) $reader->getAttribute('r')),
                        'type' => $reader->getAttribute('t') ?? '',
                        'style' => (int) ($reader->getAttribute('s') ?? 0),
                        'value' => '',
                    ];
                } elseif ($currentCell !== null && $reader->nodeType === XMLReader::ELEMENT && in_array($reader->localName, ['v', 't'], true)) {
                    $captureElement = $reader->localName;
                } elseif ($currentCell !== null && $captureElement !== null && in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA], true)) {
                    $currentCell['value'] .= $reader->value;
                } elseif ($currentCell !== null && $reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c') {
                    $cellValue = $currentCell['value'];

                    if ($currentCell['type'] === 's') {
                        $cellValue = $sharedStrings[(int) $cellValue] ?? '';
                    } elseif ($currentCell['type'] === 'inlineStr') {
                        $cellValue = $currentCell['value'];
                    } elseif (isset($zeroFillStyles[$currentCell['style']]) && preg_match('/^\d+$/', $cellValue)) {
                        $cellValue = str_pad($cellValue, $zeroFillStyles[$currentCell['style']], '0', STR_PAD_LEFT);
                    }

                    $cells[$currentCell['column']] = $cellValue;
                    $currentCell = null;
                    $captureElement = null;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row') {
                    if ($this->isEmptyRow($cells)) {
                        continue;
                    }

                    if ($mapping === null) {
                        $headers = $cells;
                        $mapping = $this->headerMapping($headers, $requiredColumns, $allowedColumns);

                        continue;
                    }

                    yield $this->mappedRow($cells, $headers, $mapping, $currentRowNumber);
                }
            }

            if ($mapping === null) {
                throw new InvalidArgumentException('The XLSX worksheet is empty.');
            }
        } finally {
            $reader?->close();

            if (is_resource($temporaryStream)) {
                fclose($temporaryStream);
            }

            $zip->close();
        }
    }

    /** @param array<int, mixed> $headers
     * @return array<int, string>
     */
    /**
     * @param  array<int, mixed>  $headers
     * @param  list<string>  $requiredColumns
     * @param  list<string>|null  $allowedColumns
     * @return array<int, string>
     */
    private function headerMapping(array $headers, array $requiredColumns, ?array $allowedColumns): array
    {
        if (isset($headers[0]) && is_string($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        $mapping = [];
        $found = [];

        foreach ($headers as $index => $header) {
            $name = $this->normalizeHeader((string) $header);
            $name = [
                'school_name' => 'nama_sekolah',
                'nama_school' => 'nama_sekolah',
                'market_name' => 'nama_pasar',
                'postal_code' => 'kode_pos',
                'phone' => 'telepon',
                'telephone' => 'telepon',
                'coord_x' => 'koordinat_x',
                'coord_y' => 'koordinat_y',
                'student_count' => 'jumlah_siswa',
                'teacher_count' => 'jumlah_guru',
                'village' => 'kelurahan',
                'district' => 'kecamatan',
            ][$name] ?? $name;

            if ($name !== '') {
                if (isset($found[$name])) {
                    throw new InvalidArgumentException("The header '{$name}' appears more than once.");
                }

                $found[$name] = true;
                $mapping[$index] = $name;
            }
        }

        $missing = array_values(array_diff($requiredColumns, array_keys($found)));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing required columns: '.implode(', ', $missing).'.');
        }

        $unknown = $allowedColumns === null ? [] : array_values(array_diff(array_keys($found), $allowedColumns));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown columns: '.implode(', ', $unknown).'.');
        }

        return $mapping;
    }

    /**
     * @param  list<string>  $requiredColumns
     * @param  list<string>|null  $allowedColumns
     * @return Generator<int, array{row_number: int, values: array<string, string>, source_original_data: array<string, string>}>
     */
    private function htmlRows(UploadedFile $file, array $requiredColumns, ?array $allowedColumns): Generator
    {
        if (! class_exists(\DOMDocument::class)) {
            throw new InvalidArgumentException('The server must enable the DOM extension to read HTML-table XLS files.');
        }

        $previousState = libxml_use_internal_errors(true);
        $document = new \DOMDocument;

        try {
            $content = file_get_contents($file->getRealPath());

            if (! is_string($content) || ! $document->loadHTML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new InvalidArgumentException('The uploaded HTML-table XLS file is invalid.');
            }

            $rows = $document->getElementsByTagName('tr');
            $headers = [];
            $mapping = null;
            $rowNumber = 0;

            foreach ($rows as $row) {
                $rowNumber++;
                $cells = [];

                foreach ($row->childNodes as $cell) {
                    if (in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                        $cells[] = trim(preg_replace('/\s+/u', ' ', html_entity_decode($cell->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
                    }
                }

                if ($cells === []) {
                    continue;
                }

                if ($mapping === null) {
                    $headers = $cells;
                    $mapping = $this->headerMapping($headers, $requiredColumns, $allowedColumns);

                    continue;
                }

                if (! $this->isEmptyRow($cells)) {
                    yield $this->mappedRow($cells, $headers, $mapping, $rowNumber);
                }
            }

            if ($mapping === null) {
                throw new InvalidArgumentException('The HTML-table XLS file contains no header row.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));

        return trim(preg_replace('/[^a-z0-9]+/u', '_', $header) ?? '', '_');
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<int, mixed>  $headers
     * @param  array<int, string>  $mapping
     * @return array{row_number: int, values: array<string, string>, source_original_data: array<string, string>}
     */
    private function mappedRow(array $cells, array $headers, array $mapping, int $rowNumber): array
    {
        $values = [];
        $source = [];

        foreach ($headers as $index => $header) {
            $value = trim((string) ($cells[$index] ?? ''));
            $originalHeader = trim((string) $header);
            $sourceKey = $mapping[$index] ?? ($originalHeader !== '' ? $originalHeader : 'column_'.($index + 1));

            if (isset($source[$sourceKey])) {
                $sourceKey .= '_'.($index + 1);
            }

            $source[$sourceKey] = $value;

            if (isset($mapping[$index])) {
                $values[$mapping[$index]] = $value;
            }
        }

        return ['row_number' => $rowNumber, 'values' => $values, 'source_original_data' => $source];
    }

    /** @param array<int, mixed> $cells */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relationships === false) {
            throw new InvalidArgumentException('The XLSX workbook is missing workbook metadata.');
        }

        $workbookXml = new SimpleXMLElement($workbook);
        $sheetNodes = $workbookXml->xpath('//*[local-name()="sheet"]');

        if (! isset($sheetNodes[0])) {
            throw new InvalidArgumentException('The XLSX workbook does not contain a worksheet.');
        }

        $relationshipId = (string) $sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $relationshipsXml = new SimpleXMLElement($relationships);
        $relationshipNodes = $relationshipsXml->xpath('//*[local-name()="Relationship"]');

        foreach ($relationshipNodes ?: [] as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId || (string) $relationship['TargetMode'] === 'External') {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');

            if (str_contains($target, '..') || str_contains($target, '://')) {
                break;
            }

            $path = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;

            if ($zip->locateName($path) !== false) {
                return $path;
            }
        }

        throw new InvalidArgumentException('The first XLSX worksheet could not be located.');
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $openedReader = $this->openZipXmlReader($zip, 'xl/sharedStrings.xml');
        $reader = $openedReader['reader'];
        $temporaryStream = $openedReader['stream'];
        $strings = [];
        $current = null;
        $capturingText = false;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                    $current = '';
                } elseif ($current !== null && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                    $capturingText = true;
                } elseif ($current !== null && $capturingText && in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA], true)) {
                    $current .= $reader->value;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 't') {
                    $capturingText = false;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' && $current !== null) {
                    $strings[] = $current;
                    $current = null;
                }
            }
        } finally {
            $reader->close();

            if (is_resource($temporaryStream)) {
                fclose($temporaryStream);
            }
        }

        return $strings;
    }

    /** @return array<int, int> */
    private function zeroFillStyles(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $styles = new SimpleXMLElement($xml);
        $formats = [];

        foreach ($styles->xpath('//*[local-name()="numFmt"]') ?: [] as $format) {
            $formats[(int) $format['numFmtId']] = (string) $format['formatCode'];
        }

        $result = [];
        $cellFormats = $styles->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]') ?: [];

        foreach ($cellFormats as $styleIndex => $format) {
            $formatCode = $formats[(int) $format['numFmtId']] ?? '';

            if (preg_match('/^0{2,}$/', $formatCode)) {
                $result[$styleIndex] = strlen($formatCode);
            }
        }

        return $result;
    }

    /** @return array{reader: XMLReader, stream: resource} */
    private function openZipXmlReader(ZipArchive $zip, string $entry): array
    {
        $zipStream = $zip->getStream($entry);

        if ($zipStream === false) {
            throw new InvalidArgumentException("The XLSX file is missing or cannot read {$entry}.");
        }

        $temporaryStream = tmpfile();

        if ($temporaryStream === false) {
            fclose($zipStream);

            throw new InvalidArgumentException("The XLSX file is missing or cannot read {$entry}.");
        }

        if (stream_copy_to_stream($zipStream, $temporaryStream) === false) {
            fclose($zipStream);
            fclose($temporaryStream);

            throw new InvalidArgumentException("The XLSX file cannot read {$entry}.");
        }

        fclose($zipStream);
        rewind($temporaryStream);
        $metadata = stream_get_meta_data($temporaryStream);
        $reader = new XMLReader;
        $uri = $metadata['uri'] ?? null;

        if (! is_string($uri) || ! $reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            fclose($temporaryStream);

            throw new InvalidArgumentException("The XLSX file contains invalid XML in {$entry}.");
        }

        return ['reader' => $reader, 'stream' => $temporaryStream];
    }

    private function assertWorkbookSize(ZipArchive $zip): void
    {
        $uncompressedBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $uncompressedBytes += (int) ($stat['size'] ?? 0);

            if ($uncompressedBytes > 100 * 1024 * 1024) {
                throw new InvalidArgumentException('The XLSX workbook expands beyond the 100 MB processing limit.');
            }
        }
    }

    private function columnNumber(string $cellReference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            throw new InvalidArgumentException('The XLSX worksheet contains an invalid cell reference.');
        }

        $column = 0;

        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $column = $column * 26 + ord($letter) - 64;
        }

        return $column - 1;
    }
}
