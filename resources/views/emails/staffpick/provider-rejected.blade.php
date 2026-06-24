<x-mail::message>
# {{ __('Update on your status') }}

{{ __('Thank you for your interest in working with :tenant. After reviewing your information, we\'re unable to move forward at this time.', ['tenant' => $tenant->name]) }}

**{{ __('Reason') }}:** {{ $reasonLabel }}

{{ __('Thank you,') }}<br>
{{ $tenant->name }}
</x-mail::message>
