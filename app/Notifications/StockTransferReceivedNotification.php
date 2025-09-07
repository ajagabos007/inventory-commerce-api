<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockTransferReceivedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public StockTransfer $stock_transfer) {}

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
            'inventories.item',
            'inventories.item.image',
            'inventories.item.category',
            'inventories.item.type',
            'inventories.item.colour',
        ]);

        return (new MailMessage)->markdown('mails.stock-transfer.received', [
            'stock_transfer' => $this->stock_transfer,
        ])
            ->subject(__('Stock Transfer Received'));
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
                'Reference No: %s | Receiver: %s%s | Received Date: %s',
                $stockTransfer->reference_no,
                $stockTransfer->receiver->name,
                $stockTransfer->comment ? " | Comment: {$stockTransfer->comment}" : '',
                (empty($stockTransfer->accepted_at) ? $stockTransfer->accepted_at : Carbon::parse($stockTransfer->dispatched_at)->format('M d, Y h:i A'))
            ),
            'icon' => 'truck', // e.g. for frontend badge/icon use
            'level' => NotificationLevel::SUCCESS->value,
            'action_url' => route('api.stock-transfers.show', $stockTransfer->id),
            'meta' => [
                'id' => $stockTransfer->id,
                'reference_no' => $stockTransfer->reference_no,
                'receiver_name' => $stockTransfer->receiver->name,
                'comment' => $stockTransfer->comment,
                'dispatch_date' => $stockTransfer->dispatched_at,
                'driver_name' => $stockTransfer->driver_name,
                'driver_phone' => $stockTransfer->phone_number,
                'inventory_count' => $stockTransfer->inventories->count(),
                'total_quantity' => $stockTransfer->inventories->sum('pivot.quantity'),
                'products' => $stockTransfer->inventories->take(3)->map(function ($inventory, $i) {
                    return [
                        'sn' => $i + 1,
                        'sku' => $inventory->item->sku,
                        'quantity' => $inventory->pivot->quantity,
                    ];
                }),
            ],
        ];
    }
}
