import { describe, expect, it } from "vitest";
import {
  assertPhaseTransition,
  assertStatusTransition,
  isPhaseTransitionLegal,
  isStatusTransitionLegal,
  isTerminalPhase,
  isTerminalStatus,
  legalNextPhases,
  legalNextStatuses,
} from "./case-state-machine";

describe("case-state-machine — phase transitions", () => {
  it("Phase1 → Phase2 is legal (gate enabled)", () => {
    expect(isPhaseTransitionLegal("Phase1_IntakeReceived", "Phase2_IntakeReview")).toBe(true);
  });

  it("Phase1 → Phase3 is legal (gate bypassed per tenant setting)", () => {
    expect(isPhaseTransitionLegal("Phase1_IntakeReceived", "Phase3_MatchingKickoff")).toBe(true);
  });

  it("Phase1 → Phase4 is illegal (cannot skip two phases)", () => {
    expect(isPhaseTransitionLegal("Phase1_IntakeReceived", "Phase4_MatchReview")).toBe(false);
  });

  it("Phase4 → Phase3 is legal (staff rejects match, re-run matching)", () => {
    expect(isPhaseTransitionLegal("Phase4_MatchReview", "Phase3_MatchingKickoff")).toBe(true);
  });

  it("Phase9 → Phase9 is legal (additional visit recorded)", () => {
    expect(isPhaseTransitionLegal("Phase9_ServiceDelivery", "Phase9_ServiceDelivery")).toBe(true);
  });

  it("Phase9 → Phase10 is legal (subsequent assessment due)", () => {
    expect(isPhaseTransitionLegal("Phase9_ServiceDelivery", "Phase10_SubsequentAssessment")).toBe(true);
  });

  it("Phase10 → Phase8 is legal (plan needs update)", () => {
    expect(isPhaseTransitionLegal("Phase10_SubsequentAssessment", "Phase8_PlanDocumentation")).toBe(true);
  });

  it("Phase10 → Phase11 is legal (final assessment complete)", () => {
    expect(isPhaseTransitionLegal("Phase10_SubsequentAssessment", "Phase11_PlanCompletion")).toBe(true);
  });

  it("Phase14 is terminal", () => {
    expect(isTerminalPhase("Phase14_Closed")).toBe(true);
    expect(legalNextPhases("Phase14_Closed")).toEqual([]);
  });

  it("Phase1 is not terminal", () => {
    expect(isTerminalPhase("Phase1_IntakeReceived")).toBe(false);
  });

  it("assertPhaseTransition throws on illegal", () => {
    expect(() =>
      assertPhaseTransition("Phase1_IntakeReceived", "Phase14_Closed"),
    ).toThrow(/Illegal phase transition/);
  });

  it("assertPhaseTransition does not throw on legal", () => {
    expect(() =>
      assertPhaseTransition("Phase3_MatchingKickoff", "Phase4_MatchReview"),
    ).not.toThrow();
  });

  it("walks the happy path Phase1→...→Phase14", () => {
    const path: Array<[CasePhaseLocal, CasePhaseLocal]> = [
      ["Phase1_IntakeReceived", "Phase2_IntakeReview"],
      ["Phase2_IntakeReview", "Phase3_MatchingKickoff"],
      ["Phase3_MatchingKickoff", "Phase4_MatchReview"],
      ["Phase4_MatchReview", "Phase5_MatchNotification"],
      ["Phase5_MatchNotification", "Phase6_Collaboration"],
      ["Phase6_Collaboration", "Phase7_InitialAssessment"],
      ["Phase7_InitialAssessment", "Phase8_PlanDocumentation"],
      ["Phase8_PlanDocumentation", "Phase9_ServiceDelivery"],
      ["Phase9_ServiceDelivery", "Phase10_SubsequentAssessment"],
      ["Phase10_SubsequentAssessment", "Phase11_PlanCompletion"],
      ["Phase11_PlanCompletion", "Phase12_InvoicePayment"],
      ["Phase12_InvoicePayment", "Phase13_ProviderPayment"],
      ["Phase13_ProviderPayment", "Phase14_Closed"],
    ];
    for (const [from, to] of path) {
      expect(() => assertPhaseTransition(from, to)).not.toThrow();
    }
  });
});

describe("case-state-machine — status transitions", () => {
  it("Active ↔ OnHold both directions legal", () => {
    expect(isStatusTransitionLegal("Active", "OnHold")).toBe(true);
    expect(isStatusTransitionLegal("OnHold", "Active")).toBe(true);
  });

  it("Active → Cancelled and Active → Closed legal", () => {
    expect(isStatusTransitionLegal("Active", "Cancelled")).toBe(true);
    expect(isStatusTransitionLegal("Active", "Closed")).toBe(true);
  });

  it("OnHold → Closed is illegal (must reactivate first)", () => {
    expect(isStatusTransitionLegal("OnHold", "Closed")).toBe(false);
  });

  it("Cancelled and Closed are terminal", () => {
    expect(isTerminalStatus("Cancelled")).toBe(true);
    expect(isTerminalStatus("Closed")).toBe(true);
    expect(legalNextStatuses("Cancelled")).toEqual([]);
    expect(legalNextStatuses("Closed")).toEqual([]);
  });

  it("assertStatusTransition throws on Cancelled → Active (terminal)", () => {
    expect(() => assertStatusTransition("Cancelled", "Active")).toThrow(/Illegal status/);
  });
});

// Local alias to keep test imports thin.
type CasePhaseLocal =
  | "Phase1_IntakeReceived"
  | "Phase2_IntakeReview"
  | "Phase3_MatchingKickoff"
  | "Phase4_MatchReview"
  | "Phase5_MatchNotification"
  | "Phase6_Collaboration"
  | "Phase7_InitialAssessment"
  | "Phase8_PlanDocumentation"
  | "Phase9_ServiceDelivery"
  | "Phase10_SubsequentAssessment"
  | "Phase11_PlanCompletion"
  | "Phase12_InvoicePayment"
  | "Phase13_ProviderPayment"
  | "Phase14_Closed";
