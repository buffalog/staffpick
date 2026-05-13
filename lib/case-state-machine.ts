/**
 * 14-phase case lifecycle state machine.
 *
 * Source of intent: docs/discovery-v0.2.md §6.
 * Status field (Active | OnHold | Cancelled | Closed) is orthogonal to phase
 * and handled separately.
 */

import type { CasePhase, CaseStatus } from "@/lib/enums";

// ── Phase transitions ────────────────────────────────────────────────────────
// Map keyed by current phase; value is the set of legal next phases.
// Phase 2 is optional: if intake_review_gate_enabled=false in tenant_settings,
// callers should advance from Phase1 directly to Phase3 (skipping Phase2).
// The state machine permits both Phase1→Phase2 and Phase1→Phase3 so the
// gate-bypass case is legal.
const PHASE_TRANSITIONS: Readonly<Record<CasePhase, ReadonlyArray<CasePhase>>> = {
  Phase1_IntakeReceived: ["Phase2_IntakeReview", "Phase3_MatchingKickoff"],
  Phase2_IntakeReview: ["Phase3_MatchingKickoff"],
  Phase3_MatchingKickoff: ["Phase4_MatchReview"],
  Phase4_MatchReview: ["Phase5_MatchNotification", "Phase3_MatchingKickoff"],
  Phase5_MatchNotification: ["Phase6_Collaboration"],
  Phase6_Collaboration: ["Phase7_InitialAssessment"],
  Phase7_InitialAssessment: ["Phase8_PlanDocumentation"],
  Phase8_PlanDocumentation: ["Phase9_ServiceDelivery"],
  // Phase9 self-loops as additional visits are recorded; advance to Phase10
  // when a subsequent assessment is needed.
  Phase9_ServiceDelivery: ["Phase9_ServiceDelivery", "Phase10_SubsequentAssessment"],
  // From the subsequent assessment, either complete (→ Phase11) or update
  // the plan (→ Phase8).
  Phase10_SubsequentAssessment: ["Phase11_PlanCompletion", "Phase8_PlanDocumentation"],
  Phase11_PlanCompletion: ["Phase12_InvoicePayment"],
  Phase12_InvoicePayment: ["Phase13_ProviderPayment"],
  Phase13_ProviderPayment: ["Phase14_Closed"],
  Phase14_Closed: [], // terminal
};

export function legalNextPhases(from: CasePhase): ReadonlyArray<CasePhase> {
  return PHASE_TRANSITIONS[from] ?? [];
}

export function isPhaseTransitionLegal(
  from: CasePhase,
  to: CasePhase,
): boolean {
  return PHASE_TRANSITIONS[from]?.includes(to) ?? false;
}

export function assertPhaseTransition(from: CasePhase, to: CasePhase): void {
  if (!isPhaseTransitionLegal(from, to)) {
    throw new Error(
      `Illegal phase transition: ${from} → ${to}. ` +
        `Legal next phases from ${from}: [${legalNextPhases(from).join(", ")}]`,
    );
  }
}

export function isTerminalPhase(phase: CasePhase): boolean {
  return PHASE_TRANSITIONS[phase].length === 0;
}

// ── Status transitions ───────────────────────────────────────────────────────
const STATUS_TRANSITIONS: Readonly<Record<CaseStatus, ReadonlyArray<CaseStatus>>> = {
  Active: ["OnHold", "Cancelled", "Closed"],
  OnHold: ["Active", "Cancelled"],
  Cancelled: [], // terminal
  Closed: [], // terminal
};

export function legalNextStatuses(from: CaseStatus): ReadonlyArray<CaseStatus> {
  return STATUS_TRANSITIONS[from] ?? [];
}

export function isStatusTransitionLegal(
  from: CaseStatus,
  to: CaseStatus,
): boolean {
  return STATUS_TRANSITIONS[from]?.includes(to) ?? false;
}

export function assertStatusTransition(from: CaseStatus, to: CaseStatus): void {
  if (!isStatusTransitionLegal(from, to)) {
    throw new Error(
      `Illegal status transition: ${from} → ${to}. ` +
        `Legal next statuses from ${from}: [${legalNextStatuses(from).join(", ")}]`,
    );
  }
}

export function isTerminalStatus(status: CaseStatus): boolean {
  return STATUS_TRANSITIONS[status].length === 0;
}

// ── Human-friendly labels for UI ─────────────────────────────────────────────
export const PHASE_LABELS: Readonly<Record<CasePhase, string>> = {
  Phase1_IntakeReceived: "1 · Intake Received",
  Phase2_IntakeReview: "2 · Intake Review",
  Phase3_MatchingKickoff: "3 · Matching Kickoff",
  Phase4_MatchReview: "4 · Match Review",
  Phase5_MatchNotification: "5 · Match Notification",
  Phase6_Collaboration: "6 · Collaboration",
  Phase7_InitialAssessment: "7 · Initial Assessment",
  Phase8_PlanDocumentation: "8 · Plan Documentation",
  Phase9_ServiceDelivery: "9 · Service Delivery",
  Phase10_SubsequentAssessment: "10 · Subsequent Assessment",
  Phase11_PlanCompletion: "11 · Plan Completion",
  Phase12_InvoicePayment: "12 · Invoice & Payment",
  Phase13_ProviderPayment: "13 · Provider Payment",
  Phase14_Closed: "14 · Closed",
};
