<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Trait\RedirectAwareTrait;
use App\Models\OauthLoginProvider;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends RegisterController
{
    use RedirectAwareTrait;

    public function redirect(string $provider)
    {
        $providerObj = OauthLoginProvider::where('provider_name', $provider)->firstOrFail();

        if (! $providerObj->enabled) {
            abort(404);
        }

        if (Auth::check()) {
            return redirect()->route('home');
        }

        Redirect::setIntendedUrl(url()->previous());

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $providerObj = OauthLoginProvider::where('provider_name', $provider)->firstOrFail();

        if (! $providerObj->enabled) {
            abort(404);
        }

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (Exception) {
            return redirect()->route('login');
        }

        $isRegistration = false;
        DB::transaction(function () use ($provider, $oauthUser, &$isRegistration) {
            $user = User::where('email', $oauthUser->email)->first();

            if ($user) {
                $user->update([
                    'name' => $oauthUser->name ?? $user->name ?? $oauthUser->nickname,
                ]);
            } else {
                $user = $this->userService->createUser([
                    'name' => $oauthUser->name ?? $oauthUser->nickname ?? '',
                    'email' => $oauthUser->email,
                ], true);

                $isRegistration = true;
            }

            $user->userParameters()->updateOrCreate(
                ['name' => 'oauth_provider_'.$provider],
                ['value' => $provider]
            );

            // Persist whichever optional OAuth attributes this provider returned.
            $oauthAttributes = [
                'id' => 'id',
                'token' => 'token',
                'refreshToken' => 'refresh_token',
                'expiresIn' => 'expires_in',
                'avatar' => 'avatar',
                'nickname' => 'nickname',
            ];

            foreach ($oauthAttributes as $property => $suffix) {
                if (property_exists($oauthUser, $property) && $oauthUser->{$property}) {
                    $user->userParameters()->updateOrCreate(
                        ['name' => 'oauth_'.$provider.'_'.$suffix],
                        ['value' => $oauthUser->{$property}]
                    );
                }
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            Auth::login($user);
        });

        // StaffPick: a social-login user who belongs to no workspace (and isn't a super
        // admin) lands on a clear dead-end with a contact path — not a profile wizard.
        $authUser = Auth::user();
        if (! $authUser->isSuperAdmin() && $authUser->tenants()->doesntExist()) {
            return redirect()->route('staffpick.no-workspace');
        }

        if ($isRegistration) {
            return redirect()->route('registration.thank-you');
        }

        return redirect($this->getRedirectUrl(Auth::user()));
    }
}
