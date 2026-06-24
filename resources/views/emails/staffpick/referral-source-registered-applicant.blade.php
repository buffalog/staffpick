<x-mail::message>
# {{ __('Thanks for registering') }}

{{ __('We received your registration for :agency. Our team will review it and be in touch shortly.', ['agency' => $source->name]) }}

{{ __('Thank you,') }}<br>
{{ $tenant->name }}
</x-mail::message>
