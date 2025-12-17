<?php

declare(strict_types=1);

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\RecurringService;
use Illuminate\Support\Carbon;

describe('RecurringService', function (): void {
    beforeEach(function (): void {
        $this->chipService = Mockery::mock(ChipCollectService::class);
        $this->service = new RecurringService($this->chipService);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(RecurringService::class);
    });

    describe('createSchedule', function (): void {
        it('has createSchedule method', function (): void {
            expect(method_exists($this->service, 'createSchedule'))->toBeTrue();
        });

        it('createSchedule method signature is correct', function (): void {
            $reflection = new ReflectionMethod($this->service, 'createSchedule');
            $params = $reflection->getParameters();

            // Should have chipClientId, recurringToken, amountMinor, interval, and optional params
            expect(count($params))->toBeGreaterThanOrEqual(4);
            expect($params[0]->getName())->toBe('chipClientId');
            expect($params[1]->getName())->toBe('recurringToken');
            expect($params[2]->getName())->toBe('amountMinor');
            expect($params[3]->getName())->toBe('interval');
        });
    });

    describe('createScheduleFromPurchase', function (): void {
        it('has createScheduleFromPurchase method', function (): void {
            expect(method_exists($this->service, 'createScheduleFromPurchase'))->toBeTrue();
        });

        it('createScheduleFromPurchase method signature is correct', function (): void {
            $reflection = new ReflectionMethod($this->service, 'createScheduleFromPurchase');
            $params = $reflection->getParameters();

            // Should have purchaseData and interval at minimum
            expect(count($params))->toBeGreaterThanOrEqual(2);
            expect($params[0]->getName())->toBe('purchaseData');
            expect($params[1]->getName())->toBe('interval');
        });
    });

    describe('processCharge', function (): void {
        it('has processCharge method', function (): void {
            expect(method_exists($this->service, 'processCharge'))->toBeTrue();
        });
    });

    describe('cancel', function (): void {
        it('has cancel method', function (): void {
            expect(method_exists($this->service, 'cancel'))->toBeTrue();
        });
    });

    describe('pause', function (): void {
        it('has pause method', function (): void {
            expect(method_exists($this->service, 'pause'))->toBeTrue();
        });
    });

    describe('resume', function (): void {
        it('has resume method', function (): void {
            expect(method_exists($this->service, 'resume'))->toBeTrue();
        });
    });

    describe('updateAmount', function (): void {
        it('has updateAmount method', function (): void {
            expect(method_exists($this->service, 'updateAmount'))->toBeTrue();
        });
    });

    describe('updateInterval', function (): void {
        it('has updateInterval method', function (): void {
            expect(method_exists($this->service, 'updateInterval'))->toBeTrue();
        });
    });

    describe('getDueSchedules', function (): void {
        it('has getDueSchedules method', function (): void {
            expect(method_exists($this->service, 'getDueSchedules'))->toBeTrue();
        });

        it('getDueSchedules returns a collection type', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getDueSchedules');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('Illuminate\Database\Eloquent\Collection');
        });
    });

    describe('processAllDue', function (): void {
        it('has processAllDue method', function (): void {
            expect(method_exists($this->service, 'processAllDue'))->toBeTrue();
        });

        it('processAllDue returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'processAllDue');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('array');
        });
    });

    describe('handleFailure', function (): void {
        it('has handleFailure method', function (): void {
            expect(method_exists($this->service, 'handleFailure'))->toBeTrue();
        });
    });

    describe('calculateFirstCharge', function (): void {
        it('has calculateFirstCharge method', function (): void {
            expect(method_exists($this->service, 'calculateFirstCharge'))->toBeTrue();
        });

        it('calculateFirstCharge is private', function (): void {
            $reflection = new ReflectionMethod($this->service, 'calculateFirstCharge');
            expect($reflection->isPrivate())->toBeTrue();
        });

        it('calculateFirstCharge returns Carbon', function (): void {
            $reflection = new ReflectionMethod($this->service, 'calculateFirstCharge');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe(Carbon::class);
        });

        it('calculateFirstCharge accepts interval and count', function (): void {
            $reflection = new ReflectionMethod($this->service, 'calculateFirstCharge');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(2);
            expect($params[0]->getName())->toBe('interval');
            expect($params[1]->getName())->toBe('count');
        });
    });
});
