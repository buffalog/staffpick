import { prismaBase } from "@/lib/prisma";
import { getTenantContext } from "@/lib/tenant-storage";
import type { AuditAction } from "@/lib/enums";

/**
 * Models containing PHI. Reads against these models produce a "Read" audit
 * event. List drives the audit extension below.
 */
const PHI_MODELS: ReadonlySet<string> = new Set([
  "Subject",
  "SubjectNotes",
  "Assessment",
  "AssessmentMeasure",
  "AssessmentMeasureOption",
  "IntakeRequestAssessmentMeasureResponse",
  "ResolutionPlan",
  "Service",
  "IntakeRequest",
]);

const MUTATION_OPS = new Set([
  "create",
  "createMany",
  "update",
  "updateMany",
  "upsert",
  "delete",
  "deleteMany",
]);

const READ_OPS = new Set([
  "findFirst",
  "findFirstOrThrow",
  "findUnique",
  "findUniqueOrThrow",
  "findMany",
  "count",
  "aggregate",
  "groupBy",
]);

const OP_TO_ACTION: Record<string, AuditAction> = {
  create: "Create",
  createMany: "Create",
  update: "Update",
  updateMany: "Update",
  upsert: "Update",
  delete: "Delete",
  deleteMany: "Delete",
};

/**
 * Direct audit writer. Use for events not covered by the Prisma extension —
 * login, logout, export, custom domain events.
 */
export async function writeAudit(opts: {
  action: AuditAction;
  entity_type?: string;
  entity_id?: string;
  metadata?: Record<string, unknown>;
  user_id?: string | null;
  tenant_id?: string | null;
  ip?: string;
  user_agent?: string;
}): Promise<void> {
  const ctx = getTenantContext();
  try {
    await prismaBase.userActivityLog.create({
      data: {
        action: opts.action,
        entity_type: opts.entity_type,
        entity_id: opts.entity_id,
        user_id: opts.user_id ?? ctx?.userId ?? null,
        tenant_id: opts.tenant_id ?? ctx?.tenantId ?? null,
        ip: opts.ip,
        user_agent: opts.user_agent,
        metadata: opts.metadata ? JSON.stringify(opts.metadata) : null,
      },
    });
  } catch (err) {
    // Audit must never block the user-facing operation. Log and continue;
    // ops should monitor stderr for audit-write failures.
    console.error("[audit] writeAudit failed", err);
  }
}

/**
 * Best-effort entity-id extractor. Works for create/update/delete with
 * { where: { id } } or { data: { id } }; returns undefined otherwise.
 */
function extractEntityId(args: unknown): string | undefined {
  const a = args as { where?: { id?: unknown }; data?: { id?: unknown } } | undefined;
  const fromWhere = a?.where?.id;
  if (typeof fromWhere === "string") return fromWhere;
  const fromData = a?.data?.id;
  if (typeof fromData === "string") return fromData;
  return undefined;
}

/**
 * Prisma client extension that auto-writes UserActivityLog entries for:
 * - Every mutation on a tenant-scoped model (Create / Update / Delete).
 * - Every read against a PHI-bearing model (Read).
 *
 * The extension fires AFTER the user query completes successfully. Failures
 * inside the user query short-circuit before audit, which is intentional:
 * we only audit operations that actually happened.
 */
export const auditExtension = {
  name: "audit-log",
  query: {
    $allModels: {
      async $allOperations({
        model,
        operation,
        args,
        query,
      }: {
        model: string;
        operation: string;
        args: unknown;
        query: (args: unknown) => Promise<unknown>;
      }): Promise<unknown> {
        const result = await query(args);
        if (!model) return result;
        const ctx = getTenantContext();
        // Skip auditing on UserActivityLog itself to prevent infinite loop.
        if (model === "UserActivityLog") return result;

        if (MUTATION_OPS.has(operation)) {
          await writeAudit({
            action: OP_TO_ACTION[operation] ?? "Update",
            entity_type: model,
            entity_id: extractEntityId(args),
            metadata: { operation },
            user_id: ctx?.userId,
            tenant_id: ctx?.tenantId,
          });
        } else if (READ_OPS.has(operation) && PHI_MODELS.has(model)) {
          const count = Array.isArray(result) ? result.length : result ? 1 : 0;
          await writeAudit({
            action: "Read",
            entity_type: model,
            entity_id: extractEntityId(args),
            metadata: { operation, record_count: count },
            user_id: ctx?.userId,
            tenant_id: ctx?.tenantId,
          });
        }

        return result;
      },
    },
  },
} as const;
