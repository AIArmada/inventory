<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Enums\PolicyType;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;
use AIArmada\FilamentAuthz\Support\UserModelResolver;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;

class GeneratePoliciesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authz:policies
        {--type= : Policy type (basic, hierarchical, temporal, contextual, abac, composite)}
        {--resource=* : Specific resources to generate for}
        {--model=* : Specific models to generate for}
        {--panel= : Panel to discover resources from}
        {--namespace= : Custom policy namespace}
        {--force : Overwrite existing policies}
        {--dry-run : Preview without writing}
        {--interactive : Interactive mode}';

    /**
     * @var string
     */
    protected $description = 'Generate Laravel policies for Filament resources';

    public function handle(
        EntityDiscoveryService $discovery,
        PolicyGeneratorService $generator
    ): int {
        $type = $this->getPolicyType();

        $resources = $this->getResources($discovery);

        if ($resources->isEmpty()) {
            $this->warn('No resources found to generate policies for.');

            return Command::SUCCESS;
        }

        $this->info("🔧 Generating {$type->value} policies for " . $resources->count() . " resources...\n");

        $generated = 0;
        $skipped = 0;

        foreach ($resources as $resource) {
            $modelClass = $resource->model;

            $options = [
                'namespace' => $this->option('namespace') ?? 'App\\Policies',
                'userModel' => UserModelResolver::resolve(),
            ];

            $policy = $generator->generate($modelClass, $type, $options);

            if ($this->option('dry-run')) {
                $this->line("Would generate: {$policy->path}");

                continue;
            }

            if (file_exists($policy->path) && ! $this->option('force')) {
                $this->line('<fg=yellow>⏭</> Skipped: ' . class_basename($modelClass) . 'Policy (exists)');
                $skipped++;

                continue;
            }

            if ($policy->write($this->option('force'))) {
                $this->line('<fg=green>✓</> Generated: ' . class_basename($modelClass) . 'Policy');
                $generated++;
            } else {
                $this->line('<fg=red>✗</> Failed: ' . class_basename($modelClass) . 'Policy');
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. Use without --dry-run to generate policies.');
        } else {
            $this->info("✅ Generated {$generated} policies" . ($skipped > 0 ? ", skipped {$skipped}" : ''));
        }

        return Command::SUCCESS;
    }

    protected function getPolicyType(): PolicyType
    {
        if ($type = $this->option('type')) {
            return PolicyType::from($type);
        }

        if ($this->option('interactive')) {
            $selection = select(
                label: 'What type of policies do you want to generate?',
                options: [
                    'basic' => PolicyType::Basic->label(),
                    'hierarchical' => PolicyType::Hierarchical->label(),
                    'contextual' => PolicyType::Contextual->label(),
                    'temporal' => PolicyType::Temporal->label(),
                    'abac' => PolicyType::Abac->label(),
                    'composite' => PolicyType::Composite->label(),
                ],
                default: 'basic'
            );

            return PolicyType::from($selection);
        }

        return PolicyType::from(config('filament-authz.policies.default_type', 'basic'));
    }

    /**
     * @return Collection<int, DiscoveredResource>
     */
    protected function getResources(EntityDiscoveryService $discovery): Collection
    {
        $options = [];

        if ($panel = $this->option('panel')) {
            $options['panels'] = [$panel];
        }

        $resources = $discovery->discoverResources($options);

        // Filter by specific resources if provided
        $specificResources = $this->option('resource');
        if (! empty($specificResources)) {
            $resources = $resources->filter(function ($resource) use ($specificResources) {
                $basename = class_basename($resource->fqcn);

                return in_array($basename, $specificResources)
                    || in_array($resource->fqcn, $specificResources);
            });
        }

        // Filter by specific models if provided
        $specificModels = $this->option('model');
        if (! empty($specificModels)) {
            $resources = $resources->filter(function ($resource) use ($specificModels) {
                $modelBasename = class_basename($resource->model);

                return in_array($modelBasename, $specificModels)
                    || in_array($resource->model, $specificModels);
            });
        }

        return $resources;
    }
}
