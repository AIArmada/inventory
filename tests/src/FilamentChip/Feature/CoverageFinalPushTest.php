<?php

declare(strict_types=1);

use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Models\RecurringSchedule;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ViewCompanyStatement;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ListRecurringSchedules;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ViewRecurringSchedule;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\RelationManagers\ChargesRelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    filament()->setCurrentPanel('test');

    Schema::dropIfExists('chip_company_statements');
    Schema::create('chip_company_statements', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->timestamps();
    });

    Schema::dropIfExists('chip_recurring_schedules');
    Schema::create('chip_recurring_schedules', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('status')->nullable();
        $table->integer('amount_minor')->default(0);
        $table->string('currency')->default('MYR');
        $table->string('interval')->default('monthly');
        $table->integer('interval_count')->default(1);
        $table->timestamps();
    });
});

it('covers remaining company statement and recurring schedule pages/relation manager', function (): void {
    $statement = CompanyStatement::query()->create([
        'status' => 'completed',
        'is_test' => false,
    ]);

    app()->instance(ChipCollectService::class, new class
    {
        public function getCompanyStatement(string $id): object
        {
            return (object) ['download_url' => null];
        }

        public function cancelCompanyStatement(string $id): void {}
    });

    $view = new ViewCompanyStatement;

    $recordProp = (new ReflectionClass(Filament\Resources\Pages\ViewRecord::class))->getProperty('record');
    $recordProp->setAccessible(true);
    $recordProp->setValue($view, $statement);

    expect($view->getTitle())->toContain('Statement');
    $actions = (new ReflectionClass($view))->getMethod('getHeaderActions');
    $actions->setAccessible(true);

    foreach ($actions->invoke($view) as $action) {
        $action->isVisible();

        if ($action->getName() === 'download') {
            $fn = $action->getActionFunction();

            if ($fn instanceof Closure) {
                $fn();
            }
        }
    }

    $schedule = RecurringSchedule::query()->create([
        'status' => 'active',
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'interval' => 'monthly',
        'interval_count' => 1,
    ]);

    $list = new ListRecurringSchedules;
    $m = (new ReflectionClass($list))->getMethod('getHeaderActions');
    $m->setAccessible(true);
    expect($m->invoke($list))->toBeArray()->toBeEmpty();

    $viewSchedule = new ViewRecurringSchedule;
    $recordProp->setValue($viewSchedule, $schedule);
    expect($viewSchedule->getTitle())->toContain('Schedule');

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('striped')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();

    (new ChargesRelationManager)->table($table);
});
