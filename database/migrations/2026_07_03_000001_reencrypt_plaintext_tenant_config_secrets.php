<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TenantConfig casts `slack_signing_secret` and `sso_client_secret` as `encrypted`.
 * Rows written before that cast existed hold PLAINTEXT, so every read throws
 * DecryptException — which 500'd the inbound Slack webhook on each call
 * (SlackWebhookController resolves the signing secret on entry, line ~40).
 *
 * Re-encrypt any value that isn't already valid ciphertext, once, in place. The raw
 * column is read via the query builder to bypass the model cast (which would throw).
 * Idempotent: already-encrypted values decrypt cleanly and are skipped.
 *
 * slack_signing_secret was originally string() = nvarchar(255); an encrypted value is
 * ~280 chars and overflows it (SQL Server errors hard: "String or binary data would
 * be truncated"), so widen it to text() first. sso_client_secret is already text().
 * Neither column is indexed, so the ALTER is safe on SQL Server (see CLAUDE.md).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $encryptedColumns = ['slack_signing_secret', 'sso_client_secret'];

    public function up(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->text('slack_signing_secret')->nullable()->change();
        });

        $rows = DB::table('sp_tenant_configs')->get(['id', ...$this->encryptedColumns]);

        foreach ($rows as $row) {
            $updates = [];

            foreach ($this->encryptedColumns as $column) {
                $value = $row->{$column};

                if (blank($value)) {
                    continue;
                }

                // Already proper ciphertext → leave it. Only plaintext (or otherwise
                // undecryptable) values are re-encrypted.
                try {
                    Crypt::decryptString($value);

                    continue;
                } catch (DecryptException) {
                    $updates[$column] = Crypt::encryptString($value);
                }
            }

            if ($updates !== []) {
                DB::table('sp_tenant_configs')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // No safe inverse: decrypting back to plaintext at rest is exactly the state
        // this migration exists to remove.
    }
};
