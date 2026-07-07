<div class="space-y-4">
    {{-- Existing attachments, newest first. Deleted (tombstoned) files are excluded — they
         persist in the database for audit but have no content to view or download. --}}
    <ul role="list" class="divide-y divide-gray-200 dark:divide-white/10">
        @forelse ($attachments as $attachment)
            <li
                x-data="{ preview: false }"
                wire:key="attachment-{{ $attachment->id }}"
                class="py-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $attachment->original_filename }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $attachment->uploaded_at?->format(config('app.datetime_format')) }}
                            @if ($attachment->uploadedBy)
                                · {{ __('by') }} {{ $attachment->uploadedBy->name }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-none items-center gap-3 text-sm">
                        @if ($attachment->isInlinePreviewable())
                            {{-- Images and PDFs preview inline (native browser rendering); no new tab. --}}
                            <button type="button" x-on:click="preview = ! preview" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('View') }}</button>
                        @else
                            {{-- DOC/DOCX have no in-browser preview — open the raw file in a new tab and
                                 let the browser/OS handle the Word document. Intended behavior. --}}
                            <a href="{{ route('staffpick.credential-attachments.view', $attachment) }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('View') }}</a>
                        @endif

                        <a href="{{ route('staffpick.credential-attachments.download', $attachment) }}" class="font-medium text-gray-700 hover:underline dark:text-gray-300">{{ __('Download') }}</a>

                        {{-- Delete renders ONLY for sp_admin / super-admin. For every other role it is
                             absent from the DOM entirely (server-side @if), not merely hidden. --}}
                        @if ($canDelete)
                            <button
                                type="button"
                                wire:click="delete({{ $attachment->id }})"
                                wire:confirm="{{ __('Delete this document? The file is removed but a record that it existed is kept.') }}"
                                class="font-medium text-danger-600 hover:underline dark:text-danger-400"
                            >{{ __('Delete') }}</button>
                        @endif
                    </div>
                </div>

                @if ($attachment->isInlinePreviewable())
                    <div x-show="preview" x-cloak class="mt-3">
                        @if ($attachment->isPdf())
                            <embed src="{{ route('staffpick.credential-attachments.view', $attachment) }}" type="application/pdf" class="h-96 w-full rounded-lg border border-gray-200 dark:border-white/10" />
                        @else
                            <img src="{{ route('staffpick.credential-attachments.view', $attachment) }}" alt="{{ $attachment->original_filename }}" class="max-h-96 rounded-lg border border-gray-200 dark:border-white/10" />
                        @endif
                    </div>
                @endif
            </li>
        @empty
            <li class="py-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No attachments yet.') }}</li>
        @endforelse
    </ul>

    {{-- Upload a new proof file. Never overwrites older files — each upload is a new row. --}}
    <div class="border-t border-gray-200 pt-4 dark:border-white/10">
        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Add attachment') }}</label>
        <input
            type="file"
            wire:model="upload"
            accept=".pdf,.jpg,.jpeg,.png,.heic,.docx,.doc"
            class="mt-2 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-gray-200 dark:text-gray-300 dark:file:bg-white/10 dark:hover:file:bg-white/20"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('PDF, JPG, PNG, HEIC, DOC, or DOCX. Max 20 MB.') }}</p>

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
            >{{ __('Upload') }}</button>
            <span wire:loading wire:target="upload, save" class="text-xs text-gray-500 dark:text-gray-400">{{ __('Uploading…') }}</span>
        </div>
    </div>
</div>
