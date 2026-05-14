/**
 * Public-domain / CMS-published clinical assessment instruments for FCTS's
 * PT/OT/SLP staffing scope. Curated subset; full validated batteries (FOTO,
 * NIH Stroke Scale, etc.) require licensing and are post-MVP.
 *
 * Sources:
 *  - Berg Balance Scale (Berg 1989; public domain): https://www.physio-pedia.com/Berg_Balance_Scale
 *  - CMS Section GG (public): https://www.cms.gov/Medicare/Quality-Initiatives-Patient-Assessment-Instruments/MDS30RAIManual
 *  - Modified Barthel Index (Shah 1989; widely used): https://www.physio-pedia.com/Barthel_Index
 *  - Mini-Mental State Examination (Folstein 1975) — abbreviated subset only;
 *    full MMSE is licensed by PAR. Documented in docs/tech-debt.md.
 *
 * Each entry is materialized as an `AssessmentMeasure` row per FCTS tenant.
 * `code` is unique per tenant and survives across re-seeds.
 */

export type MeasureType = "MultipleChoice" | "FreeText" | "NumericRange";

export type MeasureOption = {
  value: string;
  label: string;
};

export type MeasureSeed = {
  code: string;
  label: string;
  measure_type: MeasureType;
  unit?: string;
  min_value?: number;
  max_value?: number;
  options?: MeasureOption[];
  display_order: number;
};

// ── PT — Berg Balance Scale (5 of 14 items shown) + functional measures ─────
const PT_MEASURES: MeasureSeed[] = [
  {
    code: "BBS-01",
    label: "Sitting to standing (Berg item 1, 0=needs help, 4=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 4,
    display_order: 100,
  },
  {
    code: "BBS-02",
    label: "Standing unsupported (Berg item 2, 0=cannot, 4=safely 2+ min)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 4,
    display_order: 101,
  },
  {
    code: "BBS-05",
    label: "Transfers (Berg item 5, 0=needs 2 assist, 4=safely with minor hand use)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 4,
    display_order: 102,
  },
  {
    code: "BBS-11",
    label: "Turning 360° (Berg item 11, 0=needs assist, 4=≤4 sec each direction)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 4,
    display_order: 103,
  },
  {
    code: "BBS-12",
    label: "Placing alternate foot on step (Berg item 12, 0=cannot, 4=8 steps in 20 sec)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 4,
    display_order: 104,
  },
  {
    code: "TUG",
    label: "Timed Up and Go (seconds; ≥13.5s suggests fall risk)",
    measure_type: "NumericRange",
    unit: "seconds",
    min_value: 0,
    max_value: 120,
    display_order: 110,
  },
  {
    code: "GAIT-SPEED",
    label: "Comfortable gait speed",
    measure_type: "NumericRange",
    unit: "m/sec",
    min_value: 0,
    max_value: 3,
    display_order: 111,
  },
];

// ── OT — Modified Barthel Index subset + cognitive ───────────────────────────
const OT_MEASURES: MeasureSeed[] = [
  {
    code: "BARTHEL-FEED",
    label: "Feeding (Modified Barthel: 0=unable, 10=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 10,
    display_order: 200,
  },
  {
    code: "BARTHEL-TRANS",
    label: "Bed/chair transfers (Modified Barthel: 0=unable, 15=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 15,
    display_order: 201,
  },
  {
    code: "BARTHEL-GROOM",
    label: "Grooming (Modified Barthel: 0=needs help, 5=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 5,
    display_order: 202,
  },
  {
    code: "BARTHEL-DRESS",
    label: "Dressing (Modified Barthel: 0=dependent, 10=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 10,
    display_order: 203,
  },
  {
    code: "BARTHEL-TOILET",
    label: "Toileting (Modified Barthel: 0=dependent, 10=independent)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 10,
    display_order: 204,
  },
  {
    code: "ALERTNESS",
    label: "Patient alertness at session start",
    measure_type: "MultipleChoice",
    options: [
      { value: "alert", label: "Alert" },
      { value: "drowsy", label: "Drowsy but responsive" },
      { value: "lethargic", label: "Lethargic" },
      { value: "unresponsive", label: "Unresponsive" },
    ],
    display_order: 220,
  },
];

// ── SLP — speech/language/swallow ────────────────────────────────────────────
const SLP_MEASURES: MeasureSeed[] = [
  {
    code: "MMSE-ABBR",
    label: "Mini-Mental State Examination, abbreviated total (0–30; full MMSE is licensed)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 30,
    display_order: 300,
  },
  {
    code: "NAMING",
    label: "Confrontation naming intactness (objects/actions named correctly out of 10)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 10,
    display_order: 301,
  },
  {
    code: "DYSPHAGIA-DIET",
    label: "Recommended diet level (IDDSI)",
    measure_type: "MultipleChoice",
    options: [
      { value: "iddsi-0", label: "Level 0 — Thin" },
      { value: "iddsi-1", label: "Level 1 — Slightly Thick" },
      { value: "iddsi-2", label: "Level 2 — Mildly Thick" },
      { value: "iddsi-3", label: "Level 3 — Moderately Thick / Liquidised" },
      { value: "iddsi-4", label: "Level 4 — Pureed" },
      { value: "iddsi-5", label: "Level 5 — Minced & Moist" },
      { value: "iddsi-6", label: "Level 6 — Soft & Bite-Sized" },
      { value: "iddsi-7", label: "Level 7 — Regular / Easy to Chew" },
      { value: "npo", label: "NPO" },
    ],
    display_order: 302,
  },
  {
    code: "VOICE-QUALITY",
    label: "Voice quality observations",
    measure_type: "FreeText",
    display_order: 303,
  },
  {
    code: "ARTIC-NOTES",
    label: "Articulation / intelligibility notes",
    measure_type: "FreeText",
    display_order: 304,
  },
];

// ── General — applies to any discipline ──────────────────────────────────────
const GENERAL_MEASURES: MeasureSeed[] = [
  {
    code: "PAIN-NRS",
    label: "Pain (Numeric Rating Scale, 0–10)",
    measure_type: "NumericRange",
    min_value: 0,
    max_value: 10,
    display_order: 1,
  },
  {
    code: "FALL-RISK",
    label: "Estimated fall risk",
    measure_type: "MultipleChoice",
    options: [
      { value: "low", label: "Low" },
      { value: "moderate", label: "Moderate" },
      { value: "high", label: "High" },
    ],
    display_order: 2,
  },
  {
    code: "GOALS",
    label: "Short-term goals (1–4 weeks)",
    measure_type: "FreeText",
    display_order: 900,
  },
  {
    code: "PLAN-NOTES",
    label: "Plan-of-care notes",
    measure_type: "FreeText",
    display_order: 901,
  },
];

export const ASSESSMENT_MEASURES: ReadonlyArray<MeasureSeed> = [
  ...GENERAL_MEASURES,
  ...PT_MEASURES,
  ...OT_MEASURES,
  ...SLP_MEASURES,
];
