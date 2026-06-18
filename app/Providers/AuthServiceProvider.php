<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\StaffPick\StaffPickAdminPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class => RolePolicy::class,
        // StaffPick PHI models — restricted to tenant admins (see StaffPickAdminPolicy).
        IntakeRequest::class => StaffPickAdminPolicy::class,
        Subject::class => StaffPickAdminPolicy::class,
        Provider::class => StaffPickAdminPolicy::class,
        ReferralSource::class => StaffPickAdminPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Platform super admins bypass all authorization (policies + gates). This is
        // what lets them manage any tenant's resources from the super-admin panel and
        // act inside any tenant via the canAccessTenant bypass.
        Gate::before(fn (?User $user) => $user?->isSuperAdmin() ? true : null);

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new \App\Mail\User\VerifyEmail($url))
                ->to($notifiable->email);
        });

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new \App\Mail\User\ResetPassword($url))
                ->to($notifiable->email);
        });
    }
}
