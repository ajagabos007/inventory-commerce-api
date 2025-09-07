<x-mail::message>

# Dear {{$user->name}}
A default password has been automatically generated for your new account. Below are your login credentials:
<ul>
    <li><strong>Email: </strong> {{$user->email}}</li>
    <li><strong>Password: </strong> {{$password}}</li>
</ul>

@php 
    $action_text ="Login";
    $action_url = config('app.front_end.url').'/auth/login'
@endphp

You can access your account by click the  "{{$action_text}}" button below

<x-mail::button :url="$action_url">
    {{$action_text}}
</x-mail::button> 

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
    @lang(
        "If you're having trouble clicking the \"$action_text\" button, copy and paste the URL below\n".
        'into your web browser:',
        [
            'actionText' => $action_text,
        ]
) <span class="break-all">[{{ $action_url }}]({{ $action_url }})</span>
</x-slot:subcopy>

</x-mail::message>