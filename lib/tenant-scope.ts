/**
 * Pure helpers for tenant scoping. No Prisma imports — safe to import from
 * unit tests without triggering DB-client construction.
 */

/**
 * Models with non-nullable `tenant_id`. Reads auto-filter by tenant_id; writes
 * inject tenant_id on create-data. Models with nullable tenant_id (User,
 * UserActivityLog) and tenant-less joins (UserRole) are intentionally excluded.
 */
export const TENANT_SCOPED_MODELS: ReadonlySet<string> = new Set([
  "TenantLabel",
  "TenantSetting",
  "TenantServiceRate",
  "TenantStaff",
  "Subject",
  "SubjectNotes",
  "Provider",
  "ProviderAddress",
  "ProviderAvailability",
  "Source",
  "AgencyContact",
  "CareGiver",
  "IntakeRequest",
  "IntakeRequestTenantStaff",
  "IntakeRequestProvider",
  "IntakeRequestDiagnosis",
  "IntakeRequestCareGiver",
  "ResolutionPlan",
  "Assessment",
  "AssessmentMeasure",
  "AssessmentMeasureOption",
  "IntakeRequestAssessmentMeasureResponse",
  "Service",
  "Invoice",
  "NotificationLog",
  "List",
  "ListItem",
  "CaseMessage",
]);

const READ_OPS = new Set([
  "findFirst",
  "findFirstOrThrow",
  "findMany",
  "count",
  "aggregate",
  "groupBy",
]);

const WRITE_FILTER_OPS = new Set([
  "update",
  "updateMany",
  "delete",
  "deleteMany",
]);

/**
 * Returns a new args object with `tenant_id` injected per Prisma operation
 * semantics. Throws on findUnique/findUniqueOrThrow against a scoped model
 * (Prisma's findUnique signature does not accept arbitrary where conditions,
 * so callers must switch to findFirst).
 */
export function applyTenantScope(
  model: string,
  operation: string,
  args: unknown,
  tenantId: string,
): unknown {
  if (!TENANT_SCOPED_MODELS.has(model)) return args;
  if (operation === "findUnique" || operation === "findUniqueOrThrow") {
    throw new Error(
      `${model}.${operation} is not auto-scoped — use findFirst with explicit tenant_id, or set bypassTenantScope.`,
    );
  }
  const a = { ...((args as object) ?? {}) } as Record<string, unknown>;

  if (READ_OPS.has(operation) || WRITE_FILTER_OPS.has(operation)) {
    a.where = { ...((a.where as object) ?? {}), tenant_id: tenantId };
  }
  if (operation === "create") {
    a.data = { ...((a.data as object) ?? {}), tenant_id: tenantId };
  }
  if (operation === "createMany") {
    const data = a.data as unknown;
    const arr = Array.isArray(data) ? data : [data];
    a.data = arr.map((d) => ({ ...((d as object) ?? {}), tenant_id: tenantId }));
  }
  if (operation === "upsert") {
    a.where = { ...((a.where as object) ?? {}), tenant_id: tenantId };
    a.create = { ...((a.create as object) ?? {}), tenant_id: tenantId };
  }
  return a;
}
