<x-mail::message>
# Hello {{$user->full_name}}
{{$body}}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
