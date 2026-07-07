<div class="space-y-4">
    <div class="flex items-center gap-4">
        {{-- Current photo, or the discipline-colored initials fallback. --}}
        <x-provider-avatar :provider="$this->provider" :size="96" wire:key="avatar-{{ $this->provider->updated_at?->timestamp }}" />

        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('JPG, PNG, or HEIC. Max 5 MB.') }}
        </div>
    </div>

    <div>
        <input
            type="file"
            wire:model="upload"
            accept=".jpg,.jpeg,.png,.heic"
            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-gray-200 dark:text-gray-300 dark:file:bg-white/10 dark:hover:file:bg-white/20"
        />

        @error('upload')
            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        <div class="mt-3 flex items-center gap-3">
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="upload, save"
                class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-50"
            >{{ __('Upload photo') }}</button>
            <span wire:loading wire:target="upload, save" class="text-xs text-gray-500 dark:text-gray-400">{{ __('Uploading…') }}</span>
        </div>
    </div>
</div>
