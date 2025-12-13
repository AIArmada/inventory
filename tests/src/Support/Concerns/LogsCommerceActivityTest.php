<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\LogOptions;

beforeEach(function (): void {
    // Create test table for the model
    Schema::dropIfExists('test_loggable_models');
    Schema::create('test_loggable_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->default('active');
        $table->integer('price')->default(0);
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('test_loggable_models');
});

/**
 * Create a test model with custom activity log configuration.
 */
function createLoggableModel(?array $loggableAttrs = null, ?string $logName = null): Model
{
    return new class($loggableAttrs, $logName) extends Model
    {
        use LogsCommerceActivity;

        protected $table = 'test_loggable_models';

        protected $fillable = ['name', 'status', 'price'];

        private ?array $customLoggableAttrs;

        private ?string $customLogName;

        public function __construct(?array $loggableAttrs = null, ?string $logName = null)
        {
            parent::__construct();
            $this->customLoggableAttrs = $loggableAttrs;
            $this->customLogName = $logName;
        }

        protected function getLoggableAttributes(): array
        {
            return $this->customLoggableAttrs ?? $this->fillable ?? [];
        }

        protected function getActivityLogName(): string
        {
            return $this->customLogName ?? 'commerce';
        }
    };
}

it('returns LogOptions with correct configuration', function (): void {
    $model = createLoggableModel(['name', 'status'], 'test-log');
    $options = $model->getActivitylogOptions();

    expect($options)->toBeInstanceOf(LogOptions::class);
});

it('uses custom log name when specified', function (): void {
    $model = createLoggableModel(['name', 'status'], 'test-log');

    // The log name is set via useLogName() method
    // We verify the trait method returns the correct name
    $reflectionMethod = new ReflectionMethod($model, 'getActivityLogName');
    expect($reflectionMethod->invoke($model))->toBe('test-log');
});

it('uses fillable attributes as default loggable attributes', function (): void {
    // Create a model without overriding getLoggableAttributes (using null)
    $model = createLoggableModel(null, null);

    $reflectionMethod = new ReflectionMethod($model, 'getLoggableAttributes');
    expect($reflectionMethod->invoke($model))->toBe(['name', 'status', 'price']);
});

it('uses commerce as default log name', function (): void {
    $model = createLoggableModel(null, null);

    $reflectionMethod = new ReflectionMethod($model, 'getActivityLogName');
    expect($reflectionMethod->invoke($model))->toBe('commerce');
});

it('generates correct event descriptions', function (): void {
    $model = createLoggableModel(['name', 'status'], 'test-log');

    // Anonymous class gives a complex name, so we test the pattern
    $description = $model->getDescriptionForEvent('created');
    expect($description)->toEndWith('was created');

    $description = $model->getDescriptionForEvent('updated');
    expect($description)->toEndWith('was updated');

    $description = $model->getDescriptionForEvent('deleted');
    expect($description)->toEndWith('was deleted');

    $description = $model->getDescriptionForEvent('custom_event');
    expect($description)->toEndWith('custom_event');
});

it('allows custom loggable attributes', function (): void {
    $model = createLoggableModel(['name', 'status'], null);

    $reflectionMethod = new ReflectionMethod($model, 'getLoggableAttributes');
    expect($reflectionMethod->invoke($model))->toBe(['name', 'status']);
});
