<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use App\Models\StockTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockTransferRejectedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public StockTransfer $stock_transfer)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->stock_transfer->load([
            'fromStore',
            'toStore',
            'inventories.productVariant',
            'inventories.productVariant.image',
        ]);

        return (new MailMessage)->markdown('mails.stock-transfer.rejected', [
            'stock_transfer' => $this->stock_transfer,
        ])
            ->subject(__('Stock Transfer Rejected'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $stockTransfer = $this->stock_transfer;

        return [
            'type' => 'stock_transfer_received',
            'title' => 'Stock Transfer Received',
            'message' => sprintf(
                'Reference No: %s | Rejected By: %s%s | Rejection Reasion: %s',
                $stockTransfer->reference_no,
                $stockTransfer->receiver->name,
                $stockTransfer->comment ? " | Comment: {$stockTransfer->comment}" : '',
                $stockTransfer->rejection_reason ?: 'No reason provided'
            ),
            'icon' => 'truck', // e.g. for frontend badge/icon use
            'level' => NotificationLevel::ERROR->value,
            'action_url' => route('api.stock-transfers.show', $stockTransfer->id),
            'meta' => [
                'id' => $stockTransfer->id,
                'reference_no' => $stockTransfer->reference_no,
                'rejecter_name' => $stockTransfer->receiver->name,
                'comment' => $stockTransfer->comment,
                'dispatch_date' => $stockTransfer->dispatched_at,
                'driver_name' => $stockTransfer->driver_name,
                'driver_phone' => $stockTransfer->phone_number,
                'inventory_count' => $stockTransfer->inventories->count(),
                'total_quantity' => $stockTransfer->inventories->sum('pivot.quantity'),
                'products' => $stockTransfer->inventories->map(function ($inventory, $i) {
                    return [
                        'sn' => $i + 1,
                        'sku' => $inventory->productVariant->sku,
                        'quantity' => $inventory->pivot->quantity,
                        'image' => $inventory->productVariant->images->isNotEmpty() ? $inventory->productVariant->images->first() : $inventory->productVariant->product->images->first()
                    ];
                }),
            ],
        ];
    }
}
