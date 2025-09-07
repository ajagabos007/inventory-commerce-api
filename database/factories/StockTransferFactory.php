<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' => StockTransfer::generateReferenceNo(),
            'sender_id' => $sender = User::whereHas('staff')
                ->inRandomOrder()
                ->first(),
            'receiver_id' => $reciever = User::where('id', '<>', $sender?->id)
                ->whereHas('staff')
                ->inRandomOrder()
                ->first(),
            'driver_name' => fake()->name(),
            'driver_phone_number' => fake()->e164PhoneNumber(),
            'comment' => fake()->realText(50),
            'from_store_id' => $from_store = Store::whereHas('inventories', fn ($query) => $query->where('quantity', '>', 0))
                ->inRandomOrder()
                ->first(),
            'to_store_id' => $to_store = Store::where('id', '<>', $from_store?->id)
                ->inRandomOrder()
                ->first(),
            'accepted_at' => null,
            'status' => Status::NEW->value,

        ];
    }
}
