"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import {
  assertPhaseTransition,
  assertStatusTransition,
} from "@/lib/case-state-machine";
import type { CasePhase, CaseStatus } from "@/lib/enums";

export async function acceptRequest(id: string): Promise<void> {
  await withSession(async () => {
    const req = await prisma.intakeRequest.findFirst({ where: { id } });
    if (!req) throw new Error(`IntakeRequest ${id} not found`);
    assertPhaseTransition(req.phase as CasePhase, "Phase3_MatchingKickoff");
    await prisma.intakeRequest.update({
      where: { id },
      data: { phase: "Phase3_MatchingKickoff" },
    });
  });
  revalidatePath("/dashboard/inbox");
  revalidatePath(`/dashboard/cases/${id}`);
}

export async function rejectRequest(id: string, reason?: string): Promise<void> {
  await withSession(async () => {
    const req = await prisma.intakeRequest.findFirst({ where: { id } });
    if (!req) throw new Error(`IntakeRequest ${id} not found`);
    assertStatusTransition(req.status as CaseStatus, "Cancelled");
    await prisma.intakeRequest.update({
      where: { id },
      data: {
        status: "Cancelled",
        notes: reason
          ? `${req.notes ?? ""}\n\n[Rejected]: ${reason}`.trim()
          : req.notes,
      },
    });
  });
  revalidatePath("/dashboard/inbox");
  revalidatePath(`/dashboard/cases/${id}`);
}

export async function openCase(id: string): Promise<void> {
  redirect(`/dashboard/cases/${id}`);
}
