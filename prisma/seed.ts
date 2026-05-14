import bcrypt from "bcryptjs";
import { Secret, TOTP } from "otpauth";
import { prismaBase } from "../lib/prisma";
import { ICD10_CODES } from "./seed-data/icd10";
import { ASSESSMENT_MEASURES } from "./seed-data/assessment-measures";

const PASSWORD = "LocalDev_Pa55word!";

// Fixed TOTP secret for the E2E user only — never used by real users.
// Allows Playwright tests to generate valid codes deterministically.
export const E2E_TOTP_SECRET = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP";
const E2E_EMAIL = "e2e@staffpick.local";

function newTotpSecret(): string {
  return new Secret({ size: 20 }).base32;
}

function totpProvisioningUri(secret: string, label: string): string {
  return new TOTP({
    issuer: "StaffPick",
    label,
    algorithm: "SHA1",
    digits: 6,
    period: 30,
    secret,
  }).toString();
}

async function main() {
  console.log("→ Seeding StaffPick local dev DB");
  const password_hash = await bcrypt.hash(PASSWORD, 10);

  // ─── Platform admin (no tenant) ─────────────────────────────────────────────
  const adminTotpSecret = newTotpSecret();
  await prismaBase.user.upsert({
    where: { email: "admin@staffpick.local" },
    update: {},
    create: {
      email: "admin@staffpick.local",
      name: "Platform Admin",
      password_hash,
      totp_secret: adminTotpSecret,
      totp_enabled: true,
      email_verified: new Date(),
      roles: { create: [{ role: "PlatformAdmin" }] },
    },
  });

  // ─── FCTS tenant ────────────────────────────────────────────────────────────
  const fcts = await prismaBase.tenant.upsert({
    where: { slug: "fcts" },
    update: {},
    create: {
      name: "First Class Therapy Solutions",
      slug: "fcts",
      active: true,
    },
  });

  // Tenant labels — therapy-vertical mappings
  const labels = [
    ["Subject", "Patient"],
    ["Provider", "Clinician"],
    ["Source", "Agency"],
    ["CareGiver", "Care-giver"],
    ["IntakeRequest", "Referral"],
    ["ResolutionPlan", "Treatment Plan"],
    ["Service", "Therapy Session"],
    ["Assessment", "Evaluation"],
  ];
  for (const [entity, label] of labels) {
    await prismaBase.tenantLabel.upsert({
      where: { tenant_id_entity: { tenant_id: fcts.id, entity } },
      update: { label },
      create: { tenant_id: fcts.id, entity, label },
    });
  }

  // Tenant settings — Phase 2 intake-review gate ON, default everywhere
  const settings = [
    ["intake_review_gate_enabled", "true"],
    ["default_currency", '"USD"'],
    ["matching_weight_availability", "0.6"],
    ["matching_weight_proximity", "0.4"],
  ];
  for (const [key, value] of settings) {
    await prismaBase.tenantSetting.upsert({
      where: { tenant_id_key: { tenant_id: fcts.id, key } },
      update: { value },
      create: { tenant_id: fcts.id, key, value },
    });
  }

  // Service rates (cents)
  const rates = [
    ["PT-EVAL", "PT Initial Evaluation", 18000],
    ["PT-VISIT", "PT Treatment Visit", 9500],
    ["OT-EVAL", "OT Initial Evaluation", 17500],
    ["OT-VISIT", "OT Treatment Visit", 9000],
    ["SLP-EVAL", "SLP Initial Evaluation", 17000],
    ["SLP-VISIT", "SLP Treatment Visit", 9000],
  ] as const;
  for (const [code, description, rate_cents] of rates) {
    await prismaBase.tenantServiceRate.upsert({
      where: {
        tenant_id_service_code_effective_at: {
          tenant_id: fcts.id,
          service_code: code,
          effective_at: new Date("2026-01-01"),
        },
      },
      update: { rate_cents, description },
      create: {
        tenant_id: fcts.id,
        service_code: code,
        description,
        rate_cents,
        effective_at: new Date("2026-01-01"),
      },
    });
  }

  // ─── E2E test user (Tenant Staff with fixed TOTP secret) ────────────────────
  await prismaBase.user.upsert({
    where: { email: E2E_EMAIL },
    update: { totp_secret: E2E_TOTP_SECRET, totp_enabled: true },
    create: {
      email: E2E_EMAIL,
      name: "E2E Test User",
      password_hash,
      totp_secret: E2E_TOTP_SECRET,
      totp_enabled: true,
      email_verified: new Date(),
      tenant_id: fcts.id,
      roles: { create: [{ role: "TenantStaff" }] },
    },
  });
  const e2eUser = await prismaBase.user.findUniqueOrThrow({ where: { email: E2E_EMAIL } });
  await prismaBase.tenantStaff.upsert({
    where: { user_id: e2eUser.id },
    update: { role_title: "E2E Test User" },
    create: {
      tenant_id: fcts.id,
      user_id: e2eUser.id,
      role_title: "E2E Test User",
      active: true,
    },
  });

  // ─── Tenant Staff (Angela, Tena, Gregg) ─────────────────────────────────────
  const staffSeeds = [
    { email: "angela.searcy@fcts.local", name: "Angela Searcy", title: "Intake Lead" },
    { email: "tena.stafson@fcts.local", name: "Tena Stafson", title: "Operations" },
    { email: "gregg@fcts.local", name: "Dr. Gregg", title: "Clinical Director" },
  ];
  const staffOutputs: Array<{ email: string; totp: string }> = [];
  for (const s of staffSeeds) {
    const totp_secret = newTotpSecret();
    const u = await prismaBase.user.upsert({
      where: { email: s.email },
      update: {},
      create: {
        email: s.email,
        name: s.name,
        password_hash,
        totp_secret,
        totp_enabled: true,
        email_verified: new Date(),
        tenant_id: fcts.id,
        roles: { create: [{ role: "TenantStaff" }] },
      },
    });
    await prismaBase.tenantStaff.upsert({
      where: { user_id: u.id },
      update: { role_title: s.title },
      create: {
        tenant_id: fcts.id,
        user_id: u.id,
        role_title: s.title,
        active: true,
      },
    });
    staffOutputs.push({ email: s.email, totp: totpProvisioningUri(totp_secret, s.email) });
  }

  // ─── Providers (5 clinicians, each with a portal User account) ──────────────
  const providerSeeds = [
    { given: "Maria", family: "Alvarez", specialty: "PT", phone: "561-555-0101", zip: "33401" },
    { given: "Jordan", family: "Patel", specialty: "OT", phone: "561-555-0102", zip: "33444" },
    { given: "Sam", family: "Nguyen", specialty: "SLP", phone: "561-555-0103", zip: "33401" },
    { given: "Riley", family: "Cohen", specialty: "PT", phone: "561-555-0104", zip: "33444" },
    { given: "Quinn", family: "Adebayo", specialty: "OT", phone: "561-555-0105", zip: "33401" },
  ];
  for (const p of providerSeeds) {
    const email = `${p.given.toLowerCase()}.${p.family.toLowerCase()}@providers.local`;
    // Provider portal User account — Provider role uses email-OTP MFA, no TOTP.
    const providerUser = await prismaBase.user.upsert({
      where: { email },
      update: {},
      create: {
        email,
        name: `${p.given} ${p.family}`,
        password_hash,
        totp_enabled: false,
        email_verified: new Date(),
        tenant_id: fcts.id,
        roles: { create: [{ role: "Provider" }] },
      },
    });

    const existing = await prismaBase.provider.findFirst({
      where: { tenant_id: fcts.id, given_name: p.given, family_name: p.family },
    });
    if (existing) {
      if (!existing.user_id) {
        await prismaBase.provider.update({
          where: { id: existing.id },
          data: { user_id: providerUser.id },
        });
      }
      continue;
    }
    await prismaBase.provider.create({
      data: {
        tenant_id: fcts.id,
        user_id: providerUser.id,
        given_name: p.given,
        family_name: p.family,
        specialty: p.specialty,
        provider_type: p.specialty,
        phone: p.phone,
        email,
        active: true,
        classification: "1099",
        availability: {
          create: [
            { tenant_id: fcts.id, day_of_week: 1, start_minute: 540, end_minute: 1020 }, // Mon 9-5
            { tenant_id: fcts.id, day_of_week: 3, start_minute: 540, end_minute: 1020 }, // Wed
            { tenant_id: fcts.id, day_of_week: 5, start_minute: 540, end_minute: 1020 }, // Fri
          ],
        },
        addresses: {
          create: [
            {
              tenant_id: fcts.id,
              label: "Home",
              address_line1: "123 Palm Beach Dr",
              city: "West Palm Beach",
              state: "FL",
              postal_code: p.zip,
              is_primary: true,
            },
          ],
        },
      },
    });
  }

  // ─── Reference data: ICD-10 codes ───────────────────────────────────────────
  const icd10List = await prismaBase.list.upsert({
    where: { tenant_id_key: { tenant_id: fcts.id, key: "icd10_codes" } },
    update: { label: "ICD-10 Diagnosis Codes" },
    create: {
      tenant_id: fcts.id,
      key: "icd10_codes",
      label: "ICD-10 Diagnosis Codes",
    },
  });
  for (let i = 0; i < ICD10_CODES.length; i++) {
    const code = ICD10_CODES[i];
    await prismaBase.listItem.upsert({
      where: { list_id_value: { list_id: icd10List.id, value: code.value } },
      update: {
        label: code.label,
        display_order: i,
        metadata: JSON.stringify({ category: code.category }),
      },
      create: {
        tenant_id: fcts.id,
        list_id: icd10List.id,
        value: code.value,
        label: code.label,
        display_order: i,
        active: true,
        metadata: JSON.stringify({ category: code.category }),
      },
    });
  }

  // ─── Reference data: AssessmentMeasures + options ───────────────────────────
  for (const m of ASSESSMENT_MEASURES) {
    const measure = await prismaBase.assessmentMeasure.upsert({
      where: { tenant_id_code: { tenant_id: fcts.id, code: m.code } },
      update: {
        label: m.label,
        measure_type: m.measure_type,
        unit: m.unit ?? null,
        min_value: m.min_value ?? null,
        max_value: m.max_value ?? null,
        display_order: m.display_order,
      },
      create: {
        tenant_id: fcts.id,
        code: m.code,
        label: m.label,
        measure_type: m.measure_type,
        unit: m.unit ?? null,
        min_value: m.min_value ?? null,
        max_value: m.max_value ?? null,
        display_order: m.display_order,
        active: true,
      },
    });
    if (m.options) {
      for (let i = 0; i < m.options.length; i++) {
        const opt = m.options[i];
        await prismaBase.assessmentMeasureOption.upsert({
          where: { measure_id_value: { measure_id: measure.id, value: opt.value } },
          update: { label: opt.label, display_order: i },
          create: {
            tenant_id: fcts.id,
            measure_id: measure.id,
            value: opt.value,
            label: opt.label,
            display_order: i,
          },
        });
      }
    }
  }

  // ─── Sources / Agencies (2) ─────────────────────────────────────────────────
  const sourceSeeds = [
    {
      name: "Sunshine Home Health",
      email: "intake@sunshinehh.local",
      phone: "561-555-0200",
      address_line1: "500 Okeechobee Blvd",
      city: "West Palm Beach",
      state: "FL",
      postal_code: "33401",
    },
    {
      name: "Coastal SNF Group",
      email: "referrals@coastalsnf.local",
      phone: "561-555-0201",
      address_line1: "1200 Atlantic Ave",
      city: "Delray Beach",
      state: "FL",
      postal_code: "33444",
    },
  ];
  for (const s of sourceSeeds) {
    const existing = await prismaBase.source.findFirst({
      where: { tenant_id: fcts.id, name: s.name },
    });
    if (existing) continue;
    await prismaBase.source.create({
      data: { tenant_id: fcts.id, ...s, active: true },
    });
  }

  // ─── Output credentials ─────────────────────────────────────────────────────
  console.log("\n✅ Seed complete.\n");
  console.log("─── Login credentials (local dev only) ────────────────────────");
  console.log(`Password for ALL seeded users: ${PASSWORD}\n`);
  console.log(`Platform Admin (no tenant):`);
  console.log(`  email: admin@staffpick.local`);
  console.log(`  TOTP otpauth URI: ${totpProvisioningUri(adminTotpSecret, "admin@staffpick.local")}`);
  console.log("");
  console.log(`FCTS Tenant Staff (sign in at /login):`);
  for (const s of staffOutputs) {
    console.log(`  ${s.email}`);
    console.log(`    TOTP otpauth URI: ${s.totp}`);
  }
  console.log("");
  console.log(`FCTS Providers (sign in at /login/provider — email-OTP, no TOTP):`);
  for (const p of providerSeeds) {
    const email = `${p.given.toLowerCase()}.${p.family.toLowerCase()}@providers.local`;
    console.log(`  ${email}  (${p.specialty})`);
  }
  console.log("");
  console.log("Staff/admin: scan the otpauth:// URIs above with any TOTP app.");
  console.log("Providers: an OTP is emailed at login; check console if");
  console.log("RESEND_API_KEY is unset.\n");
}

main()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error(err);
    process.exit(1);
  });
