import { describe, expect, it } from "vitest";
import { applyTenantScope } from "./tenant-scope";

const T = "tenant_abc";

describe("applyTenantScope — tenant-scoped models", () => {
  const scoped = "Subject";

  it("injects tenant_id on findMany.where", () => {
    const out = applyTenantScope(scoped, "findMany", { where: { active: true } }, T);
    expect(out).toMatchObject({ where: { active: true, tenant_id: T } });
  });

  it("injects tenant_id even when args.where is undefined", () => {
    const out = applyTenantScope(scoped, "findMany", {}, T);
    expect(out).toMatchObject({ where: { tenant_id: T } });
  });

  it("injects tenant_id on create.data", () => {
    const out = applyTenantScope(scoped, "create", { data: { given_name: "x" } }, T);
    expect(out).toMatchObject({ data: { given_name: "x", tenant_id: T } });
  });

  it("does NOT let create-data override tenant_id with a different value", () => {
    // applyTenantScope's spread puts tenant_id AFTER caller data, so it wins.
    const out = applyTenantScope(
      scoped,
      "create",
      { data: { given_name: "x", tenant_id: "evil_tenant" } },
      T,
    );
    expect((out as { data: { tenant_id: string } }).data.tenant_id).toBe(T);
  });

  it("injects tenant_id on update.where", () => {
    const out = applyTenantScope(
      scoped,
      "update",
      { where: { id: "subj_1" }, data: { given_name: "x" } },
      T,
    );
    expect(out).toMatchObject({ where: { id: "subj_1", tenant_id: T } });
  });

  it("injects tenant_id on delete.where", () => {
    const out = applyTenantScope(scoped, "delete", { where: { id: "subj_1" } }, T);
    expect(out).toMatchObject({ where: { id: "subj_1", tenant_id: T } });
  });

  it("injects tenant_id on every entry in createMany.data", () => {
    const out = applyTenantScope(
      scoped,
      "createMany",
      { data: [{ given_name: "a" }, { given_name: "b" }] },
      T,
    );
    expect((out as { data: Array<{ tenant_id: string }> }).data).toEqual([
      { given_name: "a", tenant_id: T },
      { given_name: "b", tenant_id: T },
    ]);
  });

  it("injects tenant_id on upsert.where and upsert.create", () => {
    const out = applyTenantScope(
      scoped,
      "upsert",
      {
        where: { id: "subj_1" },
        create: { given_name: "x" },
        update: { given_name: "y" },
      },
      T,
    );
    expect(out).toMatchObject({
      where: { id: "subj_1", tenant_id: T },
      create: { given_name: "x", tenant_id: T },
    });
  });

  it("throws on findUnique against a scoped model", () => {
    expect(() =>
      applyTenantScope(scoped, "findUnique", { where: { id: "subj_1" } }, T),
    ).toThrow(/not auto-scoped/);
  });

  it("throws on findUniqueOrThrow against a scoped model", () => {
    expect(() =>
      applyTenantScope(scoped, "findUniqueOrThrow", { where: { id: "subj_1" } }, T),
    ).toThrow(/not auto-scoped/);
  });
});

describe("applyTenantScope — non-scoped models", () => {
  it("leaves User reads untouched (User has nullable tenant_id)", () => {
    const args = { where: { email: "x@y.z" } };
    const out = applyTenantScope("User", "findMany", args, T);
    expect(out).toEqual(args);
  });

  it("leaves UserRole writes untouched", () => {
    const args = { data: { user_id: "u1", role: "PlatformAdmin" } };
    const out = applyTenantScope("UserRole", "create", args, T);
    expect(out).toEqual(args);
  });

  it("allows findUnique on non-scoped models", () => {
    const args = { where: { email: "x@y.z" } };
    expect(() => applyTenantScope("User", "findUnique", args, T)).not.toThrow();
  });
});
