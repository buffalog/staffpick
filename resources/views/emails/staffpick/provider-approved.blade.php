<x-mail::message>
# {{ __('You\'ve been approved') }}

{{ __('Great news, :name — you\'ve been approved as a provider with :tenant. A member of our team will be in touch shortly with next steps.', ['name' => $provider->first_name, 'tenant' => $tenant->name]) }}

{{ __('Thank you,') }}<br>
{{ $tenant->name }}
</x-mail::message>
