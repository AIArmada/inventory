<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Pages;

use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use UnitEnum;

class ImportExportProducts extends Page
{
    public ?array $importData = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'filament-products::pages.import-export-products';

    protected static string | UnitEnum | null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Import / Export';

    private function resolveOwner(): ?Model
    {
        return OwnerScope::resolveOwner();
    }

    public function getImportFormProperty(): Schema
    {
        return Schema::make($this)
            ->schema([
                \Filament\Schemas\Components\Section::make('Import Products')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'text/plain'])
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->helperText('Upload a CSV file with product data'),

                        Forms\Components\Toggle::make('update_existing')
                            ->label('Update Existing Products')
                            ->helperText('Update products that already exist (matched by SKU)')
                            ->default(false),

                        Forms\Components\Toggle::make('skip_errors')
                            ->label('Skip Errors')
                            ->helperText('Continue importing even if some rows have errors')
                            ->default(true),
                    ]),
            ])
            ->statePath('importData');
    }

    public function import(): void
    {
        $data = $this->importData;

        /** @var string|array<int, string>|null $csvFile */
        $csvFile = $data['csv_file'] ?? null;

        if (is_array($csvFile)) {
            $csvFile = $csvFile[0] ?? null;
        }

        try {
            if (! is_string($csvFile) || $csvFile === '') {
                throw new Exception('CSV file is missing.');
            }

            if (! Str::startsWith($csvFile, 'imports/')) {
                throw new Exception('Invalid CSV file path.');
            }

            if (! Storage::disk('local')->exists($csvFile)) {
                throw new Exception('CSV file not found.');
            }

            $filePath = Storage::disk('local')->path($csvFile);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($records as $offset => $record) {
                try {
                    $productData = [
                        'name' => $record['name'] ?? null,
                        'sku' => $record['sku'] ?? null,
                        'slug' => $record['slug'] ?? \Illuminate\Support\Str::slug($record['name'] ?? ''),
                        'description' => $record['description'] ?? null,
                        'short_description' => $record['short_description'] ?? null,
                        'currency' => $record['currency'] ?? null,
                        'price' => isset($record['price']) ? (int) round(((float) $record['price']) * 100) : 0,
                        'compare_price' => isset($record['compare_price']) ? (int) round(((float) $record['compare_price']) * 100) : null,
                        'cost' => isset($record['cost']) ? (int) round(((float) $record['cost']) * 100) : null,
                        'weight' => $record['weight'] ?? null,
                        'status' => ProductStatus::tryFrom($record['status'] ?? 'draft') ?? ProductStatus::Draft,
                        'type' => ProductType::tryFrom($record['type'] ?? 'simple') ?? ProductType::Simple,
                        'visibility' => ProductVisibility::tryFrom($record['visibility'] ?? 'catalog_search') ?? ProductVisibility::CatalogSearch,
                        'is_featured' => filter_var($record['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_taxable' => filter_var($record['is_taxable'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'requires_shipping' => filter_var($record['requires_shipping'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'tax_class' => $record['tax_class'] ?? null,
                    ];

                    $productData = array_filter($productData, fn ($value): bool => $value !== null);

                    if (($data['update_existing'] ?? false) && isset($record['sku'])) {
                        $owner = $this->resolveOwner();
                        $product = Product::query()->forOwner($owner, false)->where('sku', $record['sku'])->first();
                        if ($product) {
                            $product->update($productData);
                            $updated++;

                            continue;
                        }
                    }

                    $product = new Product($productData);
                    $owner = $this->resolveOwner();
                    if ($owner !== null) {
                        $product->assignOwner($owner);
                    }
                    $product->save();
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Row {$offset}: {$e->getMessage()}";
                    if (! ($data['skip_errors'] ?? true)) {
                        throw $e;
                    }
                }
            }

            // Clean up uploaded file
            Storage::disk('local')->delete((string) $csvFile);

            Notification::make()
                ->title('Import completed')
                ->body("Imported: {$imported}, Updated: {$updated}, Errors: " . count($errors))
                ->success()
                ->send();

            if (! empty($errors)) {
                Notification::make()
                    ->title('Import errors')
                    ->body(implode("\n", array_slice($errors, 0, 5)))
                    ->warning()
                    ->send();
            }

            $this->importData = [];
        } catch (Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\CheckboxList::make('fields')
                        ->label('Select Fields to Export')
                        ->options([
                            'name' => 'Name',
                            'sku' => 'SKU',
                            'slug' => 'Slug',
                            'description' => 'Description',
                            'short_description' => 'Short Description',
                            'currency' => 'Currency',
                            'price' => 'Price',
                            'compare_price' => 'Compare Price',
                            'cost' => 'Cost',
                            'weight' => 'Weight',
                            'status' => 'Status',
                            'type' => 'Type',
                            'visibility' => 'Visibility',
                            'is_featured' => 'Featured',
                            'is_taxable' => 'Taxable',
                            'requires_shipping' => 'Requires Shipping',
                            'tax_class' => 'Tax Class',
                        ])
                        ->default(['name', 'sku', 'currency', 'price', 'status', 'type'])
                        ->required()
                        ->columns(3),

                    Forms\Components\Select::make('status_filter')
                        ->label('Filter by Status')
                        ->options([
                            'all' => 'All Products',
                            ...collect(ProductStatus::cases())
                                ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                ->all(),
                        ])
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    return $this->exportProducts($data);
                }),

            Action::make('download_template')
                ->label('Download CSV Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    return $this->downloadTemplate();
                }),
        ];
    }

    protected function exportProducts(array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Product::query()->forOwner();

        // Apply status filter
        if ($data['status_filter'] !== 'all') {
            $query->where('status', $data['status_filter']);
        }

        $products = $query->get();

        $csv = Writer::createFromString();

        // Add headers
        $csv->insertOne($data['fields']);

        // Add data
        foreach ($products as $product) {
            $row = [];
            foreach ($data['fields'] as $field) {
                $value = $product->{$field};

                // Convert cents to dollars for price fields
                if (in_array($field, ['price', 'compare_price', 'cost']) && is_numeric($value)) {
                    $value /= 100;
                }

                // Convert enums to their values
                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                }

                // Convert booleans to readable format
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                $row[] = $value;
            }
            $csv->insertOne($row);
        }

        return response()->streamDownload(function () use ($csv): void {
            echo $csv->toString();
        }, 'products-export-' . now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = Writer::createFromString();

        $csv->insertOne([
            'name',
            'sku',
            'slug',
            'description',
            'short_description',
            'currency',
            'price',
            'compare_price',
            'cost',
            'weight',
            'status',
            'type',
            'visibility',
            'is_featured',
            'is_taxable',
            'requires_shipping',
            'tax_class',
        ]);

        // Add example row
        $csv->insertOne([
            'Example Product',
            'EXAMPLE-001',
            'example-product',
            'This is an example product description',
            'Short desc',
            'MYR',
            '99.99',
            '129.99',
            '50.00',
            '0.5',
            'active',
            'simple',
            'catalog_search',
            'true',
            'true',
            'true',
            'standard',
        ]);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv->toString();
        }, 'product-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
