<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        $data = OwnerScopedQueries::enforceOwnerOnCreate($data);

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistConditionTargetDefinition(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $dsl = mb_trim((string) ($data['condition_target_dsl'] ?? ''));

        if ($dsl === '') {
            throw ValidationException::withMessages([
                'condition_target_dsl' => 'Condition target DSL cannot be empty.',
            ]);
        }

        try {
            $target = ConditionTarget::from($dsl);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'condition_target_dsl' => $exception->getMessage(),
            ]);
        }

        $data['target_definition'] = $target->toArray();
        unset($metadata['target_definition'], $metadata['condition_target_definition'], $metadata['condition_target_dsl']);
        $data['metadata'] = $metadata ?: null;

        unset($data['condition_target_dsl'], $data['condition_target_preset']);

        return $data;
    }
}
