<?php

namespace App\Http\Controllers\StaffPick;

use App\Http\Controllers\Controller;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderPhoto;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a provider's profile photo from its VARBINARY(MAX) BLOB. This asset is fetched far
 * more often than a credential document — every card in every list view — so it is cached
 * aggressively: a versioned URL (?v=updated_at, minted by the avatar component) lets the
 * response stay cacheable for a week while a replacement still busts the cache, and an ETag
 * lets a revalidation return 304 WITHOUT ever reading the multi-megabyte BLOB.
 *
 * Authorization mirrors the upload gate (Provider::isPhotoAccessibleBy): staff/hr/admin/
 * super-admin in the tenant, or the provider viewing their own record.
 */
class ProviderPhotoController extends Controller
{
    public function show(Request $request, Provider $provider): Response
    {
        abort_unless($provider->isPhotoAccessibleBy(auth()->user()), 403);

        // Metadata only — the content BLOB is deliberately excluded here.
        $photo = ProviderPhoto::query()
            ->where('provider_id', $provider->id)
            ->select(['id', 'mime_type', 'file_size', 'updated_at'])
            ->first();

        abort_if($photo === null, 404);

        $etag = '"'.md5($provider->id.':'.($photo->updated_at?->getTimestamp() ?? 0).':'.$photo->file_size).'"';

        $response = response('', Response::HTTP_OK, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ])->setEtag($etag);

        // Revalidation hit — 304, and we never touch the BLOB.
        if ($response->isNotModified($request)) {
            return $response;
        }

        $bytes = $photo->readContent();
        abort_if($bytes === null, 404);

        return $response
            ->setContent($bytes)
            ->header('Content-Length', (string) strlen($bytes));
    }
}
