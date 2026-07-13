<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

/**
 * Guard against the missing-$casts family of bugs.
 *
 * pdo_sqlsrv (Railway/CI) returns integer-family and bit columns as PHP *strings*, while
 * the local FreeTDS/dblib driver returns real ints. An un-cast attribute is therefore "0"
 * in production and 0 locally, so any strict comparison (===, !==, in_array(..., true))
 * or Eloquent pluck() against it silently misbehaves — and every test still passes locally.
 *
 * This has bitten us twice already:
 *   - TenantService: `max_users_per_tenant !== 0` skipped the "unlimited" sentinel (latent).
 *   - MatchDispatchService: `in_array($p->id, $pluckedProviderIds, true)` never matched, so
 *     providers were double-offered and cases never escalated (live in production).
 *
 * Rather than patch call sites one at a time, this asserts the invariant at the source:
 * every integer-family / boolean column on every model's table must be cast.
 *
 * Primary keys need no special handling. Eloquent's getCasts() merges
 * [$keyName => $keyType] automatically whenever $incrementing is true, so hasCast('id')
 * is already true for a conventional model — the assertion below covers PKs uniformly with
 * no exemption. A model that opts out of incrementing (e.g. a pivot) simply has to declare
 * the cast itself, which is exactly the ambiguity we want surfaced rather than assumed.
 */
class ModelCastCoverageTest extends FeatureTest
{
    /**
     * SQL Server type names that come back from pdo_sqlsrv as strings. Decimal/numeric and
     * money columns are deliberately absent: they are not integer-family, the guard must not
     * push them toward an integer cast, and their existing decimal:N casts stay as they are.
     *
     * @var array<int, string>
     */
    private const STRINGY_TYPES = ['int', 'bigint', 'smallint', 'tinyint', 'bit'];

    /**
     * Deliberate per-column exemptions, as `Model::column => why`. Empty on purpose — every
     * integer/boolean column in the schema is currently cast. Add an entry here (with a
     * reason) rather than silently widening STRINGY_TYPES.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [];

    public function test_every_integer_and_boolean_column_is_cast_on_its_model(): void
    {
        $violations = [];
        $modelsChecked = 0;

        foreach ($this->eloquentModels() as $class) {
            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            $modelsChecked++;

            foreach (Schema::getColumns($table) as $column) {
                $name = $column['name'];
                $type = strtolower($column['type_name'] ?? $column['type']);

                if (! in_array($type, self::STRINGY_TYPES, true)) {
                    continue;
                }

                if (array_key_exists(class_basename($class).'::'.$name, self::EXEMPT)) {
                    continue;
                }

                if (! $model->hasCast($name)) {
                    $violations[] = sprintf('%s::$%s (%s %s.%s)', class_basename($class), $name, $type, $table, $name);
                }
            }
        }

        // Sanity check: if discovery silently returned nothing, the assertion below would
        // pass vacuously and the guard would be worthless.
        $this->assertGreaterThan(50, $modelsChecked, 'Model discovery found suspiciously few models.');

        $this->assertSame([], $violations, sprintf(
            '%d integer/boolean column(s) are not cast on their model. On pdo_sqlsrv these read back as strings, '.
            "so any === / !== / in_array(..., true) / pluck() against them breaks silently in production.\n\n%s\n\n".
            "Fix: add the column to the model's casts() (integer or boolean).",
            count($violations),
            implode("\n", $violations),
        ));
    }

    /**
     * Every concrete Eloquent model under app/Models (including app/Models/StaffPick).
     *
     * @return array<int, class-string<Model>>
     */
    private function eloquentModels(): array
    {
        $models = [];

        foreach ((array) glob(app_path('Models').'/{,StaffPick/}*.php', GLOB_BRACE) as $file) {
            $relative = str_replace([app_path().'/', '.php'], '', $file);
            $class = 'App\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }
}
