# StaffPick — Next.js 15 production image for Railway (the `app` service).
# Multi-stage: deps -> build -> runner. Uses Next's standalone output.
#
# The `db` service on Railway is the stock mcr.microsoft.com/azure-sql-edge
# image — no Dockerfile needed for that one (see docs/railway-deploy.md).

# ── deps ─────────────────────────────────────────────────────────────────────
FROM node:24-slim AS deps
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@10.33.0 --activate
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

# ── build ────────────────────────────────────────────────────────────────────
FROM node:24-slim AS build
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@10.33.0 --activate
COPY --from=deps /app/node_modules ./node_modules
COPY . .
# Prisma client is gitignored — generate it inside the image.
RUN pnpm prisma generate
# next build needs SOME DATABASE_URL present at module-eval time (lib/prisma.ts
# constructs the adapter at import). It does NOT connect during build — a
# syntactically valid placeholder is enough; Railway injects the real value
# at runtime.
ENV DATABASE_URL="sqlserver://placeholder:1433;database=build;user=sa;password=build;encrypt=true;trustServerCertificate=true"
RUN pnpm build

# ── runner ───────────────────────────────────────────────────────────────────
FROM node:24-slim AS runner
WORKDIR /app
ENV NODE_ENV=production
RUN corepack enable && corepack prepare pnpm@10.33.0 --activate

# Standalone server + static assets + public dir
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public

# Prisma schema, migrations, generated client, seed + config — needed so
# `prisma migrate deploy` and the seed can run from inside the container.
COPY --from=build /app/prisma ./prisma
COPY --from=build /app/prisma.config.ts ./prisma.config.ts
COPY --from=build /app/node_modules/prisma ./node_modules/prisma
COPY --from=build /app/node_modules/@prisma ./node_modules/@prisma
COPY --from=build /app/node_modules/.bin/prisma ./node_modules/.bin/prisma

EXPOSE 3000
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

CMD ["node", "server.js"]
