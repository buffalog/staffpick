<?php

namespace App\Models\StaffPick;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A proof-of-credential document stored as a BLOB (VARBINARY(MAX)) in Azure SQL. Inherits
 * tenancy transitively through its provider credential; not directly tenant-scoped.
 *
 * Soft delete is a tombstone (see the migration): the row and metadata persist while the
 * content BLOB is cleared. The `content` column is heavy — never select it for listing or
 * counting; use {@see scopeWithoutContent()} and fetch the bytes only when streaming.
 */
class CredentialAttachment extends Model
{
    use HasFactory, SoftDeletes;

    /** Accepted upload extensions (final list — enforced server-side, not just client accept). */
    public const ACCEPTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'docx', 'doc'];

    /** Max upload size in kilobytes (20 MB). */
    public const MAX_SIZE_KB = 20480;

    /** Metadata columns — everything except the heavy `content` BLOB. */
    private const METADATA_COLUMNS = [
        'id',
        'provider_credential_id',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by_user_id',
        'uploaded_at',
        'deleted_by_user_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $table = 'sp_credential_attachments';

    // `content` is deliberately NOT fillable: pdo_sqlsrv rejects a plain string bound into
    // VARBINARY(MAX), so bytes must go through storeContent()/readContent(), never mass
    // assignment.
    protected $fillable = [
        'provider_credential_id',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by_user_id',
        'uploaded_at',
        'deleted_by_user_id',
    ];

    /** Never leak the BLOB into array/JSON output. */
    protected $hidden = [
        'content',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function providerCredential(): BelongsTo
    {
        return $this->belongsTo(ProviderCredential::class, 'provider_credential_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    /**
     * Select metadata only, omitting the multi-megabyte `content` BLOB. Use for every list
     * and count query; the bytes are fetched separately only when a file is streamed.
     */
    public function scopeWithoutContent(Builder $query): Builder
    {
        return $query->select(self::METADATA_COLUMNS);
    }

    /**
     * Write the BLOB. pdo_sqlsrv can't bind a PHP string into VARBINARY(MAX) (implicit
     * nvarchar->varbinary conversion is disallowed), so the bytes travel as a hex string
     * and SQL Server rebuilds them: CONVERT(VARBINARY(MAX), <hex>, 2). SQL Server specific
     * by design — this table is Azure-SQL-only.
     */
    public function storeContent(string $bytes): void
    {
        DB::update(
            "UPDATE {$this->getTable()} SET content = CONVERT(VARBINARY(MAX), ?, 2) WHERE id = ?",
            [bin2hex($bytes), $this->getKey()],
        );
    }

    /**
     * Read the BLOB back as raw bytes. Fetched as a hex string (CONVERT style 2) to avoid
     * pdo_sqlsrv returning VARBINARY(MAX) as a stream or mangling its encoding; hex2bin
     * restores the exact original bytes. Null when never set or cleared on soft delete.
     */
    public function readContent(): ?string
    {
        $row = DB::selectOne(
            "SELECT CONVERT(VARCHAR(MAX), content, 2) AS hex FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return ($row === null || $row->hex === null) ? null : hex2bin($row->hex);
    }

    /** Whether the BLOB still holds bytes (false once tombstoned). */
    public function hasContent(): bool
    {
        $row = DB::selectOne(
            "SELECT DATALENGTH(content) AS len FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        return $row !== null && $row->len !== null && (int) $row->len > 0;
    }

    /** Clear the BLOB to reclaim storage (binding NULL needs no binary conversion). */
    public function clearContent(): void
    {
        DB::update("UPDATE {$this->getTable()} SET content = NULL WHERE id = ?", [$this->getKey()]);
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'heic'], true);
    }

    public function isPdf(): bool
    {
        return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION)) === 'pdf';
    }

    /** Images and PDFs preview inline in the browser; DOC/DOCX open raw in a new tab. */
    public function isInlinePreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }
}
