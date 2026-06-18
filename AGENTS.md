# Agents.md

This file provides guidance to AI agents working on the SaaSykit project, including an overview of the project, development commands, architecture, coding standards, and environment setup.

## Project Overview

SaaSykit Tenancy is a  multi-tenant Laravel-based SaaS starter kit built with the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire). It provides a complete SaaS boilerplate with subscription management, payment processing, admin panels, and user dashboards powered by Filament.

SaaSykit Tenancy is a SaaS starter kit (boilerplate) that comes packed with all components required to run a modern SaaS software.

SaaSykit Tenancy is built with the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire), and offers an intuitive Filament admin panel that houses all the pre-built components like product, plans, discounts, payment providers, email providers, transactions, blog, user & role management, and much more.

### Features in a nutshell

* Multi-tenancy (SaaSykit Tenancy): Build multi-tenancy applications, seat-based subscriptions with a seamless checkout experience.
* Customize Styles: Customize the styles & colors, error page of your application to fit your brand.
* Product, Plans & Pricing: Create and manage your products, plans, and pricing from a beautiful and easy-to-use admin panel.
* Beautiful checkout process: Your customers can subscribe to your plans from a beautiful checkout process.
* Huge list of ready-to-use components: Plans & Pricing, hero section, features section, testimonials, FAQ, Call to action, tab slider, and much more.
* User authentication: Comes with user authentication out of the box, whether classic email/password or social login (Google, Facebook, Twitter, Github, LinkedIn, and more).
* Discounts: Create and manage your discounts and reward your customers.
* SaaS metric stats: View your MRR, Churn rates, ARPU, and other SaaS metrics.
* Multiple payment providers: Stripe, Paddle, Lemon Squeezy and Offline (manual) payments support out of the box.
* Multiple email providers: Mailgun, Postmark, Amazon SES, and more coming soon.
* Blog: Create and manage your blog posts.
* User & Role Management: Create and manage your users and roles, and assign permissions to your users.
* Fully translatable: Translate your application to any language you want.
* Sitemap & SEO: Sitemap and SEO optimization out of the box.
* Admin Panel: Manage your SaaS application from a beautiful admin panel powered by Filament.
* User Dashboard: Your customers can manage their subscriptions, change payment method, upgrade plan, cancel subscription, and more from a beautiful user dashboard powered by Filament.
* User Onboarding: Guide your users through the onboarding process with a beautiful onboarding wizard.
* Two-factor authentication: Secure your users' accounts with two-factor authentication.
* ReCaptcha: Protect your application from spam and abuse with Google reCAPTCHA.
* Roadmap: Let your users suggest features and vote on them and keep them updated on what's coming next.
* Automated Tests: Comes with automated tests for critical components of the application.
* One-line deployment: Provision your server and deploy your application easily with integrated Deployer support.
* Developer-friendly: Built with developers in mind, uses best coding practices.
*

## Development Commands

### Frontend Development
- `npm run dev` - Start Vite development server for asset compilation
- `npm run build` - Build assets for production

### Backend Development
- `php artisan serve` - Start Laravel development server
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan queue:work` - Start queue worker
- `php artisan horizon` - Start Laravel Horizon for queue monitoring

### Testing & Quality
- `vendor/bin/phpunit` - Run PHPUnit tests
- `vendor/bin/phpunit --filter=TestName` - Run specific test
- `vendor/bin/phpstan analyse` - Run static analysis (level 3)
- `vendor/bin/pint` - Run Laravel Pint code formatter

### Deployment
- `php dep deploy` - Deploy using Deployer (configured in deploy.php)

## Architecture & Structure

### Core Directories
- `app/Filament/Admin/` - Admin panel resources and pages (Filament 4)
- `app/Filament/Dashboard/` - User dashboard resources (Filament 4)
- `app/Services/` - Business logic services (service layer pattern)
    - `PaymentProviders/` - Payment gateway implementations (Stripe, Paddle, Lemon Squeezy, Offline)
    - `VerificationProviders/` - User verification integrations
- `app/Models/` - Eloquent models with relationships
- `app/Livewire/` - Livewire components
    - `Auth/` - Authentication components
    - `Checkout/` - Checkout flow components
    - `Roadmap/` - Feature voting components
- `app/Http/` - Controllers and middleware
- `app/Notifications/` - Email/notification classes
- `app/Events/` - Domain events (Order, Subscription, User)
- `app/Listeners/` - Event listeners
- `app/Mail/` - Mailable classes (organized by domain)
- `app/Dto/` - Data Transfer Objects
- `app/Mapper/` - Data mappers
- `app/Constants/` - Application constants
- `app/Policies/` - Authorization policies
- `app/Validator/` - Custom validation rules
- `app/Console/Commands/` - Artisan commands
- `database/migrations/` - Database schema migrations
- `database/seeders/` - Database seeders
- `resources/views/` - Blade templates
- `resources/views/livewire/` - Livewire component views
- `tests/` - Automated tests (PHPUnit)

### Key Technologies
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CSS 4 + DaisyUI 5
- **Admin Interface**: Filament 4 with Spatie Media Library plugin
- **Asset Compilation**: Vite 7
- **Payments**: Stripe, Paddle, Lemon Squeezy, Offline (manual)
- **Queue System**: Laravel Horizon (Redis-based)
- **Authentication**:
    - Laravel Sanctum (API tokens)
    - Filament Breezy (auth UI)
    - Social login via Laravel Socialite (Google, Facebook, Twitter, GitHub, LinkedIn)
    - One-time passwords (Spatie)
    - Two-factor authentication (Laragear)
- **Email**: Supports Mailgun, Postmark, Amazon SES, Resend
- **SMS**: Twilio integration
- **Media**: Spatie Media Library + Intervention Image
- **Permissions**: Spatie Permission package (roles & permissions)
- **Testing**: PHPUnit, Static Analysis (PHPStan/Larastan level 3)
- **Code Quality**: Laravel Pint (PSR-12 formatting)
- **Debugging**: Laravel Telescope (dev), Laravel Debugbar (dev)
- **Deployment**: Deployer (automated deployment)

### Core Domain Models
Key models representing the business domain:
- **Tenant** - Tenants (for multi-tenancy)
- **User** - Users with roles, permissions, subscriptions
- **Product** - Products (subscription-based SaaS offerings)
- **Plan** - Subscription plans with pricing tiers
- **PlanPrice** - Pricing for plans (per interval/currency)
- **PlanMeter** - Usage-based billing meters
- **Subscription** - User subscriptions to plans
- **SubscriptionUsage** - Usage tracking for metered billing
- **OneTimeProduct** - One-time purchasable products
- **OneTimeProductPrice** - One-time product pricing
- **Order** - One-time product orders
- **Invoice** - Generated invoices (subscription/one-time)
- **Transaction** - Payment transactions
- **Discount** - Discount rules
- **DiscountCode** - Discount codes with redemption tracking
- **BlogPost** - Blog posts with categories
- **RoadmapItem** - Feature requests with user voting
- **Announcement** - User announcements
- **PaymentProvider** - Payment gateway configurations
- **EmailProvider** - Email service configurations
- **Currency** - Supported currencies
- **Address** - User/order addresses
- **Config** - Dynamic application configuration

### Service Layer
The application uses a service layer pattern. Key services:
- `TenantService` - Tenant management
- `TenantSubscriptionService` - Tenant subscription handling
- `TenantPermissionService` - Tenant roles/permissions
- `TenantCreationService` - Tenant onboarding
- `SubscriptionService` - Subscription lifecycle management
- `OrderService` - Order processing
- `CheckoutService` - Checkout flow logic
- `PlanService` - Plan management
- `DiscountService` - Discount application
- `InvoiceService` - Invoice generation
- `MetricsService` - SaaS metrics calculation (MRR, ARPU, churn)
- `TransactionService` - Transaction handling
- `UserService` - User management
- `LoginService` - Authentication logic
- `OneTimePasswordService` - OTP handling
- `BlogService` - Blog post management
- `RoadmapService` - Feature voting
- `CurrencyService` - Currency operations
- `ConfigService` - Dynamic configuration

### Payment Provider Architecture
Payment providers are abstracted via contracts in `app/Services/PaymentProviders/`:
- Each provider implements common interfaces
- Supports Stripe, Paddle, Lemon Squeezy, and Offline payments
- Provider-specific data stored in `*PaymentProviderData` models
- Webhooks handle provider callbacks

### Event-Driven Architecture
The application uses Laravel events for domain actions:
- **Order Events**: Order created, completed, failed
- **Subscription Events**: Created, updated, cancelled, renewed, trial started/ended
- **User Events**: Registered, verified, etc.
- Listeners handle side effects (emails, notifications, metrics)

## Coding Standards

### SaaSykit-Specific Conventions
- Services should be stateless and injected via dependency injection
- Use DTOs for complex data structures passed between layers
- Event/Listener pattern for side effects
- Filament for admin UI (avoid custom controllers when possible)
- Livewire for interactive frontend components
- Payment provider logic should be isolated in provider-specific classes
- All monetary amounts use the `Money` package
- Translations via `__()` function
- Use Spatie Permissions for authorization
- Queue long-running tasks (emails, webhooks, metrics)

### Database Conventions
- Use migrations for all schema changes
- Foreign keys with cascade/set null as appropriate
- Use proper indexes for performance
- Version history via `mpociot/versionable` where needed

### Testing Guidelines
- Feature tests for critical flows (subscription, checkout, payment)
- Unit tests for services with complex logic
- Use factories for test data
- Mock external services (payment providers, email)
- Run tests before committing: `vendor/bin/phpunit`

## Environment Setup

### First-Time Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
```

### Development Tools
- **Horizon Dashboard**: `/horizon` (queue monitoring)
- **Telescope Dashboard**: `/telescope` (debugging, dev only)
- **Admin Panel**: `/admin`
- **User Dashboard**: `/dashboard`

===

<laravel-boost-guidelines>
=== .ai/deployment-railway rules ===

# Deployment

> Curated, project-specific deployment + database constraints. This file lives in
> `.ai/guidelines/` so `php artisan boost:install` composes it into the generated
> guideline block and NEVER overwrites it (Boost only regenerates its own built-in
> guidelines; user files under `.ai/guidelines/` are merged in untouched). The
> built-in generic "deploy with Laravel Cloud" guideline is excluded via
> `config/boost.php` (`guidelines.exclude => ['deployments']`).

## Production: Railway

This application is deployed on **Railway**, not Laravel Cloud or Deployer.

- **Project**: staffpick (ID: `50b12e33-0382-4140-a6ff-7c3153491fff`)
- **Environment**: staging (ID: `3c4299d6-bf25-4acc-9b40-1b0608e4a0e9`)
- **App service**: `10a329c9-9655-4aea-ac9c-f853d47c9cd9`
- **App URL**: `https://app-staging-2263.up.railway.app`
- **DB service**: `5a853239-7ca7-4b66-a01c-b4258805f743`
- **Repo**: `buffalog/staffpick` (GitHub), `main` branch — Railway auto-deploys on push

### How deployment works

Pushes to `main` trigger a Railway build. The Dockerfile builds the image (PHP 8.4, sqlsrv extension, Node 22 for Vite assets). On container start, `start.sh` runs:
1. Creates the `staffpick` database if it doesn't exist (via sqlcmd)
2. Clears config cache
3. Runs `php artisan migrate --force`
4. Runs each seeder individually (RolesAndPermissions suppresses duplicate key errors on subsequent boots)
5. Caches routes and views
6. Starts `php artisan serve` on `$PORT`

### Database: Azure SQL (SQL Server) — CRITICAL

The database is **Azure SQL Edge** via the `sqlsrv` Laravel driver at `db.railway.internal:1433`. This is locked in for HIPAA BAA coverage and is **not changing**.

**SQL Server constraints already handled in all existing migrations — do not violate these:**

1. **Cascade cycles** — SQL Server rejects ANY FK that creates multiple cascade paths to the same ancestor table, regardless of action type. All `sp_*` models use plain `unsignedBigInteger` for `tenant_id` — no FK constraint. Do NOT add constrained FKs that create multiple cascade paths back to `users` or `tenants`.

2. **Index-dependent ALTER COLUMN** — SQL Server blocks `ALTER COLUMN` on any column with a dependent index. Pattern: drop index → alter column → recreate index. See `2024_04_09_095954_table_roadmap_items_adjust_slug_type.php` and `2026_02_21_141433_change_versionable_id_to_integer_in_version_tables.php` for reference implementations.

3. **No fulltext index via Blueprint** — `$table->fullText()` is unsupported by the `sqlsrv` driver. Guard with `if (config('database.default') !== 'sqlsrv')`.

4. **No `dropColumn` inside `Schema::create`** — invalid on any DB, crashes on SQL Server.

5. **No local migration runs** — PHP 8.5 on the dev machine seg faults with the `sqlsrv` extension. Migrations are validated via Railway deploys. Write and validate against migration files as source of truth.

### Local development vs Railway

- The local environment uses Laravel Herd and a local MySQL/SQLite DB for general Laravel work
- The `sp_*` (StaffPick domain) tables only exist on Railway — they are NOT in the local database
- For StaffPick-specific feature tests requiring `sp_*` tables, use a local Azure SQL Edge container:
  ```bash
  docker run -e 'ACCEPT_EULA=1' -e 'MSSQL_SA_PASSWORD=StaffPick_Dev_2026!' \
    -e 'MSSQL_PID=Developer' -p 1433:1433 --name staffpick-db \
    -d mcr.microsoft.com/azure-sql-edge:latest
  sleep 20 && sqlcmd -S 127.0.0.1 -U sa -P 'StaffPick_Dev_2026!' -C -Q "CREATE DATABASE staffpick_test;"
  ```
  Then set `.env.testing` to use `DB_CONNECTION=sqlsrv` pointing at `127.0.0.1:1433`.
- SQLite in-memory is NOT a valid substitute for StaffPick feature tests — SQL Server has different DDL constraints that SQLite does not enforce.
- **`pdo_sqlsrv` (Railway) returns integer/bigint columns as PHP strings; the local FreeTDS `dblib` driver returns ints.** So a raw, un-cast column read (`$offer->provider_id`) is `"28"` in production but `28` locally. A **strict comparison passes locally and fails on Railway** — e.g. `$provider->id !== $offer->provider_id` 403'd the legitimate owner of `/offers/{token}` because `28 !== "28"`. Tests on the dblib container can't catch this. Rules: cast both sides (`(int) $a !== (int) $b`), or compare loosely (`==`), or push the check into a query `where()` instead of comparing in PHP. Eloquent `$casts`/`$fillable` fix declared model attributes, but **un-cast FK columns and any `DB::`-facade results are returned as strings** — never strict-compare those against an int.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v11
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

</laravel-boost-guidelines>
