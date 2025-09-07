<x-mail::message>
# Stock Transfer Received
Hello {{ $stock_transfer->sender->name }},

Your stock transfer has been successfully received.

Reference No: {{ $stock_transfer->reference_no }}<br>
Dispatch Date: {{ \Carbon\Carbon::parse($stock_transfer->dispatched_at)->format('M d, Y h:i A') }}<br>

Received By: {{ $stock_transfer->receiver->name }}<br>
Received Date: {{ \Carbon\Carbon::parse($stock_transfer->accepted_at)->format('M d, Y h:i A') }}

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


Stock transfer successfully received and recorded.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
