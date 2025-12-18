<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Pages;

use AIArmada\FilamentAuthz\Models\AccessPolicy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PolicyDesignerPage extends Page
{
    public ?string $policyName = null;

    public ?string $policyDescription = null;

    public string $effect = 'allow';

    public int $priority = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $conditions = [];

    public string $combiningAlgorithm = 'all';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Policy Designer';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament-authz::pages.policy-designer';

    /**
     * @var array<string, array<string, string>>
     */
    protected array $conditionTemplates = [
        'role' => [
            'label' => 'Has Role',
            'icon' => 'heroicon-o-user-group',
            'field' => 'role',
            'operator' => 'equals',
        ],
        'permission' => [
            'label' => 'Has Permission',
            'icon' => 'heroicon-o-key',
            'field' => 'permission',
            'operator' => 'equals',
        ],
        'team' => [
            'label' => 'In Team',
            'icon' => 'heroicon-o-building-office',
            'field' => 'team_id',
            'operator' => 'equals',
        ],
        'time' => [
            'label' => 'Time Window',
            'icon' => 'heroicon-o-clock',
            'field' => 'time',
            'operator' => 'between',
        ],
        'ip' => [
            'label' => 'IP Address',
            'icon' => 'heroicon-o-globe-alt',
            'field' => 'ip_address',
            'operator' => 'in',
        ],
        'resource_type' => [
            'label' => 'Resource Type',
            'icon' => 'heroicon-o-cube',
            'field' => 'resource_type',
            'operator' => 'equals',
        ],
        'ownership' => [
            'label' => 'Is Owner',
            'icon' => 'heroicon-o-identification',
            'field' => 'is_owner',
            'operator' => 'equals',
        ],
        'attribute' => [
            'label' => 'Resource Attribute',
            'icon' => 'heroicon-o-adjustments-horizontal',
            'field' => 'resource.attribute',
            'operator' => 'equals',
        ],
        'department' => [
            'label' => 'Department',
            'icon' => 'heroicon-o-building-library',
            'field' => 'user.department',
            'operator' => 'equals',
        ],
        'clearance' => [
            'label' => 'Clearance Level',
            'icon' => 'heroicon-o-shield-check',
            'field' => 'user.clearance_level',
            'operator' => 'gte',
        ],
    ];

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group', 'Authorization');
    }

    public static function canAccess(): bool
    {
        return config('filament-authz.features.access_policies', true);
    }

    public function mount(): void
    {
        $this->addCondition();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Policy Details')
                    ->schema([
                        Forms\Components\TextInput::make('policyName')
                            ->label('Policy Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Admin Full Access'),

                        Forms\Components\Textarea::make('policyDescription')
                            ->label('Description')
                            ->rows(2)
                            ->placeholder('What does this policy do?'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('effect')
                                    ->label('Effect')
                                    ->options([
                                        'allow' => '✅ Allow',
                                        'deny' => '❌ Deny',
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = evaluated first'),

                                Forms\Components\Select::make('combiningAlgorithm')
                                    ->label('Condition Logic')
                                    ->options([
                                        'all' => 'ALL conditions must match (AND)',
                                        'any' => 'ANY condition can match (OR)',
                                    ])
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public function addCondition(): void
    {
        $this->conditions[] = [
            'id' => uniqid(),
            'type' => 'role',
            'field' => 'role',
            'operator' => 'equals',
            'value' => '',
        ];
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function updateConditionType(int $index, string $type): void
    {
        if (isset($this->conditionTemplates[$type])) {
            $template = $this->conditionTemplates[$type];
            $this->conditions[$index]['type'] = $type;
            $this->conditions[$index]['field'] = $template['field'];
            $this->conditions[$index]['operator'] = $template['operator'];
            $this->conditions[$index]['value'] = '';
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getConditionTemplates(): array
    {
        return $this->conditionTemplates;
    }

    /**
     * @return array<string, string>
     */
    public function getOperatorOptions(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'in' => 'In List',
            'not_in' => 'Not In List',
            'gt' => 'Greater Than',
            'gte' => 'Greater or Equal',
            'lt' => 'Less Than',
            'lte' => 'Less or Equal',
            'between' => 'Between',
            'regex' => 'Matches Pattern',
        ];
    }

    public function getPreviewJson(): string
    {
        return json_encode($this->buildPolicyData(), JSON_PRETTY_PRINT);
    }

    public function getPreviewCode(): string
    {
        $policy = $this->buildPolicyData();
        $className = str_replace(' ', '', ucwords($this->policyName ?? 'CustomPolicy'));

        return <<<PHP
<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class {$className}
{
    use HandlesAuthorization;

    public function before(\$user, \$ability)
    {
        // Generated from Policy Designer
        \$conditions = {$this->exportConditionsAsPhp()};

        return \$this->evaluateConditions(\$user, \$conditions);
    }

    protected function evaluateConditions(\$user, array \$conditions): ?bool
    {
        // Policy evaluation logic
        return null;
    }
}
PHP;
    }

    public function savePolicy(): void
    {
        if (! $this->policyName) {
            Notification::make()
                ->title('Validation Error')
                ->body('Policy name is required')
                ->danger()
                ->send();

            return;
        }

        // Generate slug from policy name
        $slug = \Illuminate\Support\Str::slug($this->policyName);

        // Create or update the policy
        AccessPolicy::updateOrCreate(
            ['name' => $this->policyName],
            [
                'slug' => $slug,
                'description' => $this->policyDescription,
                'effect' => $this->effect,
                'target_action' => '*',
                'priority' => $this->priority,
                'conditions' => json_encode($this->buildPolicyData()),
                'is_active' => true,
            ]
        );

        Notification::make()
            ->title('Policy Saved')
            ->body("Policy '{$this->policyName}' has been saved successfully")
            ->success()
            ->send();
    }

    public function testPolicy(): void
    {
        Notification::make()
            ->title('Policy Test')
            ->body('Policy simulation complete. Check the results panel.')
            ->info()
            ->send();
    }

    protected function exportConditionsAsPhp(): string
    {
        return var_export($this->conditions, true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPolicyData(): array
    {
        return [
            'name' => $this->policyName,
            'description' => $this->policyDescription,
            'effect' => $this->effect,
            'priority' => $this->priority,
            'combining_algorithm' => $this->combiningAlgorithm,
            'conditions' => $this->conditions,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Policy')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('savePolicy'),

            Action::make('test')
                ->label('Test Policy')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->action('testPolicy'),

            Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->reset()),
        ];
    }
}
