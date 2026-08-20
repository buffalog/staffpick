<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

/**
 * Guard against the missing-$casts family of bugs.
 *
 * Under pdo_sqlsrv these casts were load-bearing: integer-family and bit columns came back
 * as PHP *strings*, so an un-cast attribute broke every strict comparison in production
 * while passing locally. pdo_pgsql returns native ints and bools, so that specific trap is
 * gone — but the invariant is still worth asserting cheaply. pluck() bypasses casts
 * regardless of driver, and a declared cast is what documents a column's intended PHP type.
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
     * Postgres internal type names, as Schema::getColumns() reports them — int8/int4/int2
     * and bool, NOT the bigint/integer/smallint/boolean spellings. Getting this wrong makes
     * the whole guard match nothing and pass vacuously, which is precisely what happened on
     * the SQL Server -> Postgres swap: the list still held ['int','bigint','smallint',
     * 'tinyint','bit'] and covered exactly zero columns. Hence the coverage assertion below.
     *
     * numeric/decimal and money columns are deliberately absent: they are not
     * integer-family, the guard must not push them toward an integer cast, and their
     * existing decimal:N casts stay as they are.
     *
     * @var array<int, string>
     */
    private const INTEGER_AND_BOOLEAN_TYPES = ['int8', 'int4', 'int2', 'bool'];

    /**
     * Deliberate per-column exemptions, as `Model::column => why`. Empty on purpose — every
     * integer/boolean column in the schema is currently cast. Add an entry here (with a
     * reason) rather than silently narrowing INTEGER_AND_BOOLEAN_TYPES.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [];

    public function test_every_integer_and_boolean_column_is_cast_on_its_model(): void
    {
        $violations = [];
        $modelsChecked = 0;
        $columnsChecked = 0;

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

                if (! in_array($type, self::INTEGER_AND_BOOLEAN_TYPES, true)) {
                    continue;
                }

                $columnsChecked++;

                if (array_key_exists(class_basename($class).'::'.$name, self::EXEMPT)) {
                    continue;
                }

                if (! $model->hasCast($name)) {
                    $violations[] = sprintf('%s::$%s (%s %s.%s)', class_basename($class), $name, $type, $table, $name);
                }
            }
        }

        // Sanity checks: if discovery silently returned nothing, or if the type names stopped
        // matching the engine's spelling, the assertion below would pass vacuously and the
        // guard would be worthless. The second one is not hypothetical — see the note on
        // INTEGER_AND_BOOLEAN_TYPES.
        $this->assertGreaterThan(50, $modelsChecked, 'Model discovery found suspiciously few models.');
        $this->assertGreaterThan(200, $columnsChecked, sprintf(
            'Only %d integer/boolean columns matched. INTEGER_AND_BOOLEAN_TYPES no longer '.
            'matches how this database spells its types, so this guard is inspecting nothing.',
            $columnsChecked,
        ));

        $this->assertSame([], $violations, sprintf(
            '%d integer/boolean column(s) are not cast on their model, so pluck() and any '.
            "=== / !== / in_array(..., true) against them can disagree with the model's type.\n\n%s\n\n".
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
