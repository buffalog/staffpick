"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { assertPhaseTransition } from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";

const planSchema = z.object({
  start_date: z.string().min(1),
  end_date: z.string().optional(),
  frequency: z.string().min(1),
  services_summary: z.string().optional(),
});

export async function createResolutionPlan(
  requestId: string,
  formData: FormData,
): Promise<void> {
  const parsed = planSchema.safeParse({
    start_date: formData.get("start_date"),
    end_date: formData.get("end_date") || undefined,
    frequency: formData.get("frequency"),
    services_summary: formData.get("services_summary") || undefined,
  });
  if (!parsed.success) {
    throw new Error("Plan validation failed");
  }
  const data = parsed.data;

  await withSession(async (ctx) => {
    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
    });
    if (!req) throw new Error(`IntakeRequest ${requestId} not found`);

    await prisma.resolutionPlan.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        start_date: new Date(data.start_date),
        end_date: data.end_date ? new Date(data.end_date) : null,
        frequency: data.frequency,
        services_summary: data.services_summary ?? null,
        active: true,
      },
    });

    if ((req.phase as CasePhase) === "Phase8_PlanDocumentation") {
      assertPhaseTransition("Phase8_PlanDocumentation", "Phase9_ServiceDelivery");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase9_ServiceDelivery" },
      });
    }
  });
  revalidatePath(`/dashboard/cases/${requestId}`);
  redirect(`/dashboard/cases/${requestId}`);
}
