import { AsyncLocalStorage } from "node:async_hooks";

export type TenantContext = {
  tenantId: string;
  userId?: string;
  ip?: string;
  user_agent?: string;
  /**
   * When true, tenant scoping is bypassed. NEVER set from request handlers;
   * only from server-startup, seed, or admin-only paths.
   */
  bypassTenantScope?: boolean;
};

const storage = new AsyncLocalStorage<TenantContext>();

export function getTenantContext(): TenantContext | undefined {
  return storage.getStore();
}

export function getTenantId(): string {
  const ctx = storage.getStore();
  if (!ctx?.tenantId) {
    throw new Error(
      "Tenant context required. Wrap the call in withTenantContext().",
    );
  }
  return ctx.tenantId;
}

export function withTenantContext<T>(
  ctx: TenantContext,
  fn: () => Promise<T>,
): Promise<T> {
  return storage.run(ctx, fn);
}
