@props(['html'])

{{-- Renders help Markdown (already converted to HTML) with print-free, Tailwind-only prose styling. --}}
<div
    {{ $attributes->class([
        'max-w-none text-gray-700 dark:text-gray-300',
        '[&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-gray-900 dark:[&_h1]:text-white',
        '[&_h2]:mb-2 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 dark:[&_h2]:text-white',
        '[&_h3]:mb-1 [&_h3]:mt-4 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-gray-800 dark:[&_h3]:text-gray-100',
        '[&_p]:my-3 [&_p]:leading-relaxed',
        '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-1',
        '[&_a]:text-primary-600 [&_a]:underline dark:[&_a]:text-primary-400',
        '[&_strong]:font-semibold [&_strong]:text-gray-900 dark:[&_strong]:text-white',
        '[&_code]:rounded [&_code]:bg-gray-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm dark:[&_code]:bg-white/10',
        '[&_blockquote]:my-4 [&_blockquote]:border-l-4 [&_blockquote]:border-primary-300 [&_blockquote]:pl-4 [&_blockquote]:text-gray-600 dark:[&_blockquote]:text-gray-400',
        '[&_table]:my-4 [&_table]:w-full [&_table]:border-collapse',
        '[&_th]:border [&_th]:border-gray-200 [&_th]:bg-gray-50 [&_th]:px-3 [&_th]:py-1.5 [&_th]:text-left [&_th]:text-sm dark:[&_th]:border-white/10 dark:[&_th]:bg-white/5',
        '[&_td]:border [&_td]:border-gray-200 [&_td]:px-3 [&_td]:py-1.5 [&_td]:text-sm dark:[&_td]:border-white/10',
    ]) }}
>
    {!! $html !!}
</div>
