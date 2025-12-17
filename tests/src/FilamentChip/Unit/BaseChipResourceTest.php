<?php

declare(strict_types=1);

use AIArmada\FilamentChip\Resources\BaseChipResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('uses config for navigation metadata', function (): void {
    config()->set('filament-chip.navigation.group', 'CHIP');
    config()->set('filament-chip.resources.navigation_sort.purchases', 123);
    config()->set('filament-chip.navigation.badge_color', 'warning');
    config()->set('filament-chip.polling_interval', '10s');

    Schema::dropIfExists('fake_records');

    Schema::create('fake_records', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('owner_id')->nullable();
    });

    $modelClass = new class extends Model {
        protected $table = 'fake_records';
        protected $guarded = [];

        public function scopeForOwner(Builder $query): Builder
        {
            return $query->where('owner_id', 'owner-a');
        }
    };

    $modelFqcn = $modelClass::class;

    $resource = new class ($modelFqcn) extends BaseChipResource {
        public function __construct(private string $modelFqcn) {}

        protected static ?string $model;

        protected static function navigationSortKey(): string
        {
            return 'purchases';
        }

        public static function getModel(): string
        {
            return static::$model;
        }

        public static function setModel(string $model): void
        {
            static::$model = $model;
        }
    };

    $resource::setModel($modelFqcn);

    \Illuminate\Support\Facades\DB::table('fake_records')->insert([
        ['owner_id' => 'owner-a'],
        ['owner_id' => 'owner-b'],
        ['owner_id' => null],
    ]);

    expect($resource::getNavigationGroup())->toBe('CHIP');
    expect($resource::getNavigationSort())->toBe(123);
    expect($resource::getNavigationBadgeColor())->toBe('warning');
    expect($resource::getNavigationBadge())->toBe('1');

    $polling = (new ReflectionClass(BaseChipResource::class))->getMethod('pollingInterval');
    $polling->setAccessible(true);
    expect($polling->invoke(null))->toBe('10s');
});
