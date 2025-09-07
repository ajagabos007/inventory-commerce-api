<x-mail::message>
# Hello {{$user->full_name}}

We hope this message finds you well. This is to notify you that your account has been deactivated as of <b>{{now()}}</b>. This action was taken due to violation of terms.

If you believe this was done in error or wish to reactivate your account, please contact us at <a href="mailto:{{config('mail.from.address')}}">{{config('mail.from.address')}}</a>
Thank you for your understanding.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
