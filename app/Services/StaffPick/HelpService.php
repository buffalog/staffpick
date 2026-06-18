<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Backs the in-app help & wiki system. Reads resources/docs/manifest.json, renders
 * the per-role Markdown topics to HTML via League\CommonMark, resolves which role
 * track applies to the current user, and provides full-text search across a role's
 * docs. The content itself is plain Markdown on disk — no database.
 */
class HelpService
{
    public const ROLE_SCHEDULER = 'scheduler';

    public const ROLE_CLINICIAN = 'clinician';

    public const ROLE_REFERRAL_SOURCE = 'referral-source';

    /** @var array<string, mixed>|null */
    private ?array $manifestCache = null;

    /**
     * The whole manifest (all roles).
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $path = $this->docsPath('manifest.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return $this->manifestCache = is_array($decoded) ? $decoded : ['roles' => []];
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return array_keys($this->manifest()['roles'] ?? []);
    }

    public function roleExists(string $role): bool
    {
        return isset($this->manifest()['roles'][$role]);
    }

    /**
     * The structure (label + sections + topics) for one role.
     *
     * @return array<string, mixed>|null
     */
    public function roleManifest(string $role): ?array
    {
        return $this->manifest()['roles'][$role] ?? null;
    }

    public function roleLabel(string $role): string
    {
        return $this->roleManifest($role)['label'] ?? ucfirst($role);
    }

    /**
     * Pick the help track for a user: tenant admins/schedulers get the scheduler
     * track, users with a provider profile get the clinician track, everyone else
     * (including unauthenticated/public) gets the referral-source track.
     */
    public function resolveRoleForUser(?User $user): string
    {
        if (! $user instanceof User) {
            return self::ROLE_REFERRAL_SOURCE;
        }

        $tenant = Filament::getTenant();

        if ($tenant instanceof Tenant) {
            $roles = app(TenantPermissionService::class)->getTenantUserRoles($tenant, $user);

            if (in_array(TenancyPermissionConstants::ROLE_ADMIN, $roles, true)) {
                return self::ROLE_SCHEDULER;
            }
        }

        if (Provider::query()->where('user_id', $user->getKey())->exists()) {
            return self::ROLE_CLINICIAN;
        }

        return self::ROLE_REFERRAL_SOURCE;
    }

    /**
     * Every topic of a role, flattened across sections, each carrying its section title.
     *
     * @return array<int, array{title: string, slug: string, file: string, section: string, page: ?string}>
     */
    public function topics(string $role): array
    {
        $topics = [];

        foreach ($this->roleManifest($role)['sections'] ?? [] as $section) {
            foreach ($section['topics'] ?? [] as $topic) {
                $topics[] = [
                    'title' => $topic['title'] ?? $topic['slug'] ?? '',
                    'slug' => $topic['slug'] ?? '',
                    'file' => $topic['file'] ?? '',
                    'section' => $section['title'] ?? '',
                    'page' => $topic['page'] ?? null,
                ];
            }
        }

        return $topics;
    }

    /**
     * @return array{title: string, slug: string, file: string, section: string, page: ?string}|null
     */
    public function topic(string $role, string $slug): ?array
    {
        foreach ($this->topics($role) as $topic) {
            if ($topic['slug'] === $slug) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * @return array{title: string, slug: string, file: string, section: string, page: ?string}|null
     */
    public function firstTopic(string $role): ?array
    {
        return $this->topics($role)[0] ?? null;
    }

    /**
     * Resolve a topic by the page it is associated with in the manifest (used to wire
     * contextual ? help to a slug from a page key).
     *
     * @return array{title: string, slug: string, file: string, section: string, page: ?string}|null
     */
    public function topicForPage(string $role, string $page): ?array
    {
        foreach ($this->topics($role) as $topic) {
            if ($topic['page'] === $page) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * Rendered HTML + title for a topic, or null if the slug/file is unknown.
     *
     * @return array{title: string, slug: string, html: string}|null
     */
    public function render(string $role, string $slug): ?array
    {
        $topic = $this->topic($role, $slug);

        if ($topic === null) {
            return null;
        }

        $markdown = $this->rawMarkdown($topic['file']);

        if ($markdown === null) {
            return null;
        }

        return [
            'title' => $topic['title'],
            'slug' => $topic['slug'],
            'html' => $this->toHtml($markdown),
        ];
    }

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter()->convert($markdown);
    }

    /**
     * Raw Markdown for a manifest file path (relative to resources/docs), or null.
     */
    public function rawMarkdown(string $file): ?string
    {
        // Guard against path traversal: only allow simple role/topic.md paths.
        if ($file === '' || str_contains($file, '..')) {
            return null;
        }

        $path = $this->docsPath($file);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * Full-text search across a role's docs. A topic matches when every search term
     * appears in its plain-text content or title (AND). Returns a ranked-ish list with
     * a snippet around the first hit.
     *
     * @return array<int, array{title: string, slug: string, section: string, snippet: string}>
     */
    public function search(string $role, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower($query)) ?: []));
        $results = [];

        foreach ($this->topics($role) as $topic) {
            $markdown = $this->rawMarkdown($topic['file']);

            if ($markdown === null) {
                continue;
            }

            $plain = $this->toPlainText($markdown);
            $haystack = mb_strtolower($topic['title'].' '.$plain);

            $matchesAll = true;
            foreach ($terms as $term) {
                if (! str_contains($haystack, $term)) {
                    $matchesAll = false;
                    break;
                }
            }

            if (! $matchesAll) {
                continue;
            }

            $results[] = [
                'title' => $topic['title'],
                'slug' => $topic['slug'],
                'section' => $topic['section'],
                'snippet' => $this->snippet($plain, $terms[0] ?? $query),
            ];
        }

        return $results;
    }

    private function converter(): GithubFlavoredMarkdownConverter
    {
        // GFM so the tables in the docs render; strip raw HTML so docs can't inject markup.
        return new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function toPlainText(string $markdown): string
    {
        $text = strip_tags($this->toHtml($markdown));

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function snippet(string $plain, string $needle, int $radius = 90): string
    {
        $pos = mb_stripos($plain, $needle);

        if ($pos === false) {
            return mb_strimwidth($plain, 0, $radius * 2, '…');
        }

        $start = max(0, $pos - $radius);
        $snippet = mb_substr($plain, $start, $radius * 2);

        return ($start > 0 ? '…' : '').trim($snippet).'…';
    }

    private function docsPath(string $relative = ''): string
    {
        return resource_path('docs'.($relative !== '' ? DIRECTORY_SEPARATOR.$relative : ''));
    }
}
