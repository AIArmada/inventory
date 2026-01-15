---
title: Customization
---

# Customization

This guide covers how to customize every aspect of the Filament Docs package.

## Plugin Configuration

The `FilamentDocsPlugin` provides fluent configuration methods:

```php
<?php

namespace App\Providers;

use AIArmada\FilamentDocs\FilamentDocsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentDocsPlugin::make()
                    // Navigation
                    ->navigationGroup('Billing')
                    ->navigationSort(5)
                    ->navigationIcon('heroicon-o-document-currency-dollar')
                    
                    // Resources
                    ->docResource(\App\Filament\Resources\DocResource::class)
                    ->docTemplateResource(\App\Filament\Resources\DocTemplateResource::class)
                    ->docSequenceResource(\App\Filament\Resources\DocSequenceResource::class)
                    
                    // Pages
                    ->agingReportEnabled(true)
                    ->pendingApprovalsEnabled(true)
                    
                    // Widgets
                    ->docStatsWidgetEnabled(true)
                    ->quickActionsWidgetEnabled(true)
                    ->recentDocumentsWidgetEnabled(true)
                    ->revenueChartWidgetEnabled(true)
                    ->statusBreakdownWidgetEnabled(true),
            ]);
    }
}
```

---

## Custom Resource Classes

### Complete DocResource Override

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Resources\DocResource as BaseDocResource;
use Filament\Infolists\Infolist;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocResource extends BaseDocResource
{
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Invoices & Quotations';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?string $slug = 'invoices';

    public static function getModelLabel(): string
    {
        return 'Invoice';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Invoices';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::query()
            ->forOwner(static::getOwner())
            ->where('status', 'pending')
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        // Return parent form or build custom
        return parent::form($schema);
    }

    public static function table(Table $table): Table
    {
        // Return parent table or build custom
        return parent::table($table);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // Return parent infolist or build custom
        return parent::infolist($infolist);
    }
}
```

### Register Custom Resource

```php
FilamentDocsPlugin::make()
    ->docResource(\App\Filament\Resources\DocResource::class);
```

---

## Custom Form Components

### Adding Custom Fields

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Project Information')
                ->schema([
                    Select::make('metadata.project_id')
                        ->label('Project')
                        ->options(fn () => Project::pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                        
                    Select::make('metadata.department')
                        ->label('Department')
                        ->options([
                            'sales' => 'Sales',
                            'marketing' => 'Marketing',
                            'engineering' => 'Engineering',
                        ]),
                        
                    DatePicker::make('metadata.delivery_date')
                        ->label('Expected Delivery'),
                ])
                ->columns(3),
                
            Section::make('Internal Notes')
                ->schema([
                    RichEditor::make('metadata.internal_notes')
                        ->label('Notes')
                        ->toolbarButtons(['bold', 'italic', 'bulletList']),
                ])
                ->collapsible()
                ->collapsed(),
                
            Section::make('Custom Fields')
                ->schema([
                    KeyValue::make('metadata.custom_fields')
                        ->label('Additional Data')
                        ->keyLabel('Field Name')
                        ->valueLabel('Value')
                        ->addButtonLabel('Add Field'),
                ]),
        ]);
    }
}
```

### Custom Line Item Schema

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\Components;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

class LineItemsRepeater
{
    public static function make(): Repeater
    {
        return Repeater::make('items')
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->options(fn () => Product::pluck('name', 'id'))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if ($state) {
                            $product = Product::find($state);
                            $set('name', $product->name);
                            $set('price', $product->price);
                            $set('description', $product->description);
                        }
                    }),
                    
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                    
                Textarea::make('description')
                    ->rows(2),
                    
                TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotal($get, $set)),
                    
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotal($get, $set)),
                    
                Hidden::make('total'),
            ])
            ->columns(5)
            ->defaultItems(1)
            ->addActionLabel('Add Line Item')
            ->reorderable()
            ->collapsible()
            ->cloneable()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New Item');
    }

    protected static function updateTotal(Get $get, Set $set): void
    {
        $quantity = floatval($get('quantity') ?? 0);
        $price = floatval($get('price') ?? 0);
        $set('total', $quantity * $price);
    }
}
```

---

## Custom Table Columns

### Adding Custom Columns

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Document #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'invoice',
                        'success' => 'receipt',
                        'info' => 'quotation',
                    ]),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'sent',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'secondary' => 'cancelled',
                    ]),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('total')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('metadata.project_id')
                    ->label('Project')
                    ->formatStateUsing(fn ($state) => Project::find($state)?->name ?? '-'),

                IconColumn::make('has_pdf')
                    ->label('PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document'),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
```

### Custom Filters

```php
<?php

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

$table->filters([
    SelectFilter::make('type')
        ->options([
            'invoice' => 'Invoice',
            'quotation' => 'Quotation',
            'receipt' => 'Receipt',
        ]),

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'pending' => 'Pending',
            'sent' => 'Sent',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ])
        ->multiple(),

    TernaryFilter::make('has_pdf')
        ->label('Has PDF')
        ->queries(
            true: fn (Builder $query) => $query->whereNotNull('pdf_path'),
            false: fn (Builder $query) => $query->whereNull('pdf_path'),
        ),

    Filter::make('overdue')
        ->query(fn (Builder $query) => $query
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->where('due_date', '<', now()))
        ->toggle(),

    Filter::make('this_month')
        ->query(fn (Builder $query) => $query
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year))
        ->toggle(),

    Filter::make('high_value')
        ->label('High Value (> $10,000)')
        ->query(fn (Builder $query) => $query->where('total', '>', 10000))
        ->toggle(),
]);
```

---

## Custom Actions

### Header Actions

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\Pages;

use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc as BaseViewDoc;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ViewDoc extends BaseViewDoc
{
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),

            Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (): void {
                    $newDoc = $this->record->replicate();
                    $newDoc->number = null; // Auto-generate new number
                    $newDoc->status = 'draft';
                    $newDoc->save();

                    Notification::make()
                        ->success()
                        ->title('Document duplicated')
                        ->send();

                    $this->redirect(static::$resource::getUrl('edit', ['record' => $newDoc]));
                }),

            Action::make('convert_to_invoice')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->type === 'quotation')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'type' => 'invoice',
                        'status' => 'pending',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Converted to invoice')
                        ->send();
                }),

            Action::make('export_ubl')
                ->icon('heroicon-o-code-bracket')
                ->action(function (): void {
                    // UBL XML export logic
                }),
        ];
    }
}
```

### Table Row Actions

```php
<?php

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;

$table->actions([
    ActionGroup::make([
        Action::make('view')
            ->icon('heroicon-o-eye')
            ->url(fn ($record) => DocResource::getUrl('view', ['record' => $record])),

        Action::make('edit')
            ->icon('heroicon-o-pencil')
            ->url(fn ($record) => DocResource::getUrl('edit', ['record' => $record])),

        Action::make('download_pdf')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn ($record) => $record->pdf_path !== null)
            ->action(fn ($record) => response()->download(storage_path('app/' . $record->pdf_path))),

        Action::make('send_email')
            ->icon('heroicon-o-paper-airplane')
            ->form([
                TextInput::make('email')
                    ->email()
                    ->default(fn ($record) => $record->customer_email)
                    ->required(),
                Textarea::make('message')
                    ->default('Please find attached your document.'),
            ])
            ->action(function (array $data, $record): void {
                // Send email logic
            }),

        Action::make('mark_paid')
            ->icon('heroicon-o-check-circle')
            ->visible(fn ($record) => in_array($record->status->value, ['pending', 'sent', 'overdue']))
            ->requiresConfirmation()
            ->action(fn ($record) => $record->update(['status' => 'paid'])),
    ]),
]);
```

### Bulk Actions

```php
<?php

use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

$table->bulkActions([
    BulkAction::make('generate_pdfs')
        ->icon('heroicon-o-document-arrow-down')
        ->action(function (Collection $records): void {
            $records->each(function ($record): void {
                GenerateDocPdfJob::dispatch($record);
            });
        }),

    BulkAction::make('send_reminders')
        ->icon('heroicon-o-bell')
        ->requiresConfirmation()
        ->action(function (Collection $records): void {
            $records
                ->filter(fn ($r) => in_array($r->status->value, ['sent', 'overdue']))
                ->each(function ($record): void {
                    SendDocReminderJob::dispatch($record);
                });
        }),

    BulkAction::make('export_csv')
        ->icon('heroicon-o-arrow-down-on-square')
        ->action(function (Collection $records): void {
            // Export logic
        }),
]);
```

---

## Custom Infolist Components

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class DocInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Document Information')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('number')
                            ->label('Document #')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('type')
                            ->badge(),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state->value) {
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'sent' => 'info',
                                'paid' => 'success',
                                'overdue' => 'danger',
                                default => 'secondary',
                            }),
                    ]),
                ]),

            Section::make('Customer')
                ->schema([
                    TextEntry::make('customer_name')->label('Name'),
                    TextEntry::make('customer_email')->label('Email')->copyable(),
                    TextEntry::make('customer_phone')->label('Phone'),
                    TextEntry::make('full_address')
                        ->label('Address')
                        ->state(fn ($record) => implode(', ', array_filter([
                            $record->customer_address,
                            $record->customer_city,
                            $record->customer_state,
                            $record->customer_postcode,
                            $record->customer_country,
                        ]))),
                ])
                ->columns(2),

            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('quantity')->numeric(),
                            TextEntry::make('price')->money('USD'),
                            TextEntry::make('total')
                                ->state(fn ($state) => ($state['quantity'] ?? 0) * ($state['price'] ?? 0))
                                ->money('USD'),
                        ])
                        ->columns(4),
                ]),

            Section::make('Totals')
                ->schema([
                    Group::make([
                        TextEntry::make('subtotal')->money('USD'),
                        TextEntry::make('tax_amount')->money('USD'),
                        TextEntry::make('discount')->money('USD'),
                        TextEntry::make('total')
                            ->money('USD')
                            ->weight('bold')
                            ->size('lg'),
                    ])->columns(4),
                ]),

            Section::make('PDF Status')
                ->schema([
                    IconEntry::make('has_pdf')
                        ->label('PDF Generated')
                        ->boolean()
                        ->state(fn ($record) => $record->pdf_path !== null),
                    TextEntry::make('pdf_generated_at')
                        ->label('Generated At')
                        ->dateTime(),
                ])
                ->columns(2),
        ]);
    }
}
```

---

## Custom Relation Managers

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocResource\RelationManagers;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';
    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('type')
                ->options([
                    'internal' => 'Internal Note',
                    'customer' => 'Customer Visible',
                ])
                ->required(),

            RichEditor::make('content')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('content')->limit(50)->html(),
                TextColumn::make('user.name')->label('Author'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
```

---

## Custom PDF Templates

### Blade Template

Create a custom Blade view for PDF generation:

```blade
{{-- resources/views/docs/pdf/invoice.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $doc->number }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company { font-size: 24px; font-weight: bold; }
        .document-info { text-align: right; }
        .customer { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .totals { text-align: right; }
        .total-row { font-weight: bold; font-size: 14px; }
        .footer { margin-top: 40px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">
            {{ $company['name'] ?? 'Your Company' }}
        </div>
        <div class="document-info">
            <h2>INVOICE</h2>
            <p><strong>Number:</strong> {{ $doc->number }}</p>
            <p><strong>Date:</strong> {{ $doc->issue_date->format('M d, Y') }}</p>
            <p><strong>Due:</strong> {{ $doc->due_date->format('M d, Y') }}</p>
        </div>
    </div>

    <div class="customer">
        <strong>Bill To:</strong><br>
        {{ $doc->customer_name }}<br>
        {{ $doc->customer_address }}<br>
        {{ $doc->customer_city }}, {{ $doc->customer_state }} {{ $doc->customer_postcode }}<br>
        {{ $doc->customer_country }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($doc->items as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td>${{ number_format($item['price'], 2) }}</td>
                <td>${{ number_format($item['quantity'] * $item['price'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p>Subtotal: ${{ number_format($doc->subtotal, 2) }}</p>
        <p>Tax ({{ $doc->tax_rate }}%): ${{ number_format($doc->tax_amount, 2) }}</p>
        @if($doc->discount > 0)
        <p>Discount: -${{ number_format($doc->discount, 2) }}</p>
        @endif
        <p class="total-row">Total: ${{ number_format($doc->total, 2) }}</p>
    </div>

    @if($doc->notes)
    <div class="notes">
        <strong>Notes:</strong><br>
        {{ $doc->notes }}
    </div>
    @endif

    <div class="footer">
        <p>{{ $doc->terms }}</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
```

### Configure Template

```php
// In your DocTemplate or config
'view' => 'docs.pdf.invoice',
```

---

## Localization

### Language Files

Create language files for translations:

```php
// lang/en/filament-docs.php
return [
    'resources' => [
        'doc' => [
            'label' => 'Document',
            'plural' => 'Documents',
        ],
        'template' => [
            'label' => 'Template',
            'plural' => 'Templates',
        ],
    ],
    'fields' => [
        'number' => 'Document Number',
        'type' => 'Type',
        'status' => 'Status',
        'customer_name' => 'Customer Name',
        'issue_date' => 'Issue Date',
        'due_date' => 'Due Date',
        'total' => 'Total',
    ],
    'actions' => [
        'generate_pdf' => 'Generate PDF',
        'download_pdf' => 'Download PDF',
        'send_email' => 'Send Email',
        'mark_paid' => 'Mark as Paid',
    ],
];
```

### Use Translations

```php
TextColumn::make('number')
    ->label(__('filament-docs.fields.number'));

Action::make('generate_pdf')
    ->label(__('filament-docs.actions.generate_pdf'));
```
