<x-mail::message>
    # Product Inventory Export

    Your product inventory export has been generated successfully!

    ## Export Summary

    - **Export Date:** {{ $exportDate }}
    - **Total Products:** {{ $totalProducts }}
    - **Total Records:** {{ $totalRecords }}
    - **Stores Included:** {{ count($stores) }}

    @if(count($stores) > 0)
        ### Stores
        @foreach($stores as $storeId => $storeName)
            - {{ $storeName }}
        @endforeach
    @endif

    ## File Details

    - **Filename:** {{ $filename }}
    - **Format:** {{ $isZip ? 'ZIP Archive' : 'CSV' }}

    The export file is attached to this email. You can also download it from the exports directory on the server.

    ---

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>