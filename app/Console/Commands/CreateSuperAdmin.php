<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates or promotes a platform super admin. Super admins bypass all tenant scoping
 * and SSO. is_super_admin is intentionally NOT mass-assignable, so this command (and
 * direct DB access) is the only way to grant it — never the UI.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'staffpick:create-super-admin
        {--email= : Super admin email}
        {--name= : Display name (default: derived from the email)}
        {--password= : Password to set on first create (generated if omitted)}';

    protected $description = 'Create or promote a platform super admin (CLI only — never callable from the UI).';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Super admin email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: Str::headline(Str::before($email, '@'));

        $user = User::query()->where('email', $email)->first();
        $newPassword = null;

        if ($user === null) {
            $newPassword = $this->option('password') ?: Str::password(20);

            $user = new User([
                'name' => $name,
                'email' => $email,
                'is_admin' => true,
            ]);
            $user->password = $newPassword; // hashed by the model cast
            $user->is_super_admin = true;   // set directly — not fillable
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            $this->info("Created super admin {$user->email}.");
        } else {
            $user->is_admin = true;
            $user->is_super_admin = true;
            $user->save();

            $this->info("Promoted existing user {$user->email} to super admin.");
        }

        $this->newLine();
        $this->line('  Email:    '.$user->email);

        if ($newPassword !== null) {
            $this->line('  Password: '.$newPassword);
        } else {
            $this->comment('  Existing user — password left unchanged.');
        }

        return self::SUCCESS;
    }
}
