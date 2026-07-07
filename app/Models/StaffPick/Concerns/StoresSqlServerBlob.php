<?php

namespace App\Models\StaffPick\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Read/write a `content` VARBINARY(MAX) column on Azure SQL for models that store file
 * bytes in the database (credential attachments, provider photos).
 *
 * pdo_sqlsrv can't bind a PHP string straight into VARBINARY(MAX) — the implicit
 * nvarchar->varbinary conversion is disallowed — so bytes travel as a hex string and SQL
 * Server rebuilds them via CONVERT(..., 2). SQL Server specific by design; these tables are
 * Azure-SQL-only. `content` must never be mass-assigned or SELECTed in list/count queries.
 */
trait StoresSqlServerBlob
{
    public function storeContent(string $bytes): void
    {
        DB::update(
            "UPDATE {$this->getTable()} SET content = CONVERT(VARBINARY(MAX), ?, 2) WHERE id = ?",
            [bin2hex($bytes), $this->getKey()],
        );
    }

    public function readContent(): ?string
    {
        $row = DB::selectOne(
            "SELECT CONVERT(VARCHAR(MAX), content, 2) AS hex FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return ($row === null || $row->hex === null) ? null : hex2bin($row->hex);
    }

    public function hasContent(): bool
    {
        $row = DB::selectOne(
            "SELECT DATALENGTH(content) AS len FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return $row !== null && $row->len !== null && (int) $row->len > 0;
    }

    public function clearContent(): void
    {
        DB::update("UPDATE {$this->getTable()} SET content = NULL WHERE id = ?", [$this->getKey()]);
    }
}
