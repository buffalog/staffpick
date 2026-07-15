<?php

namespace Tests\Feature\StaffPick;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * A QueryException's getMessage() interpolates the query bindings (patient name/notes/address)
 * into the string. The reportable callback in bootstrap/app.php must log a redacted record and
 * suppress the default reporter, so that PHI never lands in laravel.log.
 */
class QueryExceptionLoggingTest extends TestCase
{
    public function test_query_exception_reporting_redacts_the_bindings(): void
    {
        Log::spy();

        // Bindings carry the PHI; getMessage() would interpolate 'Zzyxpatient' into the string.
        $exception = new QueryException(
            'sqlsrv',
            'insert into [sp_subjects] ([last_name]) values (?)',
            ['Zzyxpatient'],
            new \Exception('SQLSTATE[23000]: integrity constraint violation'),
        );

        report($exception);

        // The redacted record was logged: message + sqlstate present, PHI absent, sql parameterized.
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Database query failed'
                    && array_key_exists('sqlstate', $context)
                    && str_contains($context['sql'], '?')
                    && ! str_contains(json_encode($context), 'Zzyxpatient');
            })
            ->once();
    }
}
