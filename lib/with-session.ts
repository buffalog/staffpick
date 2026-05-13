import { auth } from "@/auth";
import { withTenantContext } from "@/lib/tenant-context";

export type SessionContext = {
  userId: string;
  tenantId: string;
  email: string;
  roles: ReadonlyArray<string>;
};

/**
 * Wraps a callback in an AsyncLocalStorage tenant context derived from the
 * current NextAuth session. Throws if the request is unauthenticated or the
 * user has no tenant (platform admins must use prismaBase or set
 * `bypassTenantScope` explicitly).
 */
export async function withSession<T>(
  fn: (ctx: SessionContext) => Promise<T>,
): Promise<T> {
  const session = await auth();
  if (!session?.user?.id) {
    throw new Error("Authenticated session required.");
  }
  if (!session.user.tenantId) {
    throw new Error(
      "Tenant-scoped session required (user has no tenant_id — platform admin?).",
    );
  }
  const ctx: SessionContext = {
    userId: session.user.id,
    tenantId: session.user.tenantId,
    email: session.user.email ?? "",
    roles: session.user.roles ?? [],
  };
  return withTenantContext(
    { tenantId: ctx.tenantId, userId: ctx.userId },
    () => fn(ctx),
  );
}
