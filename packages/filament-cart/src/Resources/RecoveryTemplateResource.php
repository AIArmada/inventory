<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources;

use AIArmada\FilamentCart\Models\RecoveryTemplate;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages\CreateRecoveryTemplate;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages\EditRecoveryTemplate;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages\ListRecoveryTemplates;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages\ViewRecoveryTemplate;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class RecoveryTemplateResource extends Resource
{
    protected static ?string $model = RecoveryTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Recovery Templates';

    protected static ?string $modelLabel = 'Recovery Template';

    protected static ?string $pluralModelLabel = 'Recovery Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(2),
                        Select::make('type')
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'push' => 'Push Notification',
                            ])
                            ->required()
                            ->reactive(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        Checkbox::make('is_default')
                            ->label('Set as Default Template'),
                    ])
                    ->columns(2),

                Section::make('Email Content')
                    ->schema([
                        TextInput::make('email_subject')
                            ->label('Subject Line')
                            ->maxLength(255)
                            ->helperText('Variables: {{customer_name}}, {{cart_total}}, {{discount_code}}'),
                        TextInput::make('email_preheader')
                            ->label('Preheader Text')
                            ->maxLength(255)
                            ->helperText('Preview text shown in email clients'),
                        RichEditor::make('email_body_html')
                            ->label('HTML Body')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'orderedList',
                                'bulletList',
                                'blockquote',
                            ]),
                        Textarea::make('email_body_text')
                            ->label('Plain Text Body')
                            ->rows(5)
                            ->columnSpanFull(),
                        TextInput::make('email_from_name')
                            ->label('From Name'),
                        TextInput::make('email_from_email')
                            ->label('From Email')
                            ->email(),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('type') === 'email'),

                Section::make('SMS Content')
                    ->schema([
                        Textarea::make('sms_body')
                            ->label('SMS Message')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Max 160 characters. Variables: {{customer_name}}, {{cart_url}}, {{discount_code}}')
                            ->hint(fn ($state) => mb_strlen($state ?? '') . '/160'),
                    ])
                    ->visible(fn ($get) => $get('type') === 'sms'),

                Section::make('Push Notification Content')
                    ->schema([
                        TextInput::make('push_title')
                            ->label('Title')
                            ->maxLength(50),
                        Textarea::make('push_body')
                            ->label('Body')
                            ->rows(2)
                            ->maxLength(140),
                        TextInput::make('push_icon')
                            ->label('Icon URL'),
                        TextInput::make('push_action_url')
                            ->label('Action URL')
                            ->helperText('URL to open when notification is clicked'),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('type') === 'push'),

                Section::make('Available Variables')
                    ->schema([
                        ViewField::make('variables_info')
                            ->view('filament-cart::components.template-variables'),
                    ])
                    ->collapsible()
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
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'email' => 'primary',
                        'sms' => 'success',
                        'push' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('times_used')
                    ->label('Used')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('open_rate')
                    ->label('Open Rate')
                    ->getStateUsing(fn (RecoveryTemplate $record) => number_format($record->getOpenRate() * 100, 1) . '%'),
                TextColumn::make('click_rate')
                    ->label('Click Rate')
                    ->getStateUsing(fn (RecoveryTemplate $record) => number_format($record->getClickRate() * 100, 1) . '%'),
                TextColumn::make('conversion_rate')
                    ->label('Conv. Rate')
                    ->getStateUsing(fn (RecoveryTemplate $record) => number_format($record->getConversionRate() * 100, 1) . '%'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecoveryTemplates::route('/'),
            'create' => CreateRecoveryTemplate::route('/create'),
            'view' => ViewRecoveryTemplate::route('/{record}'),
            'edit' => EditRecoveryTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cart.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-cart.resources.navigation_sort.recovery_templates', 41);
    }
}
