"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import {
  assertPhaseTransition,
  isPhaseTransitionLegal,
} from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";

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
 * Submit the initial assessment: writes Assessment + per-measure responses,
 * then advances Phase 7 → Phase 8 (Plan Documentation).
 *
 * FormData keys:
 *   provider_id           — assigned provider performing the assessment
 *   measure_<measureId>   — response value (numeric / option value / text)
 *   notes                 — optional Assessment.notes
 */
export async function submitInitialAssessment(
  requestId: string,
  formData: FormData,
): Promise<void> {
  await withSession(async (ctx) => {
    const providerId = String(formData.get("provider_id") ?? "");
    if (!providerId) throw new Error("provider_id is required");

    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
    });
    if (!req) throw new Error(`IntakeRequest ${requestId} not found`);
    if ((req.phase as CasePhase) !== "Phase7_InitialAssessment") {
      throw new Error(
        `Cannot submit assessment from phase ${req.phase} — expected Phase7_InitialAssessment.`,
      );
    }

    const measures = await prisma.assessmentMeasure.findMany({
      where: { active: true },
    });

    const assessment = await prisma.assessment.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        provider_id: providerId,
        assessment_type: "Initial",
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

    assertPhaseTransition("Phase7_InitialAssessment", "Phase8_PlanDocumentation");
    await prisma.intakeRequest.update({
      where: { id: requestId },
      data: { phase: "Phase8_PlanDocumentation" },
    });

    // Touch the assessment so the IDE sees it's used (assessment.id is the
    // entity_id we want surfaced via audit metadata, but for MVP the audit
    // extension already captures the create).
    void assessment;
  });

  revalidatePath(`/dashboard/cases/${requestId}`);
  redirect(`/dashboard/cases/${requestId}`);
}
