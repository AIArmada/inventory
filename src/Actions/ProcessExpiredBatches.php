<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Services\Batch\BatchService;
use Lorisleiva\Actions\Concerns\AsAction;

final class ProcessExpiredBatches
{
    use AsAction;

    public function __construct(
        private readonly BatchService $batchService,
    ) {}

    public function handle(): int
    {
        return $this->batchService->processExpiredBatches();
    }
}
