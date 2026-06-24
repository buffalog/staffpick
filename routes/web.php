<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentProviders\PaddleController;
use App\Http\Controllers\ProductCheckoutController;
use App\Http\Controllers\ProviderApplicationController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\SlackWebhookController;
use App\Http\Controllers\StaffPick\ReferralSourceRegistrationController;
use App\Http\Controllers\StaffPick\SsoController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SurveyController;
use App\Livewire\StaffPick\ProviderOfferResponse;
use App\Livewire\StaffPick\PublicIntakeForm;
use App\Services\PlanService;
use App\Services\SessionService;
use App\Services\TenantCreationService;
use App\Services\UserDashboardService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| If you want the URL to be added to the sitemap, add a "sitemapped" middleware to the route (it has to GET route)
|
*/

Route::get('/', function () {
    return view('home');
})->name('home')->middleware('sitemapped');

Route::get('/dashboard', function (UserDashboardService $dashboardService) {
    return redirect($dashboardService->getUserDashboardUrl(Auth::user()));
})->name('dashboard')->middleware('auth');

Auth::routes();

Route::get('/plan/start', function (
    TenantCreationService $tenantCreationService,
    SessionService $sessionService,
    PlanService $planService,
) {
    if ($planService->getDefaultProduct() !== null) {
        if (! auth()->check()) {
            $sessionService->setCreateTenantForFreePlanUser(true);
        } else {
            $tenantCreationService->createTenantForFreePlanUser(auth()->user());
        }
    }

    return redirect()->route('register');
})->name('plan.start');

Route::get('/email/verify', function () {
    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    $user = $request->user();
    if ($user->hasVerifiedEmail()) {
        return redirect()->route('registration.thank-you');
    }

    return redirect('/');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::get('/phone/verify', function () {
    return view('verify.sms-verification');
})->name('user.phone-verify')
    ->middleware('auth');

Route::get('/phone/verified', function () {
    return view('verify.sms-verification-success');
})->name('user.phone-verified')
    ->middleware('auth');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/registration/thank-you', function () {
    return view('auth.thank-you');
})->middleware('auth')->name('registration.thank-you');

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.redirect');

Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.callback');

Route::get('/checkout/plan/{planSlug}', [
    SubscriptionCheckoutController::class,
    'subscriptionCheckout',
])->name('checkout.subscription');

Route::get('/checkout/convert-subscription/{subscriptionUuid}', [
    SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckout',
])->name('checkout.convert-local-subscription');

Route::get('/already-subscribed', function () {
    return view('checkout.already-subscribed');
})->name('checkout.subscription.already-subscribed');

Route::get('/checkout/subscription/success', [
    SubscriptionCheckoutController::class,
    'subscriptionCheckoutSuccess',
])->name('checkout.subscription.success')->middleware('auth');

Route::get('/checkout/convert-subscription-success', [
    SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckoutSuccess',
])->name('checkout.convert-local-subscription.success')->middleware('auth');

Route::get('/payment-provider/paddle/payment-link', [
    PaddleController::class,
    'paymentLink',
])->name('payment-link.paddle');

Route::get('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan')->middleware('auth');

Route::post('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan.post')->middleware('auth');

Route::get('/subscription/change-plan-thank-you', [
    SubscriptionController::class,
    'success',
])->name('subscription.change-plan.thank-you')->middleware('auth');

// blog
Route::controller(BlogController::class)
    ->prefix('/blog')
    ->group(function () {
        Route::get('/', 'all')->name('blog')->middleware('sitemapped');
        Route::get('/category/{slug}', 'category')->name('blog.category');
        Route::get('/{slug}', 'view')->name('blog.view');
    });

Route::get('/terms-of-service', function () {
    return view('pages.terms-of-service');
})->name('terms-of-service')->middleware('sitemapped');

Route::get('/privacy-policy', function () {
    return view('pages.privacy-policy');
})->name('privacy-policy')->middleware('sitemapped');

// Product checkout routes

Route::get('/buy/product/{productSlug}/{quantity?}', [
    ProductCheckoutController::class,
    'addToCart',
])->name('buy.product');

Route::get('/checkout/product', [
    ProductCheckoutController::class,
    'productCheckout',
])->name('checkout.product');

Route::get('/checkout/product/success', [
    ProductCheckoutController::class,
    'productCheckoutSuccess',
])->name('checkout.product.success')->middleware('auth');

// roadmap

Route::controller(RoadmapController::class)
    ->prefix('/roadmap')
    ->group(function () {
        Route::get('/', 'index')->name('roadmap');
        Route::get('/i/{itemSlug}', 'viewItem')->name('roadmap.viewItem');
        Route::get('/suggest', 'suggest')->name('roadmap.suggest')->middleware('auth');
    });

// Invitations

Route::get('/invitations', [
    InvitationController::class,
    'index',
])->name('invitations')->middleware('auth');

Route::get('/invitations/accept/{token}', [
    InvitationController::class,
    'accept',
])->name('invitations.accept')->middleware('auth');

// Invoice

Route::controller(InvoiceController::class)
    ->prefix('/invoice')
    ->group(function () {
        Route::get('/generate/{transactionUuid}', 'generate')->name('invoice.generate');
        Route::get('/preview', 'preview')->name('invoice.preview');
    });

// StaffPick — public patient survey response (token-authenticated, no login)

Route::controller(SurveyController::class)
    ->prefix('/survey')
    ->middleware('throttle:30,1')
    ->group(function () {
        Route::get('/{token}', 'show')->name('survey.show');
        Route::post('/{token}', 'submit')->middleware('throttle:6,1')->name('survey.submit');
    });

// StaffPick — public referral-source intake submission (token-authenticated, no login)
// Page-load throttle; the actual submission (a Livewire action over /livewire/update)
// is additionally rate-limited inside PublicIntakeForm::submit().
Route::get('/intake/{token}', PublicIntakeForm::class)
    ->middleware('throttle:30,1')
    ->name('staffpick.intake.show');

// StaffPick — public provider self-serve onboarding wizard (no login). /join/{slug}
// creates a draft and redirects to the tokenized resume URL. Page-load throttled; the
// wizard's save/submit Livewire actions run over /livewire/update.
Route::get('/join/{tenantSlug}', [ProviderApplicationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('staffpick.application.show');
Route::get('/join/{tenantSlug}/resume/{token}', [ProviderApplicationController::class, 'resume'])
    ->middleware('throttle:30,1')
    ->name('staffpick.application.resume');
// Authenticated staff download of an applicant's uploaded credential (authorized in the
// controller to admins/staff of the application's tenant).
Route::get('/staff/applications/{application}/credentials/{index}', [ProviderApplicationController::class, 'downloadCredential'])
    ->middleware('auth')
    ->name('staffpick.application.credential');

// StaffPick — public referral-source self-registration (no login). {tenantSlug} is
// the tenant uuid. Page-load throttled; the Livewire submit is rate-limited in-component.
Route::get('/register/{tenantSlug}', [ReferralSourceRegistrationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('staffpick.referral-source.register');

// StaffPick — inbound Slack webhook (signature-verified, no login/CSRF). The token
// namespaces the tenant; rate-limited to blunt abuse of the public endpoint.
Route::post('/webhooks/slack/{token}', SlackWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('staffpick.slack.inbound');

// StaffPick — authenticated provider assignment-offer response page. The token
// resolves the offer; the page authorizes it against the signed-in provider.
Route::get('/offers/{token}', ProviderOfferResponse::class)
    ->middleware('auth')
    ->name('staffpick.offer.respond');

// StaffPick — dead-end for a signed-in user who belongs to no workspace yet (e.g. a
// brand-new social-login user). A safe place to land with a contact path, not a wizard.
Route::view('/no-workspace', 'staffpick.no-workspace')
    ->middleware('auth')
    ->name('staffpick.no-workspace');

// StaffPick — tenant SSO handshake (kept off the Filament panel path). {tenant} is
// the tenant uuid. The callback is rate limited; Socialite handles the OAuth state.
Route::get('/auth/sso/{tenant}/redirect', [SsoController::class, 'redirect'])
    ->middleware('throttle:sso')
    ->name('staffpick.sso.redirect');
Route::get('/auth/sso/{tenant}/callback', [SsoController::class, 'callback'])
    ->middleware('throttle:sso')
    ->name('staffpick.sso.callback');
