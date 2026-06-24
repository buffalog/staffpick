<x-layouts.app class="relative overflow-hidden">
    <x-slot name="title">
        {{ __('StaffPick - Smarter Agency Staffing') }}
    </x-slot>

    <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 -z-10 -right-28 md:-right-48 top-52 md:top-40 rotate-45">

    </div>
    <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 -z-10 -right-28 md:-right-56 top-64 md:top-10 rotate-45">

    </div>

    <x-section.hero class="w-full">

        <div class="mx-auto text-center px-4">
            <span class="text-primary-500 uppercase font-semibold">{{ __('Built for staffing agencies') }}</span>
            <x-heading.h1 class="mt-4 text-primary-800 font-bold flex flex-col items-center justify-center">
                <span class="flex flex-row items-center justify-center">
                    <span>
                        {{ __('The smarter way') }}
                    </span>
                    <span class="text-primary-500 hidden md:block">
                        @svg('diamonds/lightning', 'w-16 h-16')
                    </span>
                </span>
                <span>
                    {{ __('to staff.') }}
                </span>

            </x-heading.h1>

            <p class="m-3">{{ __('StaffPick connects therapy agencies with the right clinicians, fast. Intake, matching, assignments, and dispatch in one place.') }}</p>

            <div class="flex flex-wrap gap-4 justify-center md:flex-row mt-6">
                <x-button-link.primary href="{{route('login')}}" class="self-center !py-3" elementType="a">
                    {{ __('Request a Demo') }}
                </x-button-link.primary>
                <x-button-link.secondary-outline href="#features" class=" self-center !py-3">
                    {{ __('See How It Works') }}
                </x-button-link.secondary-outline>

            </div>

            <x-user-ratings link="#testimonials" class="items-center justify-center mt-6 relative z-40 p-4">
                <x-slot name="avatars">
                    <x-user-ratings.avatar src="https://unsplash.com/photos/rDEOVtE7vOs/download?ixid=M3wxMjA3fDB8MXxzZWFyY2h8Mnx8cGVyc29ufGVufDB8fHx8MTcxMzY4NDI1MHww&force=true&w=640" alt="testimonial 1"/>
                    <x-user-ratings.avatar src="https://unsplash.com/photos/c_GmwfHBDzk/download?ixid=M3wxMjA3fDB8MXxzZWFyY2h8M3x8cGVyc29ufGVufDB8fHx8MTcxMzY4NDI1MHww&force=true&w=640" alt="testimonial 2"/>
                    <x-user-ratings.avatar src="https://unsplash.com/photos/QXevDflbl8A/download?ixid=M3wxMjA3fDB8MXxzZWFyY2h8NHx8cGVyc29ufGVufDB8fHx8MTcxMzY4NDI1MHww&force=true&w=640" alt="testimonial 3"/>
                    <x-user-ratings.avatar src="https://unsplash.com/photos/mjRwhvqEC0U/download?ixid=M3wxMjA3fDB8MXxzZWFyY2h8Nnx8cGVyc29ufGVufDB8fHx8MTcxMzY4NDI1MHww&force=true&w=640" alt="testimonial 4"/>
                    <x-user-ratings.avatar src="https://unsplash.com/photos/C8Ta0gwPbQg/download?ixid=M3wxMjA3fDB8MXxzZWFyY2h8MTl8fHBlcnNvbnxlbnwwfHx8fDE3MTM2ODQyNTB8MA&force=true&w=640" alt="testimonial 5"/>
                </x-slot>

                {{ __('Trusted by staffing teams placing clinicians every day.') }}
            </x-user-ratings>

            <div class="mx-auto md:max-w-3xl lg:max-w-5xl text-center p-4">
                <img class="drop-shadow-2xl mt-8 transition hover:scale-101 rounded-2xl" src="{{URL::asset('/images/diamonds/features/hero-image.png')}}" />
            </div>

        </div>
    </x-section.hero>

    <x-section.columns class="max-w-none md:max-w-6xl mt-16" >
        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500 uppercase!">
                    {{ __('End-to-end intake') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Multi-Channel Intake.') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('Capture referrals from any source into a single, structured intake queue. Every request is tracked, timestamped, and ready for your team to act on.') }}
            </p>

            <p class="mt-4">
                {{ __('No more spreadsheets, lost emails, or missed referrals. StaffPick keeps every intake organized from the moment it arrives.') }}
            </p>
        </x-section.column>

        <x-section.column>
            <img src="{{URL::asset('/images/diamonds/features/plans.png')}}" class="rounded-2xl"/>
        </x-section.column>

    </x-section.columns>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6 flex-wrap-reverse">
        <x-section.column >
            <img src="{{URL::asset('/images/diamonds/features/checkout.png')}}" class="rounded-2xl" />
        </x-section.column>

        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500 uppercase!">
                    {{ __('Buttery smooth') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Beautiful checkout process.') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('In a few clicks, your customers can subscribe to your service using a beautiful checkout page that shows all the details of the plan they are subscribing to, allowing them to add a coupon code if they have one, and choose their payment method.') }}
            </p>
        </x-section.column>

    </x-section.columns>

    <x-section.block class="mt-32 bg-primary-950 relative overflow-hidden">

        <div class="bg-primary-50  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 -right-24 md:-right-56 top-22 md:top-32 rotate-45">

        </div>
        <div class="bg-primary-50  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 z-0 -right-24 md:-right-56 top-32 md:top-10 rotate-45">

        </div>

        <x-section.columns id="features" class="mt-8">
            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-primary-200 uppercase!">
                        {{ __('Stop guessing, start matching') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-white">
                        {{ __('Intelligent Matching.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-50/75">
                    <p class="mt-4">
                        {{ __('StaffPick surfaces the right clinicians for each case based on discipline, availability, location, and tier. Your team spends time placing, not searching.') }}
                    </p>
                    <p class="mt-4">
                        {{ __('Matched candidates are surfaced in seconds. Dispatch offers directly from the match results, no extra steps.') }}
                    </p>
                </div>
            </x-section.column>

            <x-section.column class="flex items-center justify-center">
                @svg('diamonds/money-bag', 'h-60 w-60 md:h-80 md:w-80 text-primary-200 relative hover:scale-105 transition-all duration-300')
            </x-section.column>

        </x-section.columns>
        <x-section.columns class="max-w-none md:max-w-6xl mt-12  flex-wrap-reverse">
            <x-section.column class="flex items-center justify-center">
                @svg('diamonds/brush', 'h-48 w-48 md:h-60 md:w-60 text-primary-200 relative hover:scale-105 transition-all duration-300')
            </x-section.column>

            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-primary-200 uppercase!">
                        {{ __('From offer to placement') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-white">
                        {{ __('Assignment Pipeline.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-50/75">
                    <p class="mt-4">
                        {{ __('Track every placement through a clear, configurable pipeline. From offer dispatched to clinician accepted to case assigned, nothing falls through the cracks.') }}
                    </p>

                    <p class="mt-4">
                        {{ __('Your team always knows where each case stands. No chasing down status updates.') }}
                    </p>
                </div>
            </x-section.column>

        </x-section.columns>


    </x-section.block>

    <div class="text-center mt-24 mx-4" id="tech-stack">
        <x-heading.h6 class="text-primary-500 uppercase!">
            {{ __('Works the way you work') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('Configurable for Your Agency') }}
        </x-heading.h2>
    </div>


    <div class="text-center p-4 mx-auto">
        <p>{{ __('Every agency runs differently. StaffPick is built to flex around your workflows, not the other way around.') }}</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10 text-left max-w-4xl mx-auto">
            <div class="p-6 rounded-2xl bg-primary-50 border border-primary-100">
                <p class="font-semibold text-primary-900">{{ __('Multi-tenant by design') }}</p>
                <p class="mt-2 text-sm text-primary-700">{{ __('Each agency gets its own isolated environment. Your data stays yours.') }}</p>
            </div>
            <div class="p-6 rounded-2xl bg-primary-50 border border-primary-100">
                <p class="font-semibold text-primary-900">{{ __('Discipline-aware') }}</p>
                <p class="mt-2 text-sm text-primary-700">{{ __('Configure disciplines, tiers, service areas, and intake fields to match exactly how your agency operates.') }}</p>
            </div>
            <div class="p-6 rounded-2xl bg-primary-50 border border-primary-100">
                <p class="font-semibold text-primary-900">{{ __('Role-based access') }}</p>
                <p class="mt-2 text-sm text-primary-700">{{ __('Staff, coordinators, and admins each see exactly what they need and nothing they don\'t.') }}</p>
            </div>
        </div>

    </div>

    <x-section.block class="mt-32 bg-secondary-100/25 relative overflow-hidden">
        <div class="bg-secondary-900  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-5 z-0 -right-16 -bottom-16 md:-right-56 md:-bottom-10 rotate-45">

        </div>
        <div class="bg-secondary-900  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 z-0 -right-16 -bottom-0 md:-right-56 md:-bottom-32 rotate-45">

        </div>

        <x-section.columns class="mt-8">
            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-secondary-700 uppercase!">
                        {{ __('At a glance, always') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-950">
                        {{ __('Dispatch Board.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-950/75">
                    <p class="mt-4">
                        {{ __('See every open case, active offer, and pending assignment in one live view. Know exactly what needs attention before it becomes a problem.') }}
                    </p>
                </div>
            </x-section.column>

            <x-section.column>
                <img src="{{URL::asset('/images/diamonds/features/stats.png')}}" dir="right" class="relative z-10 hover:scale-105 transition-all duration-300">
            </x-section.column>

        </x-section.columns>
        <x-section.columns class="max-w-none md:max-w-6xl mt-12  flex-wrap-reverse">
            <x-section.column >
                <img src="{{URL::asset('/images/diamonds/features/blog.png')}}" class="relative z-10 hover:scale-105 transition-all duration-300" />
            </x-section.column>

            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-secondary-700 uppercase!">
                        {{ __('Stay audit-ready') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-950">
                        {{ __('Credentialing & Compliance.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-950/75">
                    <p class="mt-4">
                        {{ __('Customize the primary & secondary colors of your website, error pages, email templates, fonts, social sharing cards, favicons, and more.') }}
                    </p>

                    <p class="mt-4">
                        {{ __('Based on the popular TailwindCSS, you can easily customize the look and feel of your SaaS application.') }}
                    </p>
                </div>
            </x-section.column>

        </x-section.columns>
    </x-section.block>


    <div class="text-center mt-24 px-4" x-intersect="$el.classList.add('slide-in-top')">
        <x-heading.h6 class="text-primary-500 uppercase!">
            {{ __('Everything in one place') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('A Purpose-Built Staff Dashboard.') }}
        </x-heading.h2>
    </div>

    <p class="text-center py-4">{{ __('Your coordinators get a clean, focused workspace built around how staffing actually works.') }}</p>

    <div class="text-center pt-6 mx-auto max-w-5xl ">
        <img src="{{URL::asset('/images/diamonds/features/admin-panel.png')}}" >
    </div>

    <x-section.block class="mt-24 relative overflow-hidden">

        <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 z-0 -right-24 md:-right-56 top-22 md:top-32 rotate-45">

        </div>
        <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 z-0 -right-24 md:-right-56 top-32 md:top-10 rotate-45">

        </div>

        <x-section.columns class="max-w-none md:max-w-6xl pt-8">
            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-primary-500 uppercase!">
                        {{ __('Less back-and-forth') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-900">
                        {{ __('Provider Self-Serve Onboarding.') }}
                    </x-heading.h2>
                </div>

                <p class="mt-4">
                    {{ __('Providers complete their own intake, upload credentials, and confirm availability without your team chasing them down. StaffPick handles the collection so you can focus on the placement.') }}
                </p>
                <p class="mt-4">
                    {{ __('Automated notifications keep providers informed at every step, from offer received to assignment confirmed.') }}
                </p>
            </x-section.column>

            <x-section.column>
                <img src="{{URL::asset('/images/diamonds/features/email.png')}}" class="relative z-10 hover:scale-105 transition-all duration-300"  />
            </x-section.column>

        </x-section.columns>

        <x-section.columns class="max-w-none md:max-w-6xl pt-8 flex-wrap-reverse">

            <x-section.column>
                <img src="{{URL::asset('/images/diamonds/features/login.png')}}" class="relative z-10 hover:scale-105 transition-all duration-300"  />
            </x-section.column>

            <x-section.column>
                <div x-intersect="$el.classList.add('slide-in-top')">
                    <x-heading.h6 class="text-primary-500 uppercase!">
                        {{ __('Secure from day one') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-900">
                        {{ __('Secure Multi-Tenant Access.') }}
                    </x-heading.h2>
                </div>

                <p class="mt-4">
                    {{ __('Each agency operates in its own isolated environment. Staff see only their agency\'s data. Providers see only what\'s relevant to them. No data bleeds across tenants.') }}
                </p>

                <p class="pt-4">
                    {{ __('Built-in role-based access means the right people see the right things, always.') }}
            </x-section.column>

        </x-section.columns>
    </x-section.block>


    <div class="text-center mt-24" x-intersect="$el.classList.add('slide-in-top')">
        <x-heading.h6 class="text-primary-500 uppercase!">
            {{ __('Oh, we\'re not done yet') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('Everything you need to run placements') }}
        </x-heading.h2>
    </div>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="users" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Clinician Profiles') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Every provider gets a full profile: disciplines, credentials, availability, tier, and placement history in one place.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="translatable" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Offer Tracking') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('See exactly where every dispatched offer stands. Accepted, pending, declined, no response — tracked in real time.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="seo" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Case Management') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Manage the full lifecycle from referral to active case. Status, notes, and assignment history attached to every record.') }}</p>
        </x-section.column>

    </x-section.columns>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="user-dashboard" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Provider Portal') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Providers log in to view offers, confirm availability, and upload documents without ever calling your office.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="tool" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Automated Notifications') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Providers and staff get notified at every key step. No one is left wondering what\'s happening.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="development" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Audit Trail') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Every action, status change, and assignment is logged. Always know what happened, when, and who did it.') }}</p>
        </x-section.column>

    </x-section.columns>


    <div class="mx-4 mt=16">
        <x-heading.h6 class="text-center mt-24 text-primary-500 uppercase!" id="pricing">
            {{ __('Ready to modernize your staffing ops?') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900 text-center">
            {{ __('See StaffPick in Action') }}
        </x-heading.h2>
    </div>

    <div class="text-center mt-8 mb-16 px-4">
        <p class="text-primary-700 max-w-xl mx-auto">{{ __('Get a personalized walkthrough with your agency\'s real workflow in mind. No canned demos, no pressure.') }}</p>
        <div class="mt-8">
            <x-button-link.primary href="{{route('login')}}" class="!py-3 !px-8">
                {{ __('Request a Demo') }}
            </x-button-link.primary>
        </div>
    </div>

    <div class="text-center mt-24 mx-4" id="faq">
        <x-heading.h6 class="text-primary-500">
            {{ __('FAQ') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('Got a Question?') }}
        </x-heading.h2>
        <p>{{ __('Here are the most common questions to help you with your decision.') }}</p>
    </div>

    <div class="max-w-none md:max-w-6xl mx-auto">
        <x-accordion class="mt-4 p-8">
            <x-accordion.item active="true" name="faqs">
                <x-slot name="title">{{ __('What is StaffPick?') }}</x-slot>
                <p>{{ __('StaffPick is a multi-tenant staffing platform built for therapy and healthcare agencies. It manages the full placement lifecycle: intake, matching, offers, assignments, and compliance tracking, in one place.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Who is StaffPick for?') }}</x-slot>
                <p>{{ __('StaffPick is built for agencies that place clinicians, including physical therapists, occupational therapists, speech-language pathologists, and other healthcare providers. If your team is managing placements in spreadsheets or an outdated system, StaffPick was built for you.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('How does matching work?') }}</x-slot>
                <p>{{ __('StaffPick matches open intake requests to available clinicians based on discipline, service area, availability, and tier. Your coordinators get a ranked list of candidates they can review and dispatch offers to directly from the platform.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Can providers use StaffPick directly?') }}</x-slot>
                <p>{{ __('Yes. Providers get their own portal where they can view offers, confirm availability, upload credentials, and track their active cases. They never need to call your office to get basic status updates.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Is my agency\'s data kept separate from other agencies?') }}</x-slot>
                <p>{{ __('Completely. StaffPick is built multi-tenant from the ground up. Each agency operates in its own isolated environment. Your data never mingles with another agency\'s data.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('How do I get started?') }}</x-slot>
                <p>{{ __('Request a demo and we\'ll walk you through the platform with your agency\'s workflow in mind. We\'ll configure your disciplines, service areas, and intake fields before you go live.') }}</p>
            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Do you offer support?') }}</x-slot>
                <p>{{ __('Yes. Reach us at') }} <a href="mailto:{{config('app.support_email')}}" class="text-primary-500 hover:underline">{{config('app.support_email')}}</a>{{ __('. We\'re a small team and we actually respond.') }}</p>
            </x-accordion.item>
        </x-accordion>


        <div class="text-center">
            <x-section.outro>
                <x-heading.h6 class="text-primary-50">
                    {{ __('Less chasing. More placing.') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-50 drop-shadow-4xl">
                    {{ __('Start Placing Smarter') }}
                </x-heading.h2>

                <p class="text-primary-100 mt-2">
                    {{ __('StaffPick gives your team the tools to move faster, stay organized, and keep clinicians and clients in the loop without the manual grind.') }}
                </p>

                <div class="mt-12">
                    <x-button-link.secondary href="{{route('login')}}" >
                        {{ __('Request a Demo') }}
                    </x-button-link.secondary>
                </div>
            </x-section.outro>
        </div>
    </div>

</x-layouts.app>
