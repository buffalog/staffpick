/**
 * Route security audit. Static heuristic check — not a proof — that every
 * route handler / page / server-action file under `app/` is either:
 *
 *   - public by design (path matches the middleware's public allowlist), or
 *   - protected, and its DB access goes through `withSession` (which enforces
 *     tenant scope + audit) rather than the raw `prismaBase` client.
 *
 * Files that legitimately use `prismaBase` outside `withSession` are
 * allowlisted below with a reason. Exits non-zero on any unexplained
 * violation so it can gate CI.
 *
 *   pnpm tsx scripts/audit-routes.ts
 */
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";

const APP_DIR = join(process.cwd(), "app");

// Mirrors the public-path list in middleware.ts / auth.config.ts.
const PUBLIC_PREFIXES = [
  "/login",
  "/api/auth",
  "/api/intake",
  "/intake",
  "/invoices",
];
const PUBLIC_EXACT = ["/"];

// Files that use `prismaBase` directly on purpose, with the reason. The audit
// passes these; anything else using prismaBase under a protected path fails.
const PRISMA_BASE_ALLOWLIST: Record<string, string> = {
  "app/intake/page.tsx": "public webform — loads tenant config pre-auth",
  "app/api/intake/route.ts": "public webform POST — wraps writes in withTenantContext",
  "app/invoices/[link]/page.tsx": "public magic-link — scoped by external_link slug",
  "app/invoices/[link]/actions.ts": "public magic-link — scoped by external_link slug",
  "app/invoices/[link]/pdf/route.ts": "public magic-link — scoped by external_link slug",
  "app/dashboard/account/page.tsx":
    "Authenticator is auth-layer (no tenant_id); gated by auth() + queried by session userId",
  "app/dashboard/cases/[id]/page.tsx":
    "UserActivityLog is not tenant-scoped; read is tenant-filtered manually inside withSession",
  "app/dashboard/invoices/[id]/payroll.csv/route.ts":
    "scoped queries via withSession; prismaBase only for the UserActivityLog audit write (not tenant-scoped)",
};

type Finding = { file: string; level: "ok" | "warn" | "fail"; note: string };

function walk(dir: string): string[] {
  const out: string[] = [];
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) out.push(...walk(full));
    else if (/(route|page|actions)\.tsx?$/.test(entry)) out.push(full);
  }
  return out;
}

function urlPathFor(rel: string): string {
  // app/dashboard/cases/[id]/page.tsx -> /dashboard/cases/[id]
  let p = rel.replace(/^app/, "").replace(/\/(route|page|actions)\.tsx?$/, "");
  p = p.replace(/\/\([^)]+\)/g, ""); // strip route groups
  return p === "" ? "/" : p;
}

function isPublic(urlPath: string): boolean {
  if (PUBLIC_EXACT.includes(urlPath)) return true;
  return PUBLIC_PREFIXES.some((pre) => urlPath === pre || urlPath.startsWith(pre + "/") || urlPath.startsWith(pre));
}

const findings: Finding[] = [];

for (const abs of walk(APP_DIR)) {
  const rel = relative(process.cwd(), abs);
  const src = readFileSync(abs, "utf8");
  const urlPath = urlPathFor(rel);
  const pub = isPublic(urlPath);

  const usesPrismaBase = /\bprismaBase\b/.test(src);
  const usesWithSession = /\bwithSession\b/.test(src);
  const usesScopedPrisma = /from ["']@\/lib\/tenant-context["']/.test(src);
  const touchesDb = usesPrismaBase || usesScopedPrisma;
  const allowReason = PRISMA_BASE_ALLOWLIST[rel];

  if (!touchesDb) {
    findings.push({ file: rel, level: "ok", note: pub ? "public, no DB" : "protected, no DB" });
    continue;
  }

  if (usesPrismaBase) {
    if (allowReason) {
      findings.push({ file: rel, level: "ok", note: `prismaBase (allowed): ${allowReason}` });
    } else {
      findings.push({
        file: rel,
        level: "fail",
        note: pub
          ? "public file uses prismaBase without an allowlist entry"
          : "protected file uses prismaBase directly — should go through withSession",
      });
    }
    continue;
  }

  // Scoped-prisma path
  if (pub) {
    findings.push({
      file: rel,
      level: "warn",
      note: "public file uses the scoped client — confirm it wraps calls in withTenantContext",
    });
  } else if (usesWithSession) {
    findings.push({ file: rel, level: "ok", note: "protected, DB via withSession" });
  } else {
    findings.push({
      file: rel,
      level: "fail",
      note: "protected file uses the scoped client but not withSession — tenant context may be missing",
    });
  }
}

findings.sort((a, b) => a.file.localeCompare(b.file));
const icon = { ok: "  ok ", warn: " WARN", fail: " FAIL" };
for (const f of findings) console.log(`${icon[f.level]}  ${f.file}  —  ${f.note}`);

const fails = findings.filter((f) => f.level === "fail");
const warns = findings.filter((f) => f.level === "warn");
console.log(
  `\n${findings.length} route files · ${fails.length} fail · ${warns.length} warn`,
);
if (fails.length > 0) {
  console.error("\nRoute audit FAILED — unguarded DB access above.");
  process.exit(1);
}
console.log("Route audit passed.");
