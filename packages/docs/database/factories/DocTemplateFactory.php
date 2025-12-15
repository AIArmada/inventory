<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocTemplateFactory extends Factory
{
    protected $model = DocTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->slug,
            'view_name' => 'docs::templates.default',
            'doc_type' => 'invoice',
            'is_default' => false,
            'settings' => [],
        ];
    }
}
