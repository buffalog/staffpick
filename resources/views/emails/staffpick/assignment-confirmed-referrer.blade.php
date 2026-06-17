<x-mail::message>
# {{ __('Your referral has been assigned') }}

{{ __('Good news — a clinician has accepted your referral and it is now being scheduled.') }}

- **{{ __('Reference') }}:** {{ $intake->reference_number }}
@if ($intake->discipline)
- **{{ __('Discipline') }}:** {{ $intake->discipline->name }}
@endif

{{ __('Our team will be in touch with next steps.') }}

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
