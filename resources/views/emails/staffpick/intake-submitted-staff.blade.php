<x-mail::message>
# {{ __('New intake request') }}

{{ __('A referral source has submitted a new intake request for review.') }}

- **{{ __('Reference') }}:** {{ $intake->reference_number }}
- **{{ __('Referral source') }}:** {{ $intake->referralSource?->name }}
@if ($intake->discipline)
- **{{ __('Discipline') }}:** {{ $intake->discipline->name }}
@endif

{{ __('Open your dashboard to acknowledge and process this intake.') }}

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
