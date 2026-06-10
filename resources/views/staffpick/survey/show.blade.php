<x-staffpick.survey-shell :title="__('Rate your visit')">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h1 class="text-xl font-semibold">{{ __('How was your visit?') }}</h1>
        @if ($survey->provider)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Your feedback about :provider helps us improve care.', ['provider' => trim("{$survey->provider->first_name} {$survey->provider->last_name}")]) }}
            </p>
        @endif

        <form method="POST" action="{{ route('survey.submit', ['token' => $survey->token]) }}" class="mt-6 space-y-6">
            @csrf

            <fieldset>
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Your rating') }}</legend>
                <div class="mt-2 flex gap-2">
                    @foreach (range(1, 5) as $n)
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="rating" value="{{ $n }}" class="peer sr-only" @checked(old('rating') == $n) required>
                            <span class="flex h-12 items-center justify-center rounded-lg border border-gray-300 text-lg font-semibold text-gray-700 peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white dark:border-gray-600 dark:text-gray-200">
                                {{ $n }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-400">
                    <span>{{ __('Poor') }}</span>
                    <span>{{ __('Excellent') }}</span>
                </div>
                @error('rating')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <label for="comment" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Comments (optional)') }}</label>
                <textarea id="comment" name="comment" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">{{ old('comment') }}</textarea>
                @error('comment')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 font-medium text-white hover:bg-primary-500">
                {{ __('Submit rating') }}
            </button>
        </form>
    </div>
</x-staffpick.survey-shell>
