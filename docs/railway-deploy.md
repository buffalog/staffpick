# StaffPick — Railway deploy runbook

How the live demo is deployed on Railway: two services in one project — a
SQL Server database (`db`) and the Next.js app (`app`).

> **Why this shape**: Railway has no managed SQL Server. StaffPick's data
> layer is Prisma's `sqlserver` provider, so the `db` service runs the stock
> `mcr.microsoft.com/azure-sql-edge` Docker image with a volume for
> persistence. Azure SQL Edge is **EOL'd by Microsoft** — demo-grade, not
> production. The production path is Ed's Azure SQL; migrating there is a
> `DATABASE_URL` swap, no code change. See `docs/tech-debt.md`.

> **This runbook reflects the actual deploy**, not the original plan. Two
> things that bit us and are now baked in below: (1) the Go-based `sqlcmd`
> CLI rejects Azure SQL Edge's self-signed cert ("negative serial number"),
> so DB setup uses the JS `mssql` driver instead — `scripts/railway-db-create.mjs`;
> (2) `railway add --repo` needs Railway's GitHub App connected to the
> account, which we skipped — the app deploys from the local directory via
> `railway up`.

## Live demo coordinates

- **Project**: `staffpick` (`railway open` from the repo dir to view)
- **App URL**: https://app-production-58e5.up.railway.app
- **Services**: `db` (Azure SQL Edge + 50 GB volume at `/var/opt/mssql`),
  `app` (this repo, Dockerfile build)

## Prerequisites

- `railway` CLI v4.44+ (`brew install railway`), logged in (`railway login`).
- Run every `railway` command from `~/projects/staffpick` — the project link
  is stored per-directory in Railway's global config.
- A strong SA password: SQL Server needs 8+ chars, 3 of 4 classes.

## 1. Project + db service

```bash
cd ~/projects/staffpick
railway init -n staffpick                       # creates + links the project

# db service: Azure SQL Edge image + EULA + SA password, one shot
railway add --service db \
  --image mcr.microsoft.com/azure-sql-edge:latest \
  --variables 'ACCEPT_EULA=Y' \
  --variables 'MSSQL_SA_PASSWORD=<SA_PASSWORD>'

# persistence volume — without it the DB is wiped on every redeploy
railway service db                              # link db so `volume add` targets it
railway volume add -m /var/opt/mssql
```

The `db` service deploys automatically. Confirm `railway service status
--service db` shows SUCCESS / Online before continuing (SQL Server takes
~1–2 min to boot).

## 2. TCP proxy (the one dashboard step)

The CLI has no TCP-proxy command. In the dashboard (`railway open`):
`db` service → **Settings → Networking → TCP Proxy** → port **1433**. Railway
gives you a public `host:port` (e.g. `autorack.proxy.rlwy.net:15928`). You
need this to reach the DB from your laptop for setup.

The proxy domain + port are also exposed as service variables:
```bash
railway variables --service db | grep RAILWAY_TCP_PROXY
```

## 3. Create the database, migrate, seed — from your laptop

> **Do not use `sqlcmd`.** The Homebrew Go-based `sqlcmd` fails on Azure SQL
> Edge's self-signed cert. The JS `mssql` driver (what Prisma's adapter uses)
> has no such problem — the helper script and `prisma` both use it.

Substitute `<PROXY_HOST>`, `<PROXY_PORT>`, `<SA_PASSWORD>`:

```bash
# create the staffpick database (Prisma's sqlserver provider won't create it)
DATABASE_HOST=<PROXY_HOST> DATABASE_PORT=<PROXY_PORT> SA_PASSWORD='<SA_PASSWORD>' \
  node scripts/railway-db-create.mjs

# migrations + seed, pointed at the proxy
export DATABASE_URL="sqlserver://<PROXY_HOST>:<PROXY_PORT>;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true"
pnpm prisma migrate deploy
pnpm exec tsx prisma/seed.ts
unset DATABASE_URL
```

The seed prints the platform-admin + tenant-staff TOTP `otpauth://` URIs and
the provider emails — **copy that output**, you need the TOTP URIs to log in.

You can disable the TCP proxy afterward if you want the DB private-only — the
`app` service reaches it over `db.railway.internal` regardless.

## 4. App service

```bash
railway add --service app                       # empty service

# base env vars — DATABASE_URL via stdin (its embedded '=' chars break the
# KEY=VALUE flag parser); AUTH_SECRET as hex (no base64 padding chars)
SECRET=$(openssl rand -hex 32)
echo 'sqlserver://db.railway.internal:1433;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true' \
  | railway variable set --service app --skip-deploys --stdin DATABASE_URL
railway variable set --service app --skip-deploys \
  "AUTH_SECRET=$SECRET" "NEXTAUTH_SECRET=$SECRET" \
  "RESEND_FROM=StaffPick <noreply@staffpick.local>" \
  "NEXT_PUBLIC_TURNSTILE_SITE_KEY=1x00000000000000000000AA" \
  "TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA" \
  "NODE_ENV=production" "LOG_LEVEL=info"

# deploy from the local directory (Railway detects the Dockerfile)
railway up --service app --ci

# public domain
railway domain --service app

# domain-dependent vars — triggers one redeploy
railway variable set --service app \
  "AUTH_URL=https://<domain>" "NEXTAUTH_URL=https://<domain>" \
  "ALLOWED_ORIGINS=https://<domain>"
```

`DATABASE_URL` on the app uses the **internal** host `db.railway.internal:1433`
— not the proxy. `RESEND_API_KEY` is intentionally unset: without it, emails
(provider OTP codes, notifications, invoices) fall back to structured logs,
visible via `railway logs --service app`.

## 5. Verify

```bash
curl -sI https://<domain>/login    # 200
curl -sI https://<domain>/intake   # 200 — proves the app reaches the DB
railway logs --service app         # "Ready", no errors, no UntrustedHost
```

Then log in as a seeded tenant-staff user (`angela.searcy@fcts.local`,
password `LocalDev_Pa55word!`, TOTP from the seed output in step 3) and walk
a case through the lifecycle — see the FigJam operational diagram in the README.

## Ongoing deploys

`railway up --service app --ci` from the repo dir redeploys. For
auto-deploy-on-push, connect Railway's GitHub App to the account
(`railway open` → app service → Settings → connect the `jeremy1745/staffpick`
repo) — then pushes to `main` deploy automatically.

Schema changes: re-enable the TCP proxy, `export DATABASE_URL=<proxy>`, run
`pnpm prisma migrate deploy`.

## Notes / gotchas

- **EOL database** — Azure SQL Edge is unmaintained. Fine for a clickable
  demo; not a production answer. Swap to Ed's Azure SQL when available — it's
  a `DATABASE_URL` change, the Prisma provider is identical.
- **Seed credentials are dev-grade** — `LocalDev_Pa55word!` and the fixed E2E
  TOTP secret ship in `prisma/seed.ts`. Rotate before anything real.
- **Resend without a key** — provider OTP login codes go to `railway logs`.
  Set `RESEND_API_KEY` to deliver them to real inboxes.
- **Memory** — if the `db` service crashloops, it's RAM. SQL Server needs ≥2 GB.
