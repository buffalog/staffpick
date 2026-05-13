import { NextResponse, type NextRequest } from "next/server";
import { z } from "zod";
import { prismaBase } from "@/lib/prisma";
import { withTenantContext } from "@/lib/tenant-context";
import { prisma } from "@/lib/tenant-context";

const TENANT_SLUG = "fcts"; // single-tenant MVP

const intakeSchema = z.object({
  turnstileToken: z.string().min(1),
  source: z.object({
    name: z.string().min(1),
    contact_name: z.string().min(1),
    email: z.string().email(),
    phone: z.string().min(7),
  }),
  subject: z.object({
    given_name: z.string().min(1),
    family_name: z.string().min(1),
    date_of_birth: z.string().min(1),
    preferred_language: z.string().optional().default(""),
    email: z.string().email().optional().or(z.literal("")),
    phone: z.string().optional().default(""),
    address_line1: z.string().optional().default(""),
    city: z.string().optional().default(""),
    state: z.string().optional().default(""),
    postal_code: z.string().optional().default(""),
  }),
  caregiver: z
    .object({
      given_name: z.string().min(1),
      family_name: z.string().min(1),
      phone: z.string().optional().default(""),
      relation: z.string().optional().default(""),
    })
    .nullable()
    .optional(),
  diagnoses: z
    .array(
      z.object({
        code: z.string().min(1),
        description: z.string().optional(),
        is_primary: z.boolean().optional(),
      }),
    )
    .min(1),
  requested_service: z.string().min(1),
  schedule_preference: z.string().optional().default(""),
  notes: z.string().optional().default(""),
});

async function verifyTurnstile(token: string, ip?: string): Promise<boolean> {
  const secret = process.env.TURNSTILE_SECRET_KEY;
  if (!secret) return false;
  const form = new URLSearchParams();
  form.set("secret", secret);
  form.set("response", token);
  if (ip) form.set("remoteip", ip);
  const res = await fetch(
    "https://challenges.cloudflare.com/turnstile/v0/siteverify",
    { method: "POST", body: form },
  );
  if (!res.ok) return false;
  const json = (await res.json()) as { success?: boolean };
  return Boolean(json.success);
}

export async function POST(req: NextRequest) {
  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON" }, { status: 400 });
  }

  const parsed = intakeSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "Validation failed", issues: parsed.error.issues },
      { status: 400 },
    );
  }
  const data = parsed.data;

  const ip =
    req.headers.get("cf-connecting-ip") ??
    req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ??
    undefined;
  const captchaOk = await verifyTurnstile(data.turnstileToken, ip);
  if (!captchaOk) {
    return NextResponse.json({ error: "CAPTCHA failed" }, { status: 400 });
  }

  const tenant = await prismaBase.tenant.findUnique({
    where: { slug: TENANT_SLUG },
    include: { settings: true },
  });
  if (!tenant) {
    return NextResponse.json({ error: "Tenant not configured" }, { status: 500 });
  }

  // Determine starting phase from tenant_settings.intake_review_gate_enabled
  const gateSetting = tenant.settings.find(
    (s) => s.key === "intake_review_gate_enabled",
  );
  const gateEnabled = gateSetting ? gateSetting.value === "true" : true;
  const startingPhase = gateEnabled
    ? "Phase2_IntakeReview"
    : "Phase3_MatchingKickoff";

  // Run inside tenant scope so audit + tenant_id injection fire.
  return withTenantContext({ tenantId: tenant.id, ip }, async () => {
    // tenant_id is injected by the tenant-scope extension at runtime, but
    // Prisma's static types still require it. We pass it explicitly here
    // for type-checker satisfaction; the extension validates/overrides.
    const tenant_id = tenant.id;

    // Find or create Source by (tenant_id, name + email).
    let source = await prisma.source.findFirst({
      where: { name: data.source.name, email: data.source.email },
    });
    if (!source) {
      source = await prisma.source.create({
        data: {
          tenant_id,
          name: data.source.name,
          email: data.source.email,
          phone: data.source.phone,
        },
      });
      await prisma.agencyContact.create({
        data: {
          tenant_id,
          source_id: source.id,
          given_name: data.source.contact_name.split(/\s+/)[0] ?? "",
          family_name:
            data.source.contact_name.split(/\s+/).slice(1).join(" ") || "",
          email: data.source.email,
          phone: data.source.phone,
          is_primary: true,
        },
      });
    }

    const subject = await prisma.subject.create({
      data: {
        tenant_id,
        given_name: data.subject.given_name,
        family_name: data.subject.family_name,
        date_of_birth: new Date(data.subject.date_of_birth),
        preferred_language: data.subject.preferred_language || null,
        email: data.subject.email || null,
        phone: data.subject.phone || null,
        address_line1: data.subject.address_line1 || null,
        city: data.subject.city || null,
        state: data.subject.state || null,
        postal_code: data.subject.postal_code || null,
      },
    });

    const request = await prisma.intakeRequest.create({
      data: {
        tenant_id,
        source_id: source.id,
        subject_id: subject.id,
        phase: startingPhase,
        status: "Active",
        ingestion_channel: "webform",
        requested_service: data.requested_service,
        schedule_preference: data.schedule_preference,
        notes: data.notes,
        raw_payload: JSON.stringify(body),
      },
    });

    for (const dx of data.diagnoses) {
      await prisma.intakeRequestDiagnosis.create({
        data: {
          tenant_id,
          request_id: request.id,
          code: dx.code,
          description: dx.description ?? null,
          is_primary: Boolean(dx.is_primary),
        },
      });
    }

    if (data.caregiver) {
      const cg = await prisma.careGiver.create({
        data: {
          tenant_id,
          given_name: data.caregiver.given_name,
          family_name: data.caregiver.family_name,
          phone: data.caregiver.phone,
          relation_to_subject: data.caregiver.relation,
        },
      });
      await prisma.intakeRequestCareGiver.create({
        data: {
          tenant_id,
          request_id: request.id,
          caregiver_id: cg.id,
          relation_type: data.caregiver.relation,
        },
      });
    }

    return NextResponse.json(
      { id: request.id, phase: request.phase },
      { status: 201 },
    );
  });
}
