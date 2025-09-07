<x-mail::message>
# Introduction

A new stock transfer has been dispatched to you.

Reference No: {{ $stock_transfer->reference_no }}
Sender: {{ $stock_transfer->sender->name }}<br>
@if(!empty($stock_transfer->comment))
Comment: {{ $stock_transfer->comment }}
@endif

Dispatch Date: {{is_null($stock_transfer->dispatched_at)? $stock_transfer->dispatched_at:  $stock_transfer->dispatched_at->format('M d, Y h:i A') }}
@if(!empty($stock_transfer->driver_name))
Driver Name: {{ $stock_transfer->driver_name }}
@endif
@if(!empty($stock_transfer->phone_number))
Drive Phone Number: {{ $stock_transfer->phone_number }}
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

Please review and receive the stock transfer as soon as possible.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
