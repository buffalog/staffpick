# StaffPick — Tech Debt

Running log of choices that work for MVP but should be revisited. Each entry: what, why deferred, what to do, and expected effort.

## Phase 0 entries

### NextAuth v5 beta
- **What**: Locked stack pins `next-auth@beta` (currently 5.0.0-beta.x). v5 has been beta for years; production-grade in practice but officially pre-GA.
- **Why deferred**: v4 → v5 swap loses universal `auth()` method and App-Router-first ergonomics. Bailing to Clerk/WorkOS costs $.
- **Action post-MVP**: Track v5 GA release. Swap pin to GA when available.
- **Effort**: trivial pin update + smoke test.

### Prisma 7 enum support on SQL Server
- **What**: Prisma 7's `sqlserver` connector does not support `enum` blocks (P1012). All enum-typed fields are `String` columns with validation deferred to `lib/enums.ts` (TS string-literal types).
- **Why deferred**: Postgres-style native enums aren't available against SQL Server. App-layer validation is the standard workaround.
- **Action post-MVP**: Build a lightweight enum-validation helper at the Prisma extension layer to enforce allowed values on writes.
- **Effort**: 1 day to add Prisma client middleware + tests.

### Node 25 on dev box vs Prisma's supported matrix
- **What**: Local dev runs Node 25.8.2 (Homebrew default). Prisma 7 officially supports Node 20.19+/22.12+/24.0+ only. Install succeeded with a warning; runtime fine in practice.
- **Why deferred**: Pinning Node 24 LTS requires fnm/nvm/asdf or a downgrade. Friction not justified pre-deploy.
- **Action pre-deploy**: Install Node 24 LTS via fnm. Set `engines.node` in `package.json`. Re-run typecheck + test suite against 24.
- **Effort**: ~30 min.

### Prisma `migrations.adapter` on SQL Server
- **What**: `prisma.config.ts` wires `@prisma/adapter-mssql` for migrate operations. Untested against a live SQL Server until Phase 1.
- **Why deferred**: Azure SQL Edge container only verified pullable; not yet connected to.
- **Action Phase 1**: First migration against Azure SQL Edge container will surface any TLS/encrypt edge cases. If broken, fall back to raw SQL migrations applied via mssql driver.
- **Effort**: 1–2 days worst case.

### shadcn/ui base preset uses Base UI, not Radix
- **What**: `pnpm dlx shadcn init --defaults` selected `base-nova` preset (Base UI). Many shadcn examples in the wild still target Radix.
- **Why deferred**: Base UI is shadcn's current default; not worth fighting.
- **Action ongoing**: When copying component recipes from old shadcn docs/blogs, translate Radix patterns to Base UI equivalents.
- **Effort**: per-component as encountered.

### Inngest deferred; cron via host scheduler
- **What**: Scope decisions allowed "Inngest or cron." Picked cron (Railway scheduler) for MVP.
- **Why deferred**: Inngest adds a service dependency and ~1 day of setup. No durable async retries needed for MVP.
- **Action post-MVP**: If any phase needs durable retries (invoice generation, notification fanout), introduce Inngest.
- **Effort**: 1 day.

### Tailwind v4 + Turbopack disabled
- **What**: Scaffold was invoked with `--no-turbopack`. Tailwind v4 + Turbopack combos have had rough edges; opting out for MVP stability.
- **Why deferred**: Faster dev builds with Turbopack not worth debugging integration issues during build.
- **Action post-MVP**: Re-enable Turbopack once Tailwind v4 ecosystem stabilizes.
- **Effort**: trivial (remove flag, smoke test).

### GitHub remote not yet configured
- **What**: Repo is local-only. `.github/workflows/ci.yml` exists but does not fire until a remote is added and a PR opens.
- **Why deferred**: Jeremy explicitly skipped the remote in Phase 0.
- **Action when ready**: Decide the target (personal account, embr-works org, new org), `gh repo create`, push, verify CI fires.
- **Effort**: ~10 min.

### No HIPAA BAA chase, no SOC2/HITRUST
- **What**: Architectural prerequisites met (TLS, TDE, audit log, RBAC, secrets discipline). Compliance certifications not pursued.
- **Why deferred**: Pre-MVP. Per scope_decisions_locked.
- **Action post-MVP**: BAA with Resend, Azure, Railway. SOC2 only when revenue justifies.
- **Effort**: not engineering effort — vendor paperwork.
