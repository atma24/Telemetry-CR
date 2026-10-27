<?php

namespace App\Jobs\Wilayah;

use App\Services\Wilayah\RegionImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessRegionImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public int $uniqueFor = 14400;

    public function __construct(public string $importJobId) {}

    public function uniqueId(): string
    {
        return $this->importJobId;
    }

    public function handle(RegionImportService $service): void
    {
        $service->process($this->importJobId);
    }

    public function failed(?Throwable $exception): void
    {
        app(RegionImportService::class)->markFailed($this->importJobId, $exception);
    }
}
