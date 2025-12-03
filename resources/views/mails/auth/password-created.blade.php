<x-mail::message>
# Hello {{ $user->name }},

Your account has been successfully created, and a default password has been generated for you.
Please find your login details below:

<ul>
    <li><strong>Email:</strong> {{ $user->email }}</li>
    <li><strong>Password:</strong> {{ $password }}</li>
</ul>

@php
$actionText = "Login to Your Account";
$ecommerceUrl = config('app.front_end.ecommerce.url').'/auth/login';
$storeUrl = config('app.front_end.store.url').'/auth/login';

$actionUrl = $user->is_staff ? $storeUrl : $ecommerceUrl;
@endphp

Click the button below to access your dashboard:

<x-mail::button :url="$actionUrl">
    {{ $actionText }}
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}

<x-slot:subcopy>
@lang(
    "If you’re having trouble clicking the \":actionText\" button, copy and paste the URL below into your browser:",
    ['actionText' => $actionText]
)
<br>
<span class="break-all">[{{ $actionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
