<?php

namespace App\Jobs\Fasilitas;

use App\Services\Fasilitas\FacilityImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessFacilityImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public int $uniqueFor = 14400;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public string $importJobId) {}

    public function uniqueId(): string
    {
        return $this->importJobId;
    }

    public function handle(FacilityImportService $service): void
    {
        $service->process($this->importJobId);
    }

    public function failed(?Throwable $exception): void
    {
        app(FacilityImportService::class)->markFailed($this->importJobId, $exception);
    }
}
