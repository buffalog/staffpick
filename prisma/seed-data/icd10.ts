/**
 * Focused ICD-10-CM subset relevant to FCTS therapy staffing (PT/OT/SLP).
 * Not a full catalog — MVP enough to cover the bulk of typical referrals.
 * Expand from CMS GEMs files post-MVP if a tenant needs broader coverage.
 */

export type Icd10Code = {
  value: string; // ICD-10-CM code
  label: string; // human description
  category: "PT" | "OT" | "SLP" | "General";
};

export const ICD10_CODES: ReadonlyArray<Icd10Code> = [
  // ── Aftercare / generic rehab ──────────────────────────────────────────────
  { value: "Z47.1", label: "Aftercare following joint replacement surgery", category: "PT" },
  { value: "Z47.89", label: "Encounter for other orthopedic aftercare", category: "PT" },
  { value: "Z51.89", label: "Encounter for other specified aftercare", category: "General" },
  { value: "Z48.89", label: "Encounter for other specified surgical aftercare", category: "General" },

  // ── Gait / mobility / weakness (PT-heavy) ──────────────────────────────────
  { value: "M62.81", label: "Muscle weakness (generalized)", category: "PT" },
  { value: "M62.838", label: "Other muscle spasm", category: "PT" },
  { value: "M79.10", label: "Myalgia, unspecified site", category: "PT" },
  { value: "R26.0", label: "Ataxic gait", category: "PT" },
  { value: "R26.2", label: "Difficulty in walking, not elsewhere classified", category: "PT" },
  { value: "R26.81", label: "Unsteadiness on feet", category: "PT" },
  { value: "R26.89", label: "Other abnormalities of gait and mobility", category: "PT" },
  { value: "R26.9", label: "Unspecified abnormalities of gait and mobility", category: "PT" },
  { value: "R29.6", label: "Repeated falls", category: "PT" },

  // ── Stroke / hemiplegia (PT + SLP + OT) ────────────────────────────────────
  { value: "I63.9", label: "Cerebral infarction, unspecified (stroke)", category: "General" },
  { value: "I69.351", label: "Hemiplegia and hemiparesis following stroke, right dominant side", category: "PT" },
  { value: "I69.352", label: "Hemiplegia and hemiparesis following stroke, left non-dominant side", category: "PT" },
  { value: "G81.90", label: "Hemiplegia, unspecified", category: "PT" },
  { value: "G81.91", label: "Hemiplegia, unspecified affecting right dominant side", category: "PT" },
  { value: "G81.92", label: "Hemiplegia, unspecified affecting left non-dominant side", category: "PT" },

  // ── Neurological (mixed disciplines) ───────────────────────────────────────
  { value: "G20", label: "Parkinson's disease", category: "General" },
  { value: "G35", label: "Multiple sclerosis", category: "General" },
  { value: "G93.40", label: "Encephalopathy, unspecified", category: "General" },
  { value: "F03.90", label: "Unspecified dementia without behavioral disturbance", category: "OT" },
  { value: "F03.91", label: "Unspecified dementia with behavioral disturbance", category: "OT" },

  // ── Joint / fracture (PT, post-op) ─────────────────────────────────────────
  { value: "M25.551", label: "Pain in right hip", category: "PT" },
  { value: "M25.552", label: "Pain in left hip", category: "PT" },
  { value: "M25.561", label: "Pain in right knee", category: "PT" },
  { value: "M25.562", label: "Pain in left knee", category: "PT" },
  { value: "M54.50", label: "Low back pain, unspecified", category: "PT" },
  { value: "M54.16", label: "Radiculopathy, lumbar region", category: "PT" },
  { value: "S72.001A", label: "Fracture of unspecified neck of right femur, initial encounter", category: "PT" },
  { value: "S72.002A", label: "Fracture of unspecified neck of left femur, initial encounter", category: "PT" },
  { value: "S82.001A", label: "Unspecified fracture of right patella, initial encounter", category: "PT" },

  // ── Hand / upper extremity (OT) ────────────────────────────────────────────
  { value: "M25.531", label: "Pain in right wrist", category: "OT" },
  { value: "M25.532", label: "Pain in left wrist", category: "OT" },
  { value: "M25.541", label: "Pain in joints of right hand", category: "OT" },
  { value: "M25.542", label: "Pain in joints of left hand", category: "OT" },
  { value: "G56.01", label: "Carpal tunnel syndrome, right upper limb", category: "OT" },
  { value: "G56.02", label: "Carpal tunnel syndrome, left upper limb", category: "OT" },

  // ── Cognitive / attention (OT + SLP) ───────────────────────────────────────
  { value: "R41.81", label: "Age-related cognitive decline", category: "OT" },
  { value: "R41.840", label: "Attention and concentration deficit", category: "OT" },
  { value: "R41.844", label: "Frontal lobe and executive function deficit", category: "OT" },

  // ── Speech / swallowing (SLP) ──────────────────────────────────────────────
  { value: "R47.01", label: "Aphasia", category: "SLP" },
  { value: "R47.02", label: "Dysphasia", category: "SLP" },
  { value: "R47.1", label: "Dysarthria and anarthria", category: "SLP" },
  { value: "R13.10", label: "Dysphagia, unspecified", category: "SLP" },
  { value: "R13.11", label: "Dysphagia, oral phase", category: "SLP" },
  { value: "R13.12", label: "Dysphagia, oropharyngeal phase", category: "SLP" },
  { value: "R13.13", label: "Dysphagia, pharyngeal phase", category: "SLP" },
  { value: "R13.14", label: "Dysphagia, pharyngoesophageal phase", category: "SLP" },
  { value: "R49.0", label: "Dysphonia (hoarseness)", category: "SLP" },
  { value: "R49.21", label: "Hypernasality", category: "SLP" },
  { value: "F80.0", label: "Phonological disorder", category: "SLP" },
  { value: "F80.1", label: "Expressive language disorder", category: "SLP" },
  { value: "F80.2", label: "Mixed receptive-expressive language disorder", category: "SLP" },
  { value: "H93.25", label: "Central auditory processing disorder", category: "SLP" },

  // ── Endocrine / circulatory comorbidities (relevant to therapy plans) ──────
  { value: "E11.40", label: "Type 2 diabetes mellitus with diabetic neuropathy, unspecified", category: "General" },
  { value: "E11.9", label: "Type 2 diabetes mellitus without complications", category: "General" },
  { value: "I10", label: "Essential (primary) hypertension", category: "General" },
  { value: "I50.9", label: "Heart failure, unspecified", category: "General" },
];
