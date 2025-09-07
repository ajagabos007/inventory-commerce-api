<x-mail::message>
# Dear {{$user->name}},

We want to inform you that your password has been successfully updated.

However, if you did not authorize this update, please contact our administrator immediately to ensure the security of your account.


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
