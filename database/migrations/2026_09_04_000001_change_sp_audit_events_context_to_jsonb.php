<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * sp_audit_events.context becomes jsonb, and gains a GIN index for containment queries.
 *
 * WHY: a 'listed' event names many patients, so subject_id (a scalar bigint) is NULL on every
 * one of them and the patient ids live only inside context. Against a text column a JSON-path
 * or containment query throws "operator does not exist" on Postgres, so the audit viewer's
 * per-patient filter could not see list disclosures at all: asking for one patient's complete
 * access history returned a confidently incomplete answer.
 *
 * THE CAST IS DEFENSIVE ON PURPOSE. Every row on the authoritative environment was inspected
 * before this was written: 33 rows, 9 with NULL context, 24 valid JSON, 0 invalid, in four
 * shapes ({email}, {changes}, {actor_label}). A bare `USING context::jsonb` would therefore have
 * worked there. It is not used, because this runs on environments whose contents were not
 * inspected, and the two failure modes are both unacceptable on an audit table: aborting the
 * deploy, or losing recorded content. `IS JSON` (Postgres 16+, and we are on 18) lets an
 * unparseable value be preserved verbatim under an 'unparsed_context' key instead.
 *
 * OPERATOR CLASS: the default jsonb_ops, chosen by measurement rather than by folklore.
 *
 * The usual advice is that jsonb_path_ops is smaller and faster when the only operator you need
 * is containment, which is exactly this case. That advice is wrong for this data shape, and it
 * was measured rather than assumed. Against 45,000 seeded events on Postgres 18.4, running the
 * real filter query (context @> '{"subject_ids":[N]}'):
 *
 *                     index size    index-scan buffers    index-scan time
 *   jsonb_path_ops      5536 kB            291              0.980 ms
 *   jsonb_ops (default) 1232 kB             15              0.088 ms
 *
 * jsonb_path_ops hashes each root-to-leaf path, so every element of a subject_ids array becomes
 * its own hash entry and nothing is shared between rows. The default indexes keys and scalar
 * values separately, so one patient id occupies a single compact posting list spanning every
 * event that disclosed them, which is precisely the access pattern here: few keys, one scalar
 * repeated across many rows. The default wins ~11x on time and ~4.5x on size, and it keeps the
 * key-existence operators (?, ?|, ?&) available for free if a future query wants them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE sp_audit_events
            ALTER COLUMN context TYPE jsonb
            USING CASE
                WHEN context IS NULL THEN NULL
                WHEN context IS JSON THEN context::jsonb
                ELSE jsonb_build_object('unparsed_context', context)
            END
        SQL);

        DB::statement('CREATE INDEX sp_audit_events_context_gin ON sp_audit_events USING gin (context)');
    }

    /**
     * Genuinely reversible. jsonb -> text always succeeds, so nothing here can strand the table.
     *
     * One honest caveat: jsonb normalises on the way in (it drops insignificant whitespace,
     * deduplicates keys and does not preserve key order), so a round trip returns equivalent
     * JSON, not the original bytes. Content is preserved; formatting is not.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sp_audit_events_context_gin');
        DB::statement('ALTER TABLE sp_audit_events ALTER COLUMN context TYPE text USING context::text');
    }
};
