// Creates (or rotates the password for) a PlatformAdmin user.
//
// Generates a fresh random password, bcrypt-hashes it, upserts the User +
// PlatformAdmin role, and prints the password ONCE to stdout. Hand the
// password off out-of-band; the recipient enrolls a passkey at
// /dashboard/account on first login and the password becomes the recovery
// factor only.
//
// Usage (against Railway production DB — DATABASE_URL must point at the
// Railway TCP-proxy connection string):
//
//   DATABASE_URL='sqlserver://…' \
//     pnpm tsx scripts/create-platform-admin.ts \
//     --email judd@octopusinc.com --name "Judd Kussrow"
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
  // 24 chars base64url — ~144 bits of entropy. URL-safe alphabet so the
  // recipient can paste it without worrying about shell-quoting hazards.
  return randomBytes(18).toString("base64url");
}

async function main() {
  const email = arg("email");
  const name = arg("name");
  if (!email || !name) {
    console.error("Usage: --email <addr> --name <full name>");
    process.exit(1);
  }

  const password = generatePassword();
  const password_hash = await bcrypt.hash(password, 10);

  const user = await prismaBase.user.upsert({
    where: { email },
    update: { name, password_hash, active: true },
    create: {
      email,
      name,
      password_hash,
      email_verified: new Date(),
      active: true,
    },
  });

  await prismaBase.userRole.upsert({
    where: { user_id_role: { user_id: user.id, role: "PlatformAdmin" } },
    update: {},
    create: { user_id: user.id, role: "PlatformAdmin" },
  });

  console.log("");
  console.log("  PlatformAdmin created / rotated");
  console.log("  ────────────────────────────────");
  console.log(`  email:    ${email}`);
  console.log(`  name:     ${name}`);
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
