<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JntOrderStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly JntOrder $order,
        public readonly TrackingStatus $currentStatus,
        public readonly ?string $previousStatusCode = null,
    ) {}

    public function getOrderId(): string
    {
        return $this->order->order_id;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->order->tracking_number;
    }

    public function isDelivered(): bool
    {
        return $this->currentStatus === TrackingStatus::Delivered;
    }

    public function hasException(): bool
    {
        return $this->currentStatus === TrackingStatus::Exception;
    }

    public function isReturning(): bool
    {
        return $this->currentStatus->isReturn();
    }

    public function requiresAttention(): bool
    {
        return $this->currentStatus->requiresAttention();
    }

    public function isTerminal(): bool
    {
        return $this->currentStatus->isTerminal();
    }
}
