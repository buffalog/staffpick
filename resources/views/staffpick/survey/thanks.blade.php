<x-staffpick.survey-shell :title="__('Thank you')">
    <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-500/10 dark:text-green-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>
        <h1 class="mt-4 text-xl font-semibold">{{ __('Thank you!') }}</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            @if ($alreadyResponded)
                {{ __('This survey has already been completed. We appreciate your feedback.') }}
            @else
                {{ __('Your rating has been recorded. We appreciate your feedback.') }}
            @endif
        </p>
    </div>
</x-staffpick.survey-shell>
