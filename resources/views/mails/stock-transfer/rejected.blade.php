<x-mail::message>
# Stock Transfer Rejected
Hello {{ $stock_transfer->sender->name }},

Your stock transfer was rejected by the receiver.

Reference No: {{ $stock_transfer->reference_no }}<br>
Dispatch Date: {{ \Carbon\Carbon::parse($stock_transfer->dispatched_at)->format('M d, Y h:i A') }}<br>

Rejected By: {{ $stock_transfer->receiver->name }}<br>
Rejection Date: {{ \Carbon\Carbon::parse($stock_transfer->rejected_at)->format('M d, Y h:i A') }}
@if(!empty($stock_transfer->rejection_reason))
Reason: {{ $stock_transfer->rejection_reason }}
@endif

<x-mail::table>
|Sn.| Product SKU | Quantity |
| :-------- | :-------- | --------: |
@foreach($stock_transfer->inventories as $inventory)
@if($loop->index < 3)
|{{$loop->index+1}}| {!! $inventory->item->sku !!} | {{ $inventory->pivot->quantity }} |
@else
| . | . |
| . | . |
@break
@endif
@endforeach
| **Total** {{$stock_transfer->inventories->count()}}|| **{{ $stock_transfer->inventories->sum('pivot.quantity') }}** |
</x-mail::table>

The stock transfer was rejected and will not be processed further.
Please review the details and take necessary actions.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
