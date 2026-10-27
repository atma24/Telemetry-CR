<?php

namespace App\Jobs\Apbd;

use App\Services\Apbd\ApbdImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessApbdImport implements ShouldBeUnique, ShouldQueue
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

    public function handle(ApbdImportService $service): void
    {
        $service->process($this->importJobId);
    }

    public function failed(?Throwable $exception): void
    {
        app(ApbdImportService::class)->markFailed($this->importJobId, $exception);
    }
}
