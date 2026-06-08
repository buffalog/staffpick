<div class="p-4 py-6 lg:py-8 mt-12">
    <hr class="  max-w-(--breakpoint-xl) mx-auto bg-gray-100 border-0  h-[1px]" >
    <footer class="footer py-8 px-4 mx-auto w-full max-w-6xl text-primary-800 md:flex md:justify-between">
        <aside>
            <a href="/" class="flex items-center">
                <img src="{{asset(config('app.logo.dark') )}}" class="h-6 me-3" alt="Logo" />
            </a>

            <span class="text-xs  sm:text-center md:text-left dark:text-gray-400">
              © {{ date('Y') }} <a href="/" class="text-primary-800">{{ config('app.name') }}™</a>. {{ __('All rights reserved.') }}
            </span>

        </aside>
        <nav>
            <h6 class="footer-title">Discover</h6>
            <a href="https://saasykit.com/docs" class="text-primary-900 hover:text-primary-400">{{ __('Documentation') }}</a>
            <a href="//demo.saasykit.com" class="text-primary-900 hover:text-primary-400" rel=”nofollow”>{{ __('Check SaaSykit Demo') }}</a>
            <a href="//tenancy-demo.saasykit.com" class="text-primary-900 hover:text-primary-400" rel=”nofollow”>{{ __('Check SaaSykit Tenancy Demo') }}</a>
            @guest
                <a href="{{route('login')}}" class="text-primary-900 hover:text-primary-400">{{ __('Your Account') }}</a>
            @endguest
            <a href="{{route('blog')}}" class="text-primary-900 hover:text-primary-400">{{ __('Blog') }}</a>
            <a href="/blog/saasykit-vs-laravel-spark-which-starter-kit-should-you-choose" class="text-primary-900 hover:text-primary-400">{{ __('SaaSykit vs. Laravel Spark') }}</a>
        </nav>
        <nav>
            <h6 class="footer-title">Collaboration</h6>
            <a href="https://saasykit.lemonsqueezy.com/affiliates" class="text-primary-900 hover:text-primary-400" rel=”nofollow”>{{ __('Affiliates') }}</a>
            <a href="{{route('roadmap')}}" class="text-primary-900 hover:text-primary-400">{{ __('Roadmap') }}</a>
        </nav>
        <nav>
            <h6 class="footer-title">Legal</h6>
            <a href="{{route('privacy-policy')}}" class="text-primary-900 hover:text-primary-400">{{ __('Privacy Policy') }}</a>
            <a href="{{route('terms-of-service')}}" class="text-primary-900 hover:text-primary-400">{{ __('Terms of Service') }}</a>

            <h6 class="footer-title mt-6">Partners</h6>
            <a href="https://microlaunch.net/?ref=saasykit" class="text-primary-900 hover:text-primary-400">{{ __('MicroLaunch') }}</a>
            <a href="https://indievoice.app/?ref=saasykit" class="text-primary-900 hover:text-primary-400">{{ __('IndieVoice') }}</a>
        </nav>
        <nav>
            <h6 class="footer-title">Get in touch</h6>
            @if (!empty(config('app.social_links.facebook')))
                <x-link.social-icon name="facebook" title="{{ __('Facebook page') }}" link="{{config('app.social_links.facebook')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.instagram')))
                <x-link.social-icon name="instagram" title="{{ __('Instagram page') }}" link="{{config('app.social_links.instagram')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.youtube')))
                <x-link.social-icon name="youtube" title="{{ __('YouTube channel') }}" link="{{config('app.social_links.youtube')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.x')))
                <x-link.social-icon name="x" title="{{ __('Twitter page') }}" link="{{config('app.social_links.x')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.linkedin')))
                <x-link.social-icon name="linkedin" title="{{ __('Linkedin page') }}" link="{{config('app.social_links.linkedin')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.github')))
                <x-link.social-icon name="github" title="{{ __('Github page') }}" link="{{config('app.social_links.github')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
            @if (!empty(config('app.social_links.discord')))
                <x-link.social-icon name="discord" title="{{ __('Discord community') }}" link="{{config('app.social_links.discord')}}" class="border-primary-200 text-primary-900 hover:text-primary-400"/>
            @endif
        </nav>
    </footer>
</div>
