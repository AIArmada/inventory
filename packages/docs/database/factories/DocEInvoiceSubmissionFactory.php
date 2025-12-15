<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEInvoiceSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocEInvoiceSubmissionFactory extends Factory
{
    protected $model = DocEInvoiceSubmission::class;

    public function definition(): array
    {
        return [
            'doc_id' => Doc::factory(),
            'submission_uid' => $this->faker->uuid,
            'status' => 'pending',
            'validation_status' => null,
            'long_id' => $this->faker->uuid,
        ];
    }
}
