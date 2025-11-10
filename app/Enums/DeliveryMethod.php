<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum DeliveryMethod: string
{
    use Enumerable;

    case PICKUP = 'pickup';
    case DOOR_DELIVERY = 'door_delivery';

    public function label(): string
    {
        return match ($this) {
            self::PICKUP => 'Pickup from Store',
            self::DOOR_DELIVERY => 'Door Delivery',
        };
    }
}
