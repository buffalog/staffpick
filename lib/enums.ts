// TS string-literal types matching enum-typed String columns in prisma/schema.prisma.
// SQL Server connector doesn't support Prisma enum blocks; validation lives here.

export const CASE_PHASES = [
  "Phase1_IntakeReceived",
  "Phase2_IntakeReview",
  "Phase3_MatchingKickoff",
  "Phase4_MatchReview",
  "Phase5_MatchNotification",
  "Phase6_Collaboration",
  "Phase7_InitialAssessment",
  "Phase8_PlanDocumentation",
  "Phase9_ServiceDelivery",
  "Phase10_SubsequentAssessment",
  "Phase11_PlanCompletion",
  "Phase12_InvoicePayment",
  "Phase13_ProviderPayment",
  "Phase14_Closed",
] as const;
export type CasePhase = (typeof CASE_PHASES)[number];

export const CASE_STATUSES = ["Active", "OnHold", "Cancelled", "Closed"] as const;
export type CaseStatus = (typeof CASE_STATUSES)[number];

export const ASSESSMENT_TYPES = ["Initial", "Subsequent", "Final"] as const;
export type AssessmentType = (typeof ASSESSMENT_TYPES)[number];

export const MEASURE_TYPES = ["MultipleChoice", "FreeText", "NumericRange"] as const;
export type MeasureType = (typeof MEASURE_TYPES)[number];

export const INVOICE_STATUSES = ["Draft", "Sent", "Paid", "Disputed", "Voided"] as const;
export type InvoiceStatus = (typeof INVOICE_STATUSES)[number];

export const NOTIFICATION_CHANNELS = ["Email", "SMS", "InApp", "Push"] as const;
export type NotificationChannel = (typeof NOTIFICATION_CHANNELS)[number];

export const NOTIFICATION_STATUSES = [
  "Queued",
  "Sent",
  "Delivered",
  "Failed",
  "Bounced",
] as const;
export type NotificationStatus = (typeof NOTIFICATION_STATUSES)[number];

export const SIGNATURE_TYPES = ["TypedName", "ImageBlob", "SignedLink"] as const;
export type SignatureType = (typeof SIGNATURE_TYPES)[number];

export const USER_ROLE_TYPES = [
  "PlatformAdmin",
  "TenantAdmin",
  "TenantStaff",
  "Provider",
  "Source",
] as const;
export type UserRoleType = (typeof USER_ROLE_TYPES)[number];

export const AUDIT_ACTIONS = [
  "Create",
  "Read",
  "Update",
  "Delete",
  "Login",
  "Logout",
  "Export",
] as const;
export type AuditAction = (typeof AUDIT_ACTIONS)[number];

// Roles that require TOTP MFA on every session (authenticator-app code).
export const MFA_REQUIRED_ROLES: ReadonlySet<UserRoleType> = new Set([
  "PlatformAdmin",
  "TenantAdmin",
  "TenantStaff",
]);

// Roles that use emailed 6-digit OTPs as the second factor (no authenticator
// app required — clinicians on a phone in the field).
export const EMAIL_OTP_ROLES: ReadonlySet<UserRoleType> = new Set(["Provider"]);
