<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        return $this->hydrateConditionTargetState($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $record = $this->getRecord();
        $data = OwnerScopedQueries::enforceOwnerOnUpdate($record, $data);

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateConditionTargetState(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $definition = $data['target_definition']
            ?? $metadata['target_definition']
            ?? null;

        if (! is_array($definition)) {
            $definition = ConditionTargetPreset::default()->target()?->toArray();
            if ($definition === null) {
                $definition = ConditionTarget::from(ConditionTargetPreset::default()->dsl())->toArray();
            }
        }

        $dsl = ConditionTarget::from($definition)->toDsl();
        $preset = ConditionTargetPreset::detect($dsl) ?? ConditionTargetPreset::default();

        $data['condition_target_dsl'] = $dsl;
        $data['condition_target_preset'] = $preset->value;
        $data['metadata'] = $metadata;
        $data['target_definition'] = $definition;

        return $data;
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
