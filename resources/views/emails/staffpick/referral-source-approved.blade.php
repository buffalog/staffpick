<x-mail::message>
# {{ __('Your registration has been approved') }}

{{ __('Great news — your registration for :agency has been approved. A member of our team will be in touch shortly with next steps.', ['agency' => $source->name]) }}

{{ __('Thank you,') }}<br>
{{ $tenant->name }}
</x-mail::message>
