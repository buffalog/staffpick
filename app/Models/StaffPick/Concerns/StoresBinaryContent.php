<?php

namespace App\Models\StaffPick\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Read/write a `content` bytea column for models that store file bytes in the database
 * (credential attachments, provider photos).
 *
 * Bytes travel as a hex string in both directions — decode(?, 'hex') on the way in,
 * encode(content, 'hex') on the way out — which keeps the payload inside an ordinary string
 * bind and avoids any driver-specific large-object or stream handling. Note that Postgres
 * `encode()` emits LOWERCASE hex; never compare the hex representation case-sensitively.
 *
 * `content` must never be mass-assigned or SELECTed in list/count queries: these blobs run
 * to hundreds of kilobytes each, and pulling them into a table listing or a count would drag
 * the whole payload across the wire for rows that only need an id.
 */
trait StoresBinaryContent
{
    public function storeContent(string $bytes): void
    {
        DB::update(
            "UPDATE {$this->getTable()} SET content = decode(?, 'hex') WHERE id = ?",
            [bin2hex($bytes), $this->getKey()],
        );
    }

    public function readContent(): ?string
    {
        $row = DB::selectOne(
            "SELECT encode(content, 'hex') AS hex FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return ($row === null || $row->hex === null) ? null : hex2bin($row->hex);
    }

    public function hasContent(): bool
    {
        $row = DB::selectOne(
            "SELECT octet_length(content) AS len FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return $row !== null && $row->len !== null && (int) $row->len > 0;
    }

    public function clearContent(): void
    {
        DB::update("UPDATE {$this->getTable()} SET content = NULL WHERE id = ?", [$this->getKey()]);
    }
}
