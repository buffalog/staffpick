<x-layouts.app class="relative overflow-hidden">
    <x-slot name="title">
        {{ __('SaaSykit - Laravel SaaS Starter Kit') }}
    </x-slot>

    <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 -z-10 -right-28 md:-right-48 top-52 md:top-40 rotate-45">

    </div>
    <div class="bg-primary-300  w-40 h-40 md:w-96 md:h-96 rounded-3xl absolute opacity-10 -z-10 -right-28 md:-right-56 top-64 md:top-10 rotate-45">

    </div>

    <x-section.hero class="w-full">

        <div class="mx-auto text-center px-4">
            <span class="text-primary-500 uppercase font-semibold">{{ __('Launch your Business') }}</span>
            <x-heading.h1 class="mt-4 text-primary-800 font-bold flex flex-col items-center justify-center">
                <span class="flex flex-row items-center justify-center">
                    <span>
                        {{ __('Build your SaaS') }}
                    </span>
                    <span class="text-primary-500 hidden md:block">
                        @svg('diamonds/lightning', 'w-16 h-16')
                    </span>
                </span>
                <span>
                    {{ __('with SaaSykit') }}
                </span>

            </x-heading.h1>

            <p class="m-3">{{ __('A Laravel-based boilerplate with everything you need to build an awesome SaaS.') }}</p>

            <div class="flex flex-wrap gap-4 justify-center md:flex-row mt-6">
                <x-button-link.primary href="#pricing" class="self-center !py-3" elementType="a">
                    {{ __('Get SaaSykit') }}
                </x-button-link.primary>
                <x-button-link.secondary-outline href="//demo.saasykit.com" class=" self-center !py-3" rel=”nofollow” >
                    {{ __('Check The Demo') }}
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

                {{ __('Join the best SaaS developers who are using SaaSykit to build their SaaS.') }}
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
                    {{ __('At your fingertips') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Products, Plans & Pricing.') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('Create and manage your products, plans, and pricing, set features for each plan, mark a plan as featured, and more.') }}
            </p>

            <p class="mt-4">
                {{ __('Rewards your customers with discounts and manage all that from a beautiful admin panel.') }}
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
                        {{ __('a solid SaaS') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-white">
                        {{ __('Subscriptions & One-time purchases.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-50/75">
                    <p class="mt-4">
                        {{ __('Easily offer your customers subscription-based & one-time purchase products with SaaSykit. All the webhook handling, subscription management, and billing are already set up for you in a beautiful and easy-to-use admin panel.') }}
                    </p>
                    <p class="mt-4">
                        {{ __('Collect payments with Stripe and Paddle, and manage your customers with ease.') }}
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
                        {{ __('Your Brand, Your Colors') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-white">
                        {{ __('Customize Everything.') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-50/75">
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

    <div class="text-center mt-24 mx-4" id="tech-stack">
        <x-heading.h6 class="text-primary-500 uppercase!">
            {{ __('The best of the best') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('A solid tech stack') }}
        </x-heading.h2>
    </div>


    <div class="text-center p-4 mx-auto">
        <p >{{ __('Laravel, TailwindCSS, Livewire, AlpineJS & FilamentPhp') }}</p>

        <div class="flex flex-wrap items-center justify-center gap-12 mt-8">
            <img src="{{URL::asset('/images/diamonds/tech-stack/laravel.svg')}}" class="h-6 md:h-8 hover:cursor-pointer hover:scale-103 hover:opacity-100 transition grayscale hover:grayscale-0 opacity-50" />
            <img src="{{URL::asset('/images/diamonds/tech-stack/filament.avif')}}" class="h-6 md:h-8 hover:cursor-pointer hover:scale-103 hover:opacity-100 transition grayscale hover:grayscale-0 opacity-50" />
            <img src="{{URL::asset('/images/diamonds/tech-stack/tailwindcss.svg')}}" class="h-6 md:h-8 hover:cursor-pointer hover:scale-103 hover:opacity-100 transition grayscale hover:grayscale-0 opacity-50" />
            <img src="{{URL::asset('/images/diamonds/tech-stack/livewire.png')}}" class="h-12 md:h-16 hover:cursor-pointer hover:scale-103 hover:opacity-100 transition grayscale hover:grayscale-0 opacity-50" />
            <img src="{{URL::asset('/images/diamonds/tech-stack/alpinejs.svg')}}" class="h-8 md:h-10 hover:cursor-pointer hover:scale-103 hover:opacity-100 transition grayscale hover:grayscale-0 opacity-50" />
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
                        {{ __('SaaS Stats') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-950">
                        {{ __('Know your numbers') }}
                    </x-heading.h2>
                </div>

                <div class="text-primary-950/75">
                    <p class="mt-4">
                        {{ __('View your MRR (monthly recurring revenue), Churn rates, ARPU (average revenue per user), and other SaaS metrics right inside your admin panel.') }}
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
                        {{ __('Content is king') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-950">
                        {{ __('A ready Blog.') }}
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
            {{ __('Can\'t get more beautiful') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('A stunning Admin Panel.') }}
        </x-heading.h2>
    </div>

    <p class="text-center py-4">{{ __('Manage your SaaS application from a beautiful admin panel powered by Filament') }}</p>

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
                        {{ __('Connect with customers') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-900">
                        {{ __('Send & Customize Emails.') }}
                    </x-heading.h2>
                </div>

                <p class="mt-4">
                    {{ __('Choose your preferred email service from options like Mailgun, Postmark, and Amazon SES to communicate with your customers.') }}
                </p>
                <p class="mt-4">
                    {{ __('SaaSykit comes with a beautiful email template out of the box that takes your brand colors into consideration, along with the typical emails for customer registration, verification, resetting password, etc set up for you.') }}
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
                        {{ __('Modern Authentication') }}
                    </x-heading.h6>
                    <x-heading.h2 class="text-primary-900">
                        {{ __('Login, Registration & Social login.') }}
                    </x-heading.h2>
                </div>

                <p class="mt-4">
                    {{ __('SaaSykit includes built-in user authentication, supporting both traditional email/password authentication and social login options such as Google, Facebook, Twitter, Github, LinkedIn, and more.') }}
                </p>

                <p class="pt-4">
                    {{ __('Supported login providers:') }}
                </p>
                <div class="flex gap-3 pt-1 flex-wrap">
                    @svg('diamonds/colored/google', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/facebook', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/twitter-oauth-2', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/linkedin', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/github', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/gitlab', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                    @svg('diamonds/colored/bitbucket', 'h-12 w-12 py-2 px-2 border border-primary-50 rounded-lg')
                </div>
            </x-section.column>

        </x-section.columns>
    </x-section.block>


    <div class="text-center mt-24" x-intersect="$el.classList.add('slide-in-top')">
        <x-heading.h6 class="text-primary-500 uppercase!">
            {{ __('Oh, we\'re not done yet') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('And a whole lot more') }}
        </x-heading.h2>
    </div>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="users" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Users & Roles') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Manage your users, create roles and assign permissions to your users.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="translatable" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Fully translatable') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Translate your application to any language you want.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="seo" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Sitemap & SEO') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Auto-generated sitemap and SEO optimization out of the box.') }}</p>
        </x-section.column>

    </x-section.columns>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="user-dashboard" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('User Dashboard') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Users can manage their subscriptions, change payment method, upgrade plan, cancel subscription alone.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="tool" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Highly customizable') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Manage your SaaS settings from within the admin panel. No need to redeploy app for simple changes anymore.') }}</p>
        </x-section.column>

        <x-section.column class="flex flex-col items-center justify-center text-center">
            <x-icon.fancy name="development" class="w-1/4 mx-auto" />
            <x-heading.h3 class="mx-auto pt-2">
                {{ __('Developer-friendly') }}
            </x-heading.h3>
            <p class="mt-2">{{ __('Built with developers in mind, uses best coding practices. Offers handlers & events and automated tests covering critical components of the application.') }}</p>
        </x-section.column>

    </x-section.columns>


    <div class="mx-4 mt=16">
        <x-heading.h6 class="text-center mt-24 text-primary-500 uppercase!" id="pricing">
            {{ __('Launch your SaaS Today') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900 text-center">
            {{ __('Ship Your SaaS in Days') }}
        </x-heading.h2>
    </div>

    <div class="pricing">
        <x-plans.all calculate-saving-rates="true" show-default-product="1"/>
        <x-products.all />
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
                <x-slot name="title">{{ __('What is SaaSykit?') }}</x-slot>

                <p>
                    {{ __('SaaSykit is a complete SaaS starter kit that includes everything you need to start your SaaS business. It comes ready with a huge list of reusable components, a complete admin panel, user dashboard, user authentication, user & role management, plans & pricing, subscriptions, payments, emails, and more.') }}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('What features does SaaSykit offer?') }}</x-slot>

                <p class="mt-4">
                    {{ __('Here are some of the features included in SaaSykit in a nutshell:') }}
                </p>

                <ul class="mt-4 list-disc ms-4 ps-4">
                    <li>{{ __('Customize Styles: Customize the styles &amp; colors, error page of your application to fit your brand.') }}</li>
                    <li>{{ __('Product, Plans &amp; Pricing: Create and manage your products, plans, and pricing from a beautiful and easy-to-use admin panel.') }}</li>
                    <li>{{ __('Beautiful checkout process: Your customers can subscribe to your plans from a beautiful checkout process.') }}</li>
                    <li>{{ __('Huge list of ready-to-use components: Plans &amp; Pricing, hero section, features section, testimonials, FAQ, Call to action, tab slider, and much more.') }}</li>
                    <li>{{ __('User authentication: Comes with user authentication out of the box, whether classic email/password or social login (Google, Facebook, Twitter, Github, LinkedIn, and more).') }}</li>
                    <li>{{ __('Discounts: Create and manage your discounts and reward your customers.') }}</li>
                    <li>{{ __('SaaS metric stats: View your MRR, Churn rates, ARPU, and other SaaS metrics.') }}</li>
                    <li>{{ __('Multiple payment providers: Stripe, Paddle, and more coming soon.') }}</li>
                    <li>{{ __('Multiple email providers: Mailgun, Postmark, Amazon SES, and more coming soon.') }}</li>
                    <li>{{ __('Blog: Create and manage your blog posts.') }}</li>
                    <li>{{ __('User &amp; Role Management: Create and manage your users and roles, and assign permissions to your users.') }}</li>
                    <li>{{ __('Fully translatable: Translate your application to any language you want.') }}</li>
                    <li>{{ __('Sitemap &amp; SEO: Sitemap and SEO optimization out of the box.') }}</li>
                    <li>{{ __('Admin Panel: Manage your SaaS application from a beautiful admin panel powered by ') }} <a href="https://filamentphp.com/" target="_blank" rel="noopener noreferrer">Filament</a>.</li>
                    <li>{{ __('User Dashboard: Your customers can manage their subscriptions, change payment method, upgrade plan, cancel subscription, and more from a beautiful user dashboard powered by') }} <a href="https://filamentphp.com/" target="_blank" rel="noopener noreferrer">Filament</a>.</li>
                    <li>{{ __('Automated Tests: Comes with automated tests for critical components of the application.') }}</li>
                    <li>{{ __('One-line deployment: Provision your server and deploy your application easily with integrated') }} <a href="https://deployer.org/" target="_blank" rel="noopener noreferrer">Deployer</a> {{ __('  support.') }}</li>
                    <li>{{ __('Developer-friendly: Built with developers in mind, uses best coding practices.') }}</li>
                    <li>{{ __('And much more...') }}</li>
                </ul>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Which payment providers are supported?') }}</x-slot>

                <p>
                    {{ __('SaaSykit supports Stripe and Paddle out of the box. You can easily add more payment providers by extending the code. More payment method will be added in the future as well (e.g. Lemon Squeezy)') }}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{ __('Do you offer support?') }}</x-slot>

                <p>
                    {{ __('Of course! we offer email and discord support to help you with any issues you might face or questions you have. Write us an email at') }} <a href="mailto:{{config('app.support_email')}}" class="text-primary-500 hover:underline">{{config('app.support_email')}}</a> {{ __('or join our') }} <a href="{{config('app.social_links.discord')}}">{{ __('discord server')}}</a> {{ __('to get help.')}}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'What Tech stack is used?'}}</x-slot>

                <p>
                    {{ __('SaaSykit is built on top of') }} <a href="https://laravel.com" target="_blank">Laravel</a> {{ __('Laravel, the most popular PHP framework, and') }} <a target="_blank" href="https://filamentphp.com/">Filament</a> {{ __(', a beautiful and powerful admin panel for Laravel. It also uses TailwindCSS, AlpineJS, and Livewire.')}}
                </p>
                <p class="mt-4">
                    {{ __('You can use your favourite database (MySQL, PostgreSQL, SQLite) and your favourite queue driver (Redis, Amazon SQS, etc).')}}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'How often is SaaSykit updated?'}}</x-slot>

                <p>
                    {{ __('SaaSykit is updated regularly to keep up with the latest Laravel and Filament versions, and to add new features and improvements.')}}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'Do you offer refunds?'}}</x-slot>

                <p>
                    {{ __('Yes, we offer a 14-day money-back guarantee. If you are not satisfied with SaaSykit, you can request a refund within 14 days of your purchase. Please write us an email at') }} <a href="mailto:{{config('app.support_email')}}" class="text-primary-500 hover:underline">{{config('app.support_email')}}</a> {{ __('to request a refund.')}}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'Where can I host my SaaS application?'}}</x-slot>

                <p>
                    {{ __('You can host your SaaS application on any server that supports PHP, such as DigitalOcean, AWS, Hetzner, Linode, and more. You can also use a platform like Laravel Forge to manage your server and deploy your application.')}}
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'Is there a demo available?'}}</x-slot>

                <p>
                    {{ __('Yes, a demo is available to help you get a feel of SaaSykit. You can find the demo') }} <a href="https://saasykit.com/demo" target="_blank" rel=”nofollow” >here</a>.
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'Is there documentation available?'}}</x-slot>

                <p>
                    {{ __('Yes, an extensive documentation is available to help you get started with SaaSykit. You can find the documentation ')}} <a href="https://saasykit.com/docs" target="_blank">here</a>.
                </p>

            </x-accordion.item>

            <x-accordion.item active="false" name="faqs">
                <x-slot name="title">{{'How is SaaSykit different from just using Laravel directly?'}}</x-slot>

                <p>
                    {{__('SaaSykit is built on top of Laravel with the intention to save you time and effort by not having to build everything needed for a modern SaaS from scratch, like payment provider integration, subscription management, user authentication, user & role management, having a beautiful admin panel, a user dashboard to manage their subscriptions/payments, and more.')}}
                </p>
                <p class="mt-4">
                    {{__('You can choose to base your SaaS on vanilla Laravel and build everything from scratch if you prefer and that is totally fine, but you will need a few months to build what SaaSykit offers out of the box, then on top of that, you will need to start to build your actual SaaS application.')}}
                </p>

                <p class="mt-4">
                    {{__('SaaSykit is a great starting point for your SaaS application, it is built with best coding practices, and it is developer-friendly. It is also built with the intention to be easily customizable and extendable. Any developer who is familiar with Laravel will feel right at home.')}}
                </p>

            </x-accordion.item>
        </x-accordion>


        <div class="text-center">
            <x-section.outro>
                <x-heading.h6 class="text-primary-50">
                    {{ __('Ship fast & don\'t reinvent the wheel') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-50 drop-shadow-4xl">
                    {{ __('Build your SaaS Today') }}
                </x-heading.h2>

                <p class="text-primary-100 mt-2">
                    {{ __('SaaSykit is a SaaS boilerplate that comes packed with all components required to run a modern SaaS software.') }}
                </p>

                <div class="mt-12">
                    <x-button-link.secondary href="/" >
                        {{ __('Buy SaaSykit Now') }}
                    </x-button-link.secondary>
                </div>
            </x-section.outro>
        </div>
    </div>

</x-layouts.app>
