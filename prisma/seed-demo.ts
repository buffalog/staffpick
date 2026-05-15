import { randomUUID } from "node:crypto";
import { prismaBase } from "../lib/prisma";

/**
 * Demo scenario seed — 3 cases parked at different lifecycle stages so a
 * walkthrough has something live to show at each major step:
 *
 *   DEMO-1  Phase 2  — fresh referral sitting in the Intake Inbox
 *   DEMO-2  Phase 9  — active case: provider matched, plan documented, visits logged
 *   DEMO-3  Phase 12 — invoice generated + sent, awaiting Source payment
 *
 * Idempotent: every run wipes prior demo data (Subjects tagged DEMO-*) and
 * rebuilds it. Run AFTER the base seed (`prisma/seed.ts`).
 */

async function resetDemoData(tenantId: string) {
  const demoSubjects = await prismaBase.subject.findMany({
    where: { tenant_id: tenantId, external_id: { startsWith: "DEMO-" } },
    select: { id: true },
  });
  if (demoSubjects.length === 0) return;
  const subjectIds = demoSubjects.map((s) => s.id);

  const demoRequests = await prismaBase.intakeRequest.findMany({
    where: { tenant_id: tenantId, subject_id: { in: subjectIds } },
    select: { id: true },
  });
  const requestIds = demoRequests.map((r) => r.id);

  if (requestIds.length > 0) {
    const demoInvoices = await prismaBase.invoice.findMany({
      where: { request_id: { in: requestIds } },
      select: { id: true },
    });
    const invoiceIds = demoInvoices.map((i) => i.id);

    // Delete children first — cascades are NoAction on SQL Server.
    await prismaBase.intakeRequestAssessmentMeasureResponse.deleteMany({
      where: { request_id: { in: requestIds } },
    });
    await prismaBase.service.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.assessment.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.caseMessage.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.notificationLog.deleteMany({
      where: {
        OR: [
          { entity_type: "IntakeRequest", entity_id: { in: requestIds } },
          { entity_type: "Invoice", entity_id: { in: invoiceIds } },
        ],
      },
    });
    await prismaBase.invoice.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.resolutionPlan.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.intakeRequestDiagnosis.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.intakeRequestCareGiver.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.intakeRequestProvider.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.intakeRequestTenantStaff.deleteMany({ where: { request_id: { in: requestIds } } });
    await prismaBase.intakeRequest.deleteMany({ where: { id: { in: requestIds } } });
  }
  await prismaBase.subjectNotes.deleteMany({ where: { subject_id: { in: subjectIds } } });
  await prismaBase.subject.deleteMany({ where: { id: { in: subjectIds } } });
}

function daysAgo(n: number): Date {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return d;
}

async function main() {
  console.log("→ Seeding demo scenario (3 cases)");

  const fcts = await prismaBase.tenant.findUniqueOrThrow({ where: { slug: "fcts" } });
  const tenant_id = fcts.id;

  await resetDemoData(tenant_id);

  const source = await prismaBase.source.findFirstOrThrow({
    where: { tenant_id, name: "Sunshine Home Health" },
  });
  const ptProviders = await prismaBase.provider.findMany({
    where: { tenant_id, specialty: "PT" },
    orderBy: { family_name: "asc" },
  });
  if (ptProviders.length < 2) throw new Error("Expected ≥2 seeded PT providers");
  const ptVisitRate = await prismaBase.tenantServiceRate.findFirstOrThrow({
    where: { tenant_id, service_code: "PT-VISIT" },
  });

  // ── DEMO-1 — Phase 2, in the Intake Inbox ──────────────────────────────────
  const subj1 = await prismaBase.subject.create({
    data: {
      tenant_id,
      external_id: "DEMO-1",
      given_name: "Eleanor",
      family_name: "Whitfield",
      date_of_birth: new Date("1944-02-11"),
      preferred_language: "English",
      address_line1: "88 Flagler Dr",
      city: "West Palm Beach",
      state: "FL",
      postal_code: "33401",
    },
  });
  const req1 = await prismaBase.intakeRequest.create({
    data: {
      tenant_id,
      source_id: source.id,
      subject_id: subj1.id,
      phase: "Phase2_IntakeReview",
      status: "Active",
      ingestion_channel: "webform",
      requested_service: "PT",
      schedule_preference: "weekday mornings",
      notes: "Post-op hip replacement, discharged home, needs gait training.",
      created_at: daysAgo(1),
    },
  });
  await prismaBase.intakeRequestDiagnosis.create({
    data: {
      tenant_id,
      request_id: req1.id,
      code: "Z47.1",
      description: "Aftercare following joint replacement surgery",
      is_primary: true,
    },
  });

  // ── DEMO-2 — Phase 9, active case mid-delivery ─────────────────────────────
  const subj2 = await prismaBase.subject.create({
    data: {
      tenant_id,
      external_id: "DEMO-2",
      given_name: "Marcus",
      family_name: "Bell",
      date_of_birth: new Date("1952-07-19"),
      preferred_language: "English",
      address_line1: "210 Clematis St",
      city: "West Palm Beach",
      state: "FL",
      postal_code: "33401",
    },
  });
  const req2 = await prismaBase.intakeRequest.create({
    data: {
      tenant_id,
      source_id: source.id,
      subject_id: subj2.id,
      phase: "Phase9_ServiceDelivery",
      status: "Active",
      ingestion_channel: "webform",
      requested_service: "PT",
      schedule_preference: "afternoons, 3x/week",
      notes: "CVA with right-side weakness; balance + gait focus.",
      created_at: daysAgo(21),
    },
  });
  await prismaBase.intakeRequestDiagnosis.create({
    data: {
      tenant_id,
      request_id: req2.id,
      code: "I69.351",
      description:
        "Hemiplegia and hemiparesis following stroke, right dominant side",
      is_primary: true,
    },
  });
  await prismaBase.intakeRequestProvider.create({
    data: {
      tenant_id,
      request_id: req2.id,
      provider_id: ptProviders[0].id,
      approved: true,
      approved_at: daysAgo(18),
      rank_score: 0.82,
    },
  });
  const plan2 = await prismaBase.resolutionPlan.create({
    data: {
      tenant_id,
      request_id: req2.id,
      start_date: daysAgo(16),
      frequency: "3x/week for 6 weeks",
      services_summary: "PT-VISIT — gait, balance, and transfer training.",
      active: true,
    },
  });
  for (const v of [12, 9, 5, 2]) {
    await prismaBase.service.create({
      data: {
        tenant_id,
        request_id: req2.id,
        plan_id: plan2.id,
        provider_id: ptProviders[0].id,
        service_code: "PT-VISIT",
        visit_date: daysAgo(v),
        duration_minutes: 45,
        notes: "Gait + balance; tolerated well.",
        subject_signature_type: "TypedName",
        subject_signature_value: "Marcus Bell",
        signed_at: daysAgo(v),
        billable: true,
      },
    });
  }
  await prismaBase.assessment.create({
    data: {
      tenant_id,
      request_id: req2.id,
      provider_id: ptProviders[0].id,
      assessment_type: "Initial",
      notes: "Baseline: TUG 22s, requires min assist for transfers.",
      performed_at: daysAgo(16),
    },
  });

  // ── DEMO-3 — Phase 12, invoice sent, awaiting payment ──────────────────────
  const subj3 = await prismaBase.subject.create({
    data: {
      tenant_id,
      external_id: "DEMO-3",
      given_name: "Rosa",
      family_name: "Inglesias",
      date_of_birth: new Date("1939-11-03"),
      preferred_language: "Spanish",
      address_line1: "1450 S Olive Ave",
      city: "West Palm Beach",
      state: "FL",
      postal_code: "33401",
    },
  });
  const req3 = await prismaBase.intakeRequest.create({
    data: {
      tenant_id,
      source_id: source.id,
      subject_id: subj3.id,
      phase: "Phase12_InvoicePayment",
      status: "Active",
      ingestion_channel: "webform",
      requested_service: "PT",
      schedule_preference: "mornings",
      notes: "Generalized deconditioning following extended hospitalization.",
      created_at: daysAgo(56),
    },
  });
  await prismaBase.intakeRequestDiagnosis.create({
    data: {
      tenant_id,
      request_id: req3.id,
      code: "M62.81",
      description: "Muscle weakness (generalized)",
      is_primary: true,
    },
  });
  await prismaBase.intakeRequestProvider.create({
    data: {
      tenant_id,
      request_id: req3.id,
      provider_id: ptProviders[1].id,
      approved: true,
      approved_at: daysAgo(52),
      rank_score: 0.74,
    },
  });
  const plan3 = await prismaBase.resolutionPlan.create({
    data: {
      tenant_id,
      request_id: req3.id,
      start_date: daysAgo(50),
      end_date: daysAgo(8),
      frequency: "2x/week for 6 weeks",
      services_summary: "PT-VISIT — progressive strengthening + endurance.",
      active: true,
    },
  });
  const invoice3 = await prismaBase.invoice.create({
    data: {
      tenant_id,
      request_id: req3.id,
      plan_id: plan3.id,
      source_id: source.id,
      invoice_number: `INV-${new Date().getFullYear()}-DEMO`,
      status: "Sent",
      subtotal_cents: ptVisitRate.rate_cents * 5,
      total_cents: ptVisitRate.rate_cents * 5,
      currency: "USD",
      issued_at: daysAgo(7),
      due_at: daysAgo(-23),
      external_link: randomUUID().replace(/-/g, "").slice(0, 24),
    },
  });
  for (let i = 0; i < 5; i++) {
    await prismaBase.service.create({
      data: {
        tenant_id,
        request_id: req3.id,
        plan_id: plan3.id,
        provider_id: ptProviders[1].id,
        service_code: "PT-VISIT",
        visit_date: daysAgo(48 - i * 8),
        duration_minutes: 45,
        notes: "Strengthening + endurance; progressing toward goals.",
        subject_signature_type: "TypedName",
        subject_signature_value: "Rosa Inglesias",
        signed_at: daysAgo(48 - i * 8),
        billable: true,
        billed_invoice_id: invoice3.id,
      },
    });
  }
  for (const type of ["Initial", "Subsequent", "Final"] as const) {
    await prismaBase.assessment.create({
      data: {
        tenant_id,
        request_id: req3.id,
        provider_id: ptProviders[1].id,
        assessment_type: type,
        notes: `${type} assessment — see plan notes.`,
        performed_at: daysAgo(type === "Initial" ? 50 : type === "Subsequent" ? 28 : 8),
      },
    });
  }

  console.log("\n✅ Demo scenario seeded:");
  console.log(`  DEMO-1  Eleanor Whitfield   Phase 2  — in the Intake Inbox`);
  console.log(`  DEMO-2  Marcus Bell         Phase 9  — active, 4 visits logged`);
  console.log(`  DEMO-3  Rosa Inglesias      Phase 12 — invoice ${invoice3.invoice_number} sent`);
  console.log(`          source-view link: /invoices/${invoice3.external_link}\n`);
}

main()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error(err);
    process.exit(1);
  });
