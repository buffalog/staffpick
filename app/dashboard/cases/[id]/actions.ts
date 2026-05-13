"use server";

import { revalidatePath } from "next/cache";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import {
  assertPhaseTransition,
  assertStatusTransition,
} from "@/lib/case-state-machine";
import type { CasePhase, CaseStatus } from "@/lib/enums";

export async function transitionPhase(id: string, to: string): Promise<void> {
  await withSession(async () => {
    const req = await prisma.intakeRequest.findFirst({ where: { id } });
    if (!req) throw new Error(`IntakeRequest ${id} not found`);
    assertPhaseTransition(req.phase as CasePhase, to as CasePhase);
    await prisma.intakeRequest.update({
      where: { id },
      data: { phase: to },
    });
  });
  revalidatePath(`/dashboard/cases/${id}`);
  revalidatePath("/dashboard/inbox");
}

export async function transitionStatus(id: string, to: string): Promise<void> {
  await withSession(async () => {
    const req = await prisma.intakeRequest.findFirst({ where: { id } });
    if (!req) throw new Error(`IntakeRequest ${id} not found`);
    assertStatusTransition(req.status as CaseStatus, to as CaseStatus);
    await prisma.intakeRequest.update({
      where: { id },
      data: { status: to },
    });
  });
  revalidatePath(`/dashboard/cases/${id}`);
  revalidatePath("/dashboard/inbox");
}
