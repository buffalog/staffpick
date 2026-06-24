<x-mail::message>
# {{ __('Update on your registration') }}

{{ __('Thank you for your interest in registering with :tenant. After reviewing your submission for :agency, we\'re unable to move forward at this time.', ['tenant' => $tenant->name, 'agency' => $source->name]) }}

**{{ __('Reason') }}:** {{ $reasonLabel }}

{{ __('Thank you,') }}<br>
{{ $tenant->name }}
</x-mail::message>
