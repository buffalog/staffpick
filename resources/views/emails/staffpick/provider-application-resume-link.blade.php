<x-mail::message>
# Continue your application

Thanks for starting your provider application with **{{ $tenantName }}**.

Your progress is saved. Use the button below to pick up where you left off — the link is unique to you, so keep it handy.

<x-mail::button :url="$resumeUrl">
Continue application
</x-mail::button>

If you didn't start this application, you can safely ignore this email.

Thanks,<br>
{{ $tenantName }}
</x-mail::message>
