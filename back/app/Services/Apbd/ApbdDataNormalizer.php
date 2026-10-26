<?php

namespace App\Services\Apbd;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ApbdDataNormalizer
{
    /**
     * @param  array<string, string>  $row
     * @return array{periode_data: string, tanggal: string, kode_opd: string, nama_opd: string, akun_jenis: string, nama_akun: string, anggaran: string, realisasi: string}
     */
    public function normalizeRow(array $row): array
    {
        $normalized = [];
        $errors = [];

        foreach (['periode_data', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value === '') {
                $errors[$field] = "The {$field} column is required.";

                continue;
            }

            if (mb_strlen($value) > ($field === 'kode_opd' || $field === 'akun_jenis' ? 100 : 255)) {
                $errors[$field] = "The {$field} value exceeds the supported length.";

                continue;
            }

            $normalized[$field] = $value;
        }

        if (isset($normalized['periode_data'])) {
            $period = preg_replace('/\.0+$/', '', $normalized['periode_data']);

            if (! preg_match('/^\d{6}$/', $period) || (int) substr($period, 4, 2) < 1 || (int) substr($period, 4, 2) > 12) {
                $errors['periode_data'] = 'periode_data must use YYYYMM with a valid month.';
            } else {
                $normalized['periode_data'] = $period;
            }
        }

        try {
            $normalized['tanggal'] = $this->normalizeDate(trim((string) ($row['tanggal'] ?? '')));
        } catch (InvalidArgumentException $exception) {
            $errors['tanggal'] = $exception->getMessage();
        }

        foreach (['anggaran', 'realisasi'] as $field) {
            try {
                $normalized[$field] = $this->normalizeAmount(trim((string) ($row[$field] ?? '')));
            } catch (InvalidArgumentException $exception) {
                $errors[$field] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var array{periode_data: string, tanggal: string, kode_opd: string, nama_opd: string, akun_jenis: string, nama_akun: string, anggaran: string, realisasi: string} $normalized */
        return $normalized;
    }

    public function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));

        if ($value === '') {
            throw new InvalidArgumentException('A numeric amount is required.');
        }

        if (str_contains(strtolower($value), 'e')) {
            $value = $this->expandScientificNotation($value);
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                if (! preg_match('/^\d{1,3}(?:\.\d{3})+,\d+$/', $value)) {
                    throw new InvalidArgumentException('The amount has an invalid Indonesian thousands/decimal format.');
                }

                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                if (! preg_match('/^\d{1,3}(?:,\d{3})+\.\d+$/', $value)) {
                    throw new InvalidArgumentException('The amount has an invalid thousands/decimal format.');
                }

                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            if (substr_count($value, ',') !== 1 || ! preg_match('/^\d+,\d+$/', $value)) {
                throw new InvalidArgumentException('The amount has an invalid decimal separator.');
            }

            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, '.')) {
            if (substr_count($value, '.') > 1) {
                if (! preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
                    throw new InvalidArgumentException('The amount has an invalid thousands separator.');
                }

                $value = str_replace('.', '', $value);
            } elseif (preg_match('/^\d{1,3}\.\d{3}$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('The amount must be a non-negative number.');
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        if (strlen($integer) > 36 || strlen($fraction) > 2) {
            throw new InvalidArgumentException('The amount supports up to 36 integer digits and 2 decimal places.');
        }

        return $integer.'.'.str_pad($fraction, 2, '0');
    }

    /** @param array<string, string> $attributes */
    public function fingerprint(array $attributes): string
    {
        $fields = [
            'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
        ];

        $values = [];

        foreach ($fields as $field) {
            $values[$field] = (string) ($attributes[$field] ?? '');
        }

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeDate(string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('A date is required.');
        }

        if (preg_match('/^\d{8}$/', $value)) {
            $date = Carbon::createFromFormat('!Ymd', $value);

            if ($date instanceof Carbon && $date->format('Ymd') === $value) {
                return $date->toDateString();
            }
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) && (float) $value >= 1 && (float) $value <= 2958465) {
            $days = (int) floor((float) $value);

            return Carbon::create(1899, 12, 30)->addDays($days)->toDateString();
        }

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                $date = false;
            }

            if ($date instanceof Carbon && $date->format(ltrim($format, '!')) === $value) {
                return $date->toDateString();
            }
        }

        throw new InvalidArgumentException('tanggal must be a valid date (YYYY-MM-DD, DD/MM/YYYY, or an Excel date).');
    }

    private function expandScientificNotation(string $value): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $value, $matches)) {
            throw new InvalidArgumentException('The amount has an invalid scientific notation.');
        }

        $exponent = (int) $matches[3];

        if (abs($exponent) > 40) {
            throw new InvalidArgumentException('The amount exponent is outside the supported range.');
        }

        $integer = $matches[1];
        $fraction = $matches[2];
        $digits = $integer.$fraction;
        $decimalPosition = strlen($integer) + $exponent;

        if ($decimalPosition <= 0) {
            return '0.'.str_repeat('0', abs($decimalPosition)).$digits;
        }

        if ($decimalPosition >= strlen($digits)) {
            return $digits.str_repeat('0', $decimalPosition - strlen($digits));
        }

        return substr($digits, 0, $decimalPosition).'.'.substr($digits, $decimalPosition);
    }
}
