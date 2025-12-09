<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Services\CodeManipulator;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallTraitCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authz:install-trait
        {file? : Path to the target file}
        {--trait= : Fully qualified trait name to install}
        {--preview : Preview changes without applying}
        {--force : Apply changes without confirmation}';

    /**
     * @var string
     */
    protected $description = 'Install authorization traits into your classes';

    /**
     * @var array<string, string>
     */
    protected array $availableTraits = [
        'HasPageAuthz' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz',
        'HasWidgetAuthz' => 'AIArmada\\FilamentAuthz\\Concerns\\HasWidgetAuthz',
        'HasResourceAuthz' => 'AIArmada\\FilamentAuthz\\Concerns\\HasResourceAuthz',
        'HasPanelAuthz' => 'AIArmada\\FilamentAuthz\\Concerns\\HasPanelAuthz',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! $file) {
            $file = text(
                label: 'Enter the path to the file:',
                placeholder: 'app/Filament/Pages/Dashboard.php',
                required: true
            );
        }

        // Resolve to absolute path
        if (! str_starts_with($file, '/')) {
            $file = base_path($file);
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return Command::FAILURE;
        }

        // Select trait
        $traitFqcn = $this->option('trait');

        if (! $traitFqcn) {
            $selected = select(
                label: 'Which trait would you like to install?',
                options: array_merge(
                    ['custom' => 'Enter custom trait'],
                    array_flip($this->availableTraits)
                )
            );

            if ($selected === 'custom') {
                $traitFqcn = text(
                    label: 'Enter the fully qualified trait name:',
                    required: true
                );
            } else {
                $traitFqcn = $selected;
            }
        }

        $traitName = class_basename($traitFqcn);

        // Perform manipulation
        $manipulator = CodeManipulator::make($file);

        // Check if trait already exists
        if ($manipulator->containsTrait($traitFqcn)) {
            $this->info("Trait {$traitName} is already installed.");

            return Command::SUCCESS;
        }

        // Add the trait
        $manipulator->addTrait($traitFqcn);

        // Preview mode
        if ($this->option('preview')) {
            $this->info("\n📝 Preview of changes:\n");
            $this->line($manipulator->diff());
            $this->newLine();
            $this->line('<fg=yellow>Dry run - no changes applied.</>');

            return Command::SUCCESS;
        }

        // Show diff
        $this->info("\n📝 Changes to be made:\n");
        $this->line($manipulator->diff());
        $this->newLine();

        // Confirm
        if (! $this->option('force')) {
            $confirmed = confirm('Apply these changes?', true);

            if (! $confirmed) {
                $this->line('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        // Save
        if ($manipulator->save()) {
            $this->info("✅ Successfully installed {$traitName} into {$file}");
        } else {
            $this->error('Failed to save changes.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
