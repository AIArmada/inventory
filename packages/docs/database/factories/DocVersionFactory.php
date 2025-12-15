<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocVersionFactory extends Factory
{
    protected $model = DocVersion::class;

    public function definition(): array
    {
        return [
            'doc_id' => Doc::factory(),
            'version_number' => 1,
            'snapshot' => [],
            'change_summary' => $this->faker->sentence,
            'changed_by' => 1,
        ];
    }
}
