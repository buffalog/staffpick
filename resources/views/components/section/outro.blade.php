<div {{ $attributes->merge(['class' => 'mx-auto max-w-none md:max-w-6xl p-4']) }}>
    <div class="bg-primary-50 rounded-3xl relative my-10 bg-linear-to-r from-primary-600 to-primary-300 mx-auto py-16 px-8 md:px-4 overflow-hidden">
        <div class="bg-secondary-300  w-48 h-64 rounded-3xl absolute opacity-10 -z-0 -right-28 md:top-24 rotate-45">

        </div>
        <div class="bg-secondary-300  w-48 h-64 rounded-3xl absolute opacity-20 -z-0 -right-32 -bottom-10 md:top-0 rotate-45">

        </div>

        <div class="relative z-10">
            {{ $slot }}
        </div>
    </div>
</div>
