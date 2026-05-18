// Creates (or rotates the password for) a TenantStaff user scoped to one
// tenant. Generates a fresh random password, bcrypt-hashes it, upserts the
// User + TenantStaff role + TenantStaff record, and prints the password
// ONCE to stdout. Hand the password off out-of-band; the recipient enrolls
// a passkey at /dashboard/account on first login.
//
// Usage (against Railway production DB — DATABASE_URL must point at the
// Railway TCP-proxy connection string):
//
//   DATABASE_URL='sqlserver://…' \
//     pnpm tsx scripts/create-tenant-staff.ts \
//     --email judd@octopusinc.com \
//     --name "Judd Kussrow" \
//     --tenant fcts \
//     --title "Partner"
//
// Re-running for the same email rotates the password (idempotent upsert).
import { randomBytes } from "node:crypto";
import bcrypt from "bcryptjs";
import { prismaBase } from "../lib/prisma";

function arg(name: string): string | undefined {
  const ix = process.argv.indexOf(`--${name}`);
  return ix >= 0 ? process.argv[ix + 1] : undefined;
}

function generatePassword(): string {
  return randomBytes(18).toString("base64url");
}

async function main() {
  const email = arg("email");
  const name = arg("name");
  const tenantSlug = arg("tenant");
  const title = arg("title");
  if (!email || !name || !tenantSlug || !title) {
    console.error(
      "Usage: --email <addr> --name <full name> --tenant <slug> --title <role title>",
    );
    process.exit(1);
  }

  const tenant = await prismaBase.tenant.findUnique({
    where: { slug: tenantSlug },
  });
  if (!tenant) {
    console.error(`Tenant with slug "${tenantSlug}" not found.`);
    process.exit(1);
  }

  const password = generatePassword();
  const password_hash = await bcrypt.hash(password, 10);

  const user = await prismaBase.user.upsert({
    where: { email },
    update: { name, password_hash, active: true, tenant_id: tenant.id },
    create: {
      email,
      name,
      password_hash,
      email_verified: new Date(),
      active: true,
      tenant_id: tenant.id,
    },
  });

  await prismaBase.userRole.upsert({
    where: { user_id_role: { user_id: user.id, role: "TenantStaff" } },
    update: {},
    create: { user_id: user.id, role: "TenantStaff" },
  });

  await prismaBase.tenantStaff.upsert({
    where: { user_id: user.id },
    update: { role_title: title, tenant_id: tenant.id, active: true },
    create: {
      tenant_id: tenant.id,
      user_id: user.id,
      role_title: title,
      active: true,
    },
  });

  console.log("");
  console.log("  TenantStaff created / rotated");
  console.log("  ─────────────────────────────");
  console.log(`  tenant:   ${tenant.name} (${tenant.slug})`);
  console.log(`  email:    ${email}`);
  console.log(`  name:     ${name}`);
  console.log(`  title:    ${title}`);
  console.log(`  password: ${password}`);
  console.log("");
  console.log("  Hand off out-of-band. Recipient enrolls a passkey at");
  console.log("  /dashboard/account on first login.");
  console.log("");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prismaBase.$disconnect());
