<x-mail::message>
# {{ __('We received your referral') }}

{{ __('Thank you for your referral. Our intake team has received it and will be in touch shortly.') }}

{{ __('Please keep this reference number for your records:') }}

<x-mail::panel>
{{ $intake->reference_number }}
</x-mail::panel>

{{ __('Case') }}: {{ trim("{$intake->subject?->first_name} {$intake->subject?->last_name}") }}

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
