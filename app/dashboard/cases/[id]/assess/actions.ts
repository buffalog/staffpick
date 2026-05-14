"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import {
  assertPhaseTransition,
  isPhaseTransitionLegal,
} from "@/lib/case-state-machine";
import type { AssessmentType, CasePhase } from "@/lib/enums";
import { generateInvoice } from "@/lib/invoicing/generate-invoice";

/** Advance from Phase 6 → Phase 7 to begin the initial assessment. */
export async function startInitialAssessment(requestId: string): Promise<void> {
  await withSession(async () => {
    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
    });
    if (!req) throw new Error(`IntakeRequest ${requestId} not found`);
    if (
      (req.phase as CasePhase) === "Phase6_Collaboration" &&
      isPhaseTransitionLegal("Phase6_Collaboration", "Phase7_InitialAssessment")
    ) {
      assertPhaseTransition("Phase6_Collaboration", "Phase7_InitialAssessment");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase7_InitialAssessment" },
      });
    }
  });
  revalidatePath(`/dashboard/cases/${requestId}`);
  redirect(`/dashboard/cases/${requestId}/assess`);
}

/**
 * Submit an Assessment. Behavior per `type`:
 *
 *   Initial      — current phase must be Phase 7. Advances Phase 7 → 8.
 *   Subsequent   — current phase must be Phase 9 or 10. Phase 9 → 10 on first
 *                  subsequent; further subsequents stay at 10.
 *   Final        — current phase must be Phase 10. Advances Phase 10 → 11
 *                  and triggers invoice generation.
 */
export async function submitAssessment(
  requestId: string,
  type: AssessmentType,
  formData: FormData,
): Promise<void> {
  await withSession(async (ctx) => {
    const providerId = String(formData.get("provider_id") ?? "");
    if (!providerId) throw new Error("provider_id is required");

    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
    });
    if (!req) throw new Error(`IntakeRequest ${requestId} not found`);
    const phase = req.phase as CasePhase;

    // Phase gating per assessment type
    if (type === "Initial" && phase !== "Phase7_InitialAssessment") {
      throw new Error(`Initial assessment requires Phase 7, got ${phase}`);
    }
    if (
      type === "Subsequent" &&
      phase !== "Phase9_ServiceDelivery" &&
      phase !== "Phase10_SubsequentAssessment"
    ) {
      throw new Error(`Subsequent assessment requires Phase 9 or 10, got ${phase}`);
    }
    if (type === "Final" && phase !== "Phase10_SubsequentAssessment") {
      throw new Error(`Final assessment requires Phase 10, got ${phase}`);
    }

    const measures = await prisma.assessmentMeasure.findMany({
      where: { active: true },
    });

    await prisma.assessment.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        provider_id: providerId,
        assessment_type: type,
        notes: String(formData.get("notes") ?? "") || null,
        performed_at: new Date(),
      },
    });

    for (const m of measures) {
      const raw = formData.get(`measure_${m.id}`);
      if (raw === null || raw === undefined) continue;
      const str = String(raw).trim();
      if (str === "") continue;

      const response: {
        tenant_id: string;
        request_id: string;
        measure_id: string;
        response_text?: string | null;
        response_number?: number | null;
        response_option?: string | null;
      } = {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        measure_id: m.id,
      };
      if (m.measure_type === "NumericRange") {
        const n = Number(str);
        if (!Number.isFinite(n)) continue;
        response.response_number = n;
      } else if (m.measure_type === "MultipleChoice") {
        response.response_option = str;
      } else {
        response.response_text = str;
      }
      await prisma.intakeRequestAssessmentMeasureResponse.create({
        data: response,
      });
    }

    // Phase transitions
    if (type === "Initial") {
      assertPhaseTransition("Phase7_InitialAssessment", "Phase8_PlanDocumentation");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase8_PlanDocumentation" },
      });
    } else if (type === "Subsequent" && phase === "Phase9_ServiceDelivery") {
      assertPhaseTransition("Phase9_ServiceDelivery", "Phase10_SubsequentAssessment");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase10_SubsequentAssessment" },
      });
    } else if (type === "Final") {
      assertPhaseTransition("Phase10_SubsequentAssessment", "Phase11_PlanCompletion");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase11_PlanCompletion" },
      });
      // Phase 11(a) — automated invoice generation
      await generateInvoice(requestId, ctx.tenantId);
    }
  });

  revalidatePath(`/dashboard/cases/${requestId}`);
  redirect(`/dashboard/cases/${requestId}`);
}
