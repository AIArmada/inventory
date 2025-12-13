<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Contracts\Auditable;

beforeEach(function (): void {
    // Create test table for the model
    Schema::dropIfExists('test_auditable_models');
    Schema::create('test_auditable_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('test_auditable_models');
});

/**
 * Create a test model with custom audit configuration.
 */
function createAuditableModel(array $auditInclude = []): Model
{
    return new class($auditInclude) extends Model implements Auditable
    {
        use HasCommerceAudit;

        protected $table = 'test_auditable_models';

        protected $fillable = ['name', 'email', 'password', 'status'];

        public function __construct(array $customAuditInclude = [])
        {
            parent::__construct();
            if (! empty($customAuditInclude)) {
                $this->auditInclude = $customAuditInclude;
            }
        }
    };
}

it('returns audit include list', function (): void {
    $model = createAuditableModel(['name', 'email', 'status']);

    expect($model->getAuditInclude())->toBe(['name', 'email', 'status']);
});

it('returns audit exclude list with sensitive fields merged', function (): void {
    $model = createAuditableModel();
    $excludeList = $model->getAuditExclude();

    expect($excludeList)->toContain('password');
    expect($excludeList)->toContain('credit_card');
    expect($excludeList)->toContain('cvv');
    expect($excludeList)->toContain('ssn');
});

it('returns audit threshold', function (): void {
    $model = createAuditableModel();

    expect($model->getAuditThreshold())->toBe(100);
});

it('checks if attribute is auditable when using include list', function (): void {
    $model = createAuditableModel(['name', 'email', 'status']);

    expect($model->isAuditableAttribute('name'))->toBeTrue();
    expect($model->isAuditableAttribute('email'))->toBeTrue();
    expect($model->isAuditableAttribute('status'))->toBeTrue();
    expect($model->isAuditableAttribute('password'))->toBeFalse();
});

it('redacts sensitive fields in audit transformation', function (): void {
    $model = createAuditableModel();

    $data = [
        'old_values' => [
            'name' => 'Old Name',
            'password' => 'secret123',
            'credit_card' => '4111111111111111',
        ],
        'new_values' => [
            'name' => 'New Name',
            'password' => 'newsecret456',
        ],
    ];

    $transformed = $model->transformAudit($data);

    expect($transformed['old_values']['name'])->toBe('Old Name');
    expect($transformed['old_values']['password'])->toBe('[REDACTED]');
    expect($transformed['old_values']['credit_card'])->toBe('[REDACTED]');
    expect($transformed['new_values']['name'])->toBe('New Name');
    expect($transformed['new_values']['password'])->toBe('[REDACTED]');
});

it('adds commerce tags to audit data', function (): void {
    $model = createAuditableModel();

    $data = [
        'old_values' => ['name' => 'Old'],
        'new_values' => ['name' => 'New'],
    ];

    $transformed = $model->transformAudit($data);

    expect($transformed['tags'])->toBe(['commerce']);
});

it('uses all fillable attributes when no include list specified', function (): void {
    $model = createAuditableModel([]);

    // When auditInclude is empty, isAuditableAttribute checks exclude list
    expect($model->isAuditableAttribute('name'))->toBeTrue();
    expect($model->isAuditableAttribute('email'))->toBeTrue();

    // Sensitive fields should still be excluded
    expect($model->isAuditableAttribute('password'))->toBeFalse();
});

it('includes default sensitive fields', function (): void {
    $model = createAuditableModel();
    $reflectionMethod = new ReflectionMethod($model, 'getSensitiveFields');
    $sensitiveFields = $reflectionMethod->invoke($model);

    expect($sensitiveFields)->toContain('password');
    expect($sensitiveFields)->toContain('password_hash');
    expect($sensitiveFields)->toContain('remember_token');
    expect($sensitiveFields)->toContain('api_key');
    expect($sensitiveFields)->toContain('secret');
    expect($sensitiveFields)->toContain('credit_card');
    expect($sensitiveFields)->toContain('card_number');
    expect($sensitiveFields)->toContain('cvv');
    expect($sensitiveFields)->toContain('ssn');
    expect($sensitiveFields)->toContain('tax_id');
});
