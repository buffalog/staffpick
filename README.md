# StaffPick

Multi-tenant, industry-agnostic agency lead-placement & case management platform. First tenant: First Class Therapy Solutions (FCTS) — therapy staffing (PT/OT/SLP).

> **Source of intent**: [`docs/discovery-v0.2.md`](docs/discovery-v0.2.md)
> **Architecture**: [`docs/architecture.md`](docs/architecture.md)
> **MVP cuts**: [`docs/mvp-gaps.md`](docs/mvp-gaps.md)
> **Tech debt**: [`docs/tech-debt.md`](docs/tech-debt.md)

## Stack

Next.js 15 (App Router) · TypeScript strict · Tailwind v4 · shadcn/ui · Prisma 7 + `@prisma/adapter-mssql` · Azure SQL · NextAuth v5 (beta) · Resend · Vitest · Playwright

## Prerequisites

- macOS Apple Silicon (or Linux ARM64/x86_64). Windows untested.
- Node.js 24 LTS preferred; 22 LTS works. Node 25 used during early dev but **not the supported runtime** — see `docs/tech-debt.md`.
- pnpm 10+
- Colima + Docker CLI (`brew install colima docker docker-compose`) for the local SQL container.

## Local dev quickstart

```bash
# 1. Clone, install deps
git clone <repo-url>
cd staffpick
pnpm install

# 2. Start the local SQL container (Azure SQL Edge, ARM64-friendly)
colima start --cpu 4 --memory 6 --arch aarch64
docker run -d --name staffpick-db \
  -e "ACCEPT_EULA=Y" \
  -e "MSSQL_SA_PASSWORD=ChangeMe_StrongPwd_123" \
  -p 1433:1433 \
  mcr.microsoft.com/azure-sql-edge:latest

# 3. Wait ~10s for DB to boot, then create the staffpick database
docker exec staffpick-db /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "ChangeMe_StrongPwd_123" -No \
  -Q "CREATE DATABASE staffpick"

# 4. Env vars
cp .env.example .env.local
# Generate AUTH_SECRET / NEXTAUTH_SECRET:
echo "AUTH_SECRET=\"$(openssl rand -base64 32)\"" >> .env.local
echo "NEXTAUTH_SECRET=\"$(openssl rand -base64 32)\"" >> .env.local

# 5. Generate Prisma client, apply migrations, seed
pnpm prisma generate
pnpm prisma migrate dev       # Phase 1+ once migrations exist
pnpm tsx prisma/seed.ts        # Phase 1+

# 6. Run the dev server
pnpm dev
# → http://localhost:3000
```

## Scripts

| Script | What it does |
|---|---|
| `pnpm dev` | Next.js dev server on :3000 |
| `pnpm build` | Production build |
| `pnpm start` | Run the production build |
| `pnpm lint` | ESLint |
| `pnpm typecheck` | `tsc --noEmit` |
| `pnpm test` | Vitest (unit) |
| `pnpm test:watch` | Vitest watch mode |
| `pnpm db:generate` | Regenerate Prisma client |
| `pnpm db:format` | Format `schema.prisma` |
| `pnpm db:validate` | Validate `schema.prisma` |

## Login flow (Phase 1+ — not implemented at Phase 0 closeout)

1. Visit `/login`.
2. Enter email → magic link sent via Resend, **or** email + password.
3. For Tenant Staff: TOTP challenge after password.
4. Successful auth lands on the tenant-scoped dashboard (`/(dashboard)`).
5. Session middleware injects `tenant_id` into every Prisma query via `lib/tenant-context.ts`.

Seed data (Phase 1):
- 1 platform admin
- FCTS tenant
- Tenant Staff: Angela Searcy, Tena Stafson, Dr. Gregg
- 5 seeded Providers, 2 Source/Agency records

## Project layout

See `docs/architecture.md` § Folder layout.

## Stopping the local DB

```bash
docker stop staffpick-db
docker rm staffpick-db          # if you want a fresh DB next start
colima stop                     # to release the VM
```
