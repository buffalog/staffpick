<x-mail::message>
# Application update

Thank you for your interest in joining **{{ $tenantName }}**.

After review, your application was not approved at this time.

@if ($reason)
> {{ $reason }}
@endif

You're welcome to reach out to {{ $tenantName }} with any questions.

Thanks,<br>
{{ $tenantName }}
</x-mail::message>
