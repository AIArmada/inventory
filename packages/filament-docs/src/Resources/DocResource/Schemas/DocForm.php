<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\DocTemplate;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        $docTypes = config('docs.types', []);

        if (! is_array($docTypes)) {
            $docTypes = [];
        }

        $docTypeOptions = collect($docTypes)
            ->keys()
            ->mapWithKeys(static fn (string $type): array => [$type => Str::headline($type)])
            ->all();

        $defaultDocType = array_key_first($docTypeOptions) ?? 'invoice';

        return $schema
            ->schema([
                Section::make('Document Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('doc_number')
                                    ->label('Document Number')
                                    ->helperText('Leave empty to auto-generate')
                                    ->unique(ignoreRecord: true),

                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options($docTypeOptions)
                                    ->default($defaultDocType)
                                    ->required()
                                    ->live(),

                                Select::make('doc_template_id')
                                    ->label('Template')
                                    ->options(function (Get $get): array {
                                        $query = DocTemplate::query();

                                        $docType = $get('doc_type');
                                        if (is_string($docType) && $docType !== '') {
                                            $query->where('doc_type', $docType);
                                        }

                                        if (config('docs.owner.enabled', false)) {
                                            $includeGlobal = (bool) config('docs.owner.include_global', false);
                                            $owner = OwnerContext::resolve();

                                            $query->forOwner($owner, $includeGlobal);
                                        }

                                        /** @var array<string, string> $options */
                                        $options = $query->orderBy('name')->pluck('name', 'id')->all();

                                        return $options;
                                    })
                                    ->searchable()
                                    ->helperText('Optional: Select a template'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(collect(DocStatus::cases())->mapWithKeys(fn (DocStatus $status) => [$status->value => $status->label()]))
                                    ->default(DocStatus::DRAFT->value)
                                    ->required(),

                                DatePicker::make('issue_date')
                                    ->label('Issue Date')
                                    ->default(CarbonImmutable::now())
                                    ->required(),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->helperText('Auto-calculated if empty'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('currency')
                                    ->label('Currency')
                                    ->default((string) config('docs.defaults.currency', 'MYR'))
                                    ->maxLength(3)
                                    ->required()
                                    ->live(),

                                TextInput::make('tax_rate')
                                    ->label('Tax Rate')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->helperText('e.g., 6 for 6%'),
                            ]),
                    ]),

                Section::make('Customer Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_data.name')
                                    ->label('Customer Name')
                                    ->required(),

                                TextInput::make('customer_data.email')
                                    ->label('Email')
                                    ->email(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_data.phone')
                                    ->label('Phone'),

                                TextInput::make('customer_data.address')
                                    ->label('Address'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('customer_data.city')
                                    ->label('City'),

                                TextInput::make('customer_data.state')
                                    ->label('State'),

                                TextInput::make('customer_data.postcode')
                                    ->label('Postcode'),

                                TextInput::make('customer_data.country')
                                    ->label('Country'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Item Name')
                                            ->required()
                                            ->columnSpan(2),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required(),

                                        TextInput::make('price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix(fn (Get $get): string => $get('../../currency') ?? config('docs.defaults.currency', 'MYR'))
                                            ->required(),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->itemLabel(fn (array $state, Get $get): string => ($state['name'] ?? 'New Item') .
                                (isset($state['quantity'], $state['price']) ? ' - ' . $state['quantity'] . ' × ' . ($get('currency') ?? config('docs.defaults.currency', 'MYR')) . ' ' . number_format((float) $state['price'], 2) : ''))
                            ->columnSpanFull()
                            ->defaultItems(1),
                    ]),

                Section::make('Amounts')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),

                                TextInput::make('tax_amount')
                                    ->label('Tax Amount')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),

                                TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->default(0),

                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Notes & Terms')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
