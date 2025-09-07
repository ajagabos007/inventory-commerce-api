<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockTransferDispatchedNotification extends Notification
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
        return ['database'];
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

        return (new MailMessage)->markdown('mails.stock-transfer.dispatched', [
            'stock_transfer' => $this->stock_transfer,
        ])
            ->subject(__('Incoming Stock Transfer'));
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
            'type' => 'stock_transfer_dispatched',
            'title' => 'Incoming Stock Transfer',
            'message' => sprintf(
                'Reference No: %s | Sender: %s%s | Dispatch Date: %s',
                $stockTransfer->reference_no,
                $stockTransfer->sender->name,
                $stockTransfer->comment ? " | Comment: {$stockTransfer->comment}" : '',
                (empty($stockTransfer->dispatched_at) ? $stockTransfer->dispatched_at : Carbon::parse($stockTransfer->dispatched_at)->format('M d, Y h:i A'))
            ),
            'icon' => 'truck', // e.g. for frontend badge/icon use
            'level' => NotificationLevel::INFO->value,
            'action_url' => route('api.stock-transfers.show', $stockTransfer->id),
            'meta' => [
                'id' => $stockTransfer->id,
                'reference_no' => $stockTransfer->reference_no,
                'sender_name' => $stockTransfer->sender->name,
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
