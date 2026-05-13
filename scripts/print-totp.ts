import { TOTP } from "otpauth";
import { prismaBase } from "../lib/prisma";

async function main() {
  const users = await prismaBase.user.findMany({
    where: { totp_enabled: true },
    select: { email: true, totp_secret: true, tenant_id: true, roles: { select: { role: true } } },
    orderBy: { email: "asc" },
  });
  const now = new Date();
  const periodSec = 30;
  const secsLeft = periodSec - (Math.floor(now.getTime() / 1000) % periodSec);

  console.log(`\n  Current TOTP codes (valid ~${secsLeft}s before rotation):\n`);
  for (const u of users) {
    if (!u.totp_secret) continue;
    const totp = new TOTP({
      issuer: "StaffPick",
      label: u.email,
      algorithm: "SHA1",
      digits: 6,
      period: periodSec,
      secret: u.totp_secret,
    });
    const roles = u.roles.map((r) => r.role).join(",");
    console.log(`    ${u.email.padEnd(34)}  ${totp.generate()}   (${roles})`);
  }
  console.log("");
}

main().then(() => process.exit(0)).catch((e) => {
  console.error(e);
  process.exit(1);
});
