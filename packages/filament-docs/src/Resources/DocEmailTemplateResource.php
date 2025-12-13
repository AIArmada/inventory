<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\DocEmailTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

final class DocEmailTemplateResource extends Resource
{
    protected static ?string $model = DocEmailTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $modelLabel = 'Email Template';

    protected static ?string $pluralModelLabel = 'Email Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options(collect(DocType::cases())
                                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                        ->all())
                                    ->required(),

                                Select::make('trigger')
                                    ->options([
                                        'send' => 'When document is sent',
                                        'reminder' => 'Payment reminder',
                                        'overdue' => 'When overdue',
                                        'paid' => 'When paid',
                                        'created' => 'When created',
                                    ])
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Email Content')
                    ->schema([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Variables: {{doc_number}}, {{customer_name}}, {{total}}, {{due_date}}, {{company_name}}')
                            ->columnSpanFull(),

                        RichEditor::make('body')
                            ->required()
                            ->helperText('Variables: {{doc_number}}, {{customer_name}}, {{total}}, {{currency}}, {{due_date}}, {{issue_date}}, {{company_name}}')
                            ->columnSpanFull(),
                    ]),

                Section::make('Available Variables')
                    ->schema([
                        Text::make('variables')
                            ->content('
                                • {{doc_number}} - Document number
                                • {{doc_type}} - Document type
                                • {{customer_name}} - Customer name
                                • {{total}} - Total amount
                                • {{currency}} - Currency code
                                • {{due_date}} - Due date
                                • {{issue_date}} - Issue date
                                • {{company_name}} - Your company name
                            '),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('trigger')
                    ->badge()
                    ->color('info'),

                TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->options(collect(DocType::cases())
                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                        ->all()),

                SelectFilter::make('trigger')
                    ->options([
                        'send' => 'When document is sent',
                        'reminder' => 'Payment reminder',
                        'overdue' => 'When overdue',
                        'paid' => 'When paid',
                        'created' => 'When created',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (DocEmailTemplate $record): void {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->slug = $record->slug . '-copy-' . now()->timestamp;
                        $new->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => DocEmailTemplateResource\Pages\ListDocEmailTemplates::route('/'),
            'create' => DocEmailTemplateResource\Pages\CreateDocEmailTemplate::route('/create'),
            'edit' => DocEmailTemplateResource\Pages\EditDocEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-docs.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.email_templates', 91);
    }
}
