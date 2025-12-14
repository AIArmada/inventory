<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\OrderStatus;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;

describe('Order State Methods - Direct Class Testing', function (): void {
    describe('Delivered State', function (): void {
        it('has correct name', function (): void {
            expect(Delivered::$name)->toBe('delivered');
        });

        it('returns success color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('success');
        });

        it('returns check icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-ICON-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-check');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-LABEL-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-REFUND-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeTrue();
        });

        it('is not final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-FINAL-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeFalse();
        });

        it('cannot cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-CANCEL-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-MODIFY-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('Fraud State', function (): void {
        it('has correct name', function (): void {
            expect(Fraud::$name)->toBe('fraud');
        });

        it('returns danger color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-COLOR-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('danger');
        });

        it('returns exclamation triangle icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-ICON-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-exclamation-triangle');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-LABEL-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-FINAL-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeTrue();
        });

        it('cannot cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-CANCEL-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeFalse();
        });

        it('cannot refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-REFUND-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-MODIFY-' . uniqid(),
                'status' => Fraud::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('OnHold State', function (): void {
        it('has correct name', function (): void {
            expect(OnHold::$name)->toBe('on_hold');
        });

        it('returns gray color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-COLOR-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('gray');
        });

        it('returns pause circle icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-ICON-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-pause-circle');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-LABEL-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-CANCEL-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeTrue();
        });

        it('is not final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-FINAL-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeFalse();
        });

        it('cannot refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-REFUND-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-MODIFY-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('PaymentFailed State', function (): void {
        it('has correct name', function (): void {
            expect(PaymentFailed::$name)->toBe('payment_failed');
        });

        it('returns danger color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-COLOR-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('danger');
        });

        it('returns x-mark icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-ICON-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-x-mark');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-LABEL-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-FINAL-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeTrue();
        });

        it('cannot cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-CANCEL-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeFalse();
        });

        it('cannot refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-REFUND-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-MODIFY-' . uniqid(),
                'status' => PaymentFailed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('Processing State', function (): void {
        it('has correct name', function (): void {
            expect(Processing::$name)->toBe('processing');
        });

        it('returns info color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-COLOR-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('info');
        });

        it('returns cog icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-ICON-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-cog-6-tooth');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-LABEL-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-CANCEL-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeTrue();
        });

        it('is not final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-FINAL-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeFalse();
        });

        it('cannot refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-REFUND-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROC-MODIFY-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('Refunded State', function (): void {
        it('has correct name', function (): void {
            expect(Refunded::$name)->toBe('refunded');
        });

        it('returns gray color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-COLOR-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('gray');
        });

        it('returns banknotes icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-ICON-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-banknotes');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-LABEL-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-FINAL-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeTrue();
        });

        it('cannot cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-CANCEL-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeFalse();
        });

        it('cannot refund again', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-REFUND-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUND-MODIFY-' . uniqid(),
                'status' => Refunded::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('Shipped State', function (): void {
        it('has correct name', function (): void {
            expect(Shipped::$name)->toBe('shipped');
        });

        it('returns primary color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-COLOR-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('primary');
        });

        it('returns truck icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-ICON-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-truck');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-LABEL-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is not final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-FINAL-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeFalse();
        });

        it('cannot cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-CANCEL-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeFalse();
        });

        it('cannot refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-REFUND-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeFalse();
        });

        it('cannot modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-MODIFY-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeFalse();
        });
    });

    describe('Canceled State (Additional Coverage)', function (): void {
        it('returns gray color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-COLOR-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('gray');
        });

        it('returns x-circle icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-ICON-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-x-circle');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-LABEL-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-FINAL-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeTrue();
        });
    });

    describe('Completed State (Additional Coverage)', function (): void {
        it('returns success color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COMPL-COLOR-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('success');
        });

        it('returns check-circle icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COMPL-ICON-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-check-circle');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COMPL-LABEL-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('is final', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COMPL-FINAL-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->isFinal())->toBeTrue();
        });

        it('can refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COMPL-REFUND-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeTrue();
        });
    });

    describe('Created State (Additional Coverage)', function (): void {
        it('returns gray color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CREATED-COLOR-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('gray');
        });

        it('returns plus-circle icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CREATED-ICON-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-plus-circle');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CREATED-LABEL-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CREATED-CANCEL-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeTrue();
        });

        it('can modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CREATED-MODIFY-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeTrue();
        });
    });

    describe('PendingPayment State (Additional Coverage)', function (): void {
        it('returns warning color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PENDING-COLOR-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('warning');
        });

        it('returns clock icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PENDING-ICON-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-clock');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PENDING-LABEL-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can cancel', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PENDING-CANCEL-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canCancel())->toBeTrue();
        });

        it('can modify', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PENDING-MODIFY-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canModify())->toBeTrue();
        });
    });

    describe('Returned State (Additional Coverage)', function (): void {
        it('returns warning color', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-RETURN-COLOR-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->color())->toBe('warning');
        });

        it('returns arrow-uturn-left icon', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-RETURN-ICON-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->icon())->toBe('heroicon-o-arrow-uturn-left');
        });

        it('returns translated label', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-RETURN-LABEL-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->label())->toBeString();
        });

        it('can refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-RETURN-REFUND-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status->canRefund())->toBeTrue();
        });
    });

    describe('OrderStatus Base Class', function (): void {
        it('has default state as Created', function (): void {
            $config = OrderStatus::config();
            expect($config->defaultStateClass)->toBe(Created::class);
        });
    });
});
