<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocApprovalFactory extends Factory
{
    protected $model = DocApproval::class;

    public function definition(): array
    {
        return [
            'doc_id' => Doc::factory(),
            'requested_by' => 1,
            'status' => 'pending',
            'comments' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
