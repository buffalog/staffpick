import { prismaBase } from "@/lib/prisma";
import { TENANT_SCOPED_MODELS, applyTenantScope } from "@/lib/tenant-scope";
import { getTenantContext } from "@/lib/tenant-storage";
import { auditExtension } from "@/lib/audit";

export {
  type TenantContext,
  getTenantContext,
  getTenantId,
  withTenantContext,
} from "@/lib/tenant-storage";

/**
 * Tenant-scoped + audited Prisma client. App code MUST import this, not
 * `prismaBase`.
 *
 * Layered extensions (applied in order):
 *   1. tenant-scope — injects tenant_id into queries on TENANT_SCOPED_MODELS.
 *   2. audit-log    — writes UserActivityLog for mutations on any model and
 *                     for reads on PHI-bearing models.
 *
 * Notes:
 *   - On scoped models, `findUnique`/`findUniqueOrThrow` are NOT auto-scoped.
 *     Use `findFirst` with explicit tenant_id, or call inside
 *     `withTenantContext({ tenantId, bypassTenantScope: true }, fn)`.
 *   - For platform-admin/seed paths, set `bypassTenantScope: true` or use
 *     `prismaBase` directly.
 */
export const prisma = prismaBase
  .$extends({
    name: "tenant-scope",
    query: {
      $allModels: {
        async $allOperations({ model, operation, args, query }) {
          if (!model || !TENANT_SCOPED_MODELS.has(model)) {
            return query(args);
          }
          const ctx = getTenantContext();
          if (ctx?.bypassTenantScope) {
            return query(args);
          }
          if (!ctx?.tenantId) {
            throw new Error(
              `Tenant-scoped query ${model}.${operation} called without tenant context.`,
            );
          }
          const scoped = applyTenantScope(model, operation, args, ctx.tenantId);
          return query(scoped as typeof args);
        },
      },
    },
  })
  .$extends(auditExtension);

export type ExtendedPrismaClient = typeof prisma;
