<x-mail::message>
# Hello {{$user->full_name}}

We are pleased to inform you that your account has been successfully reactivated as of <b>{{now()}}</b>.. You can now access your account and continue using our services without interruption.

If you experience any issues or have further questions, please do not hesitate to contact us at <a href="mailto:{{config('mail.from.address')}}">{{config('mail.from.address')}}</a>

Thank you for being a valued member of our community.


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
