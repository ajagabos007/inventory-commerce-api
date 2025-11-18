<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum OrderStatus: string
{
    use Enumerable;

    // Actual statuses
    case ONGOING = 'ongoing';        // waiting for payment
    case NEW = 'new';                // payment made & verified
    case PROCESSING = 'processing';  // order being reviewed/processed
    case DISPATCHED = 'dispatched';  // sent out for delivery
    case DELIVERED = 'delivered';    // customer received item
    case COMPLETED = 'completed';    // order is fully closed

    /**
     * Human-friendly descriptions for each status
     */
    public function description(): string
    {
        return match ($this) {
            self::ONGOING      => 'Order is ongoing and waiting for payment.',
            self::NEW          => 'Payment made and verified.',
            self::PROCESSING   => 'Order is being reviewed and processed.',
            self::DISPATCHED   => 'Order has been dispatched for delivery.',
            self::DELIVERED    => 'Order has been delivered to the customer.',
            self::COMPLETED    => 'Order has been completed and closed.',
            default         => 'N/A.',
        };
    }
}
