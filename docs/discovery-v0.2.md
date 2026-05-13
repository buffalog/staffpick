# First Class Therapy Solutions — Platform Discovery & Architecture Baseline

**Prepared for:** Judd Kussrow (FCTS engagement lead)
**Prepared with:** Jeremy Pihl
**Date:** April 16, 2026
**Status:** Draft v0.2 — Full Synthesis (StoriesOnBoard + Boardmix)

This document supersedes v0.1. It synthesizes the public-facing website, the StoriesOnBoard user story map, and the Boardmix whiteboard — including Judd's entity diagram, swim-lane workflow, Golden Road checklist, and 14-phase narrative spec — into a single baseline for the FCTS platform rewrite. Where v0.1 made assumptions, this version reflects Judd's actual declared intent.

## Contents

1. Executive Summary
2. What This Product Actually Is
3. Source Material
4. Stakeholders and Roles
5. Domain Language (Canonical)
6. The 14-Phase Lifecycle (Judd's Spec)
7. Golden Road Operational Checklist
8. Epic and Feature Scope
9. Data Model (Entity Inventory)
10. Declared Technical Architecture
11. Integrations and External Dependencies
12. Cross-Cutting Concerns
13. Architectural Tensions to Resolve
14. Open Questions
15. Recommended Next Steps

---

## 1. Executive Summary

The FCTS engagement is not — or not only — a rewrite of a single agency's internal tools. Judd is building a multi-tenant, industry-agnostic, "Uber-for-X" style SaaS product for agency-based lead placement and case management, with First Class Therapy Solutions as the first tenant and therapy staffing as the first vertical.

The platform captures requests from source organizations (agencies, facilities, referrers), matches them to qualified providers using availability and location data, orchestrates a kanban-style case lifecycle from initial assessment through invoicing and provider payment, and maintains the clinical and financial documentation required along the way. The data model is designed to be abstracted — customizable titles and properties per tenant — so that the same engine can run therapy staffing, other clinical verticals, or entirely non-clinical staffing use cases.

The declared technical baseline is Azure SQL Server as the relational store, a microservices decomposition, an API and webhook surface for integration, and Turso as a probable tenancy layer. The intake/matching/case-management flow is specified at three levels of granularity: a 14-phase narrative, a 9-step Golden Road checklist, and an in-progress user story map with 10 epics and ~60 stories.

The single most consequential architectural commitment already made is multi-tenancy with per-tenant customization. Everything downstream — data model, auth, billing, UX — is shaped by it.

This document captures that baseline, reconciles it against the StoriesOnBoard story map, flags the genuine open tensions (Turso alongside Azure SQL, industry-agnostic metadata layer, Uber-style vs. staffing-agency-style matching), and lays out the next concrete steps.

---

## 2. What This Product Actually Is

### 2.1 Product framing (from Judd)

Judd's one-line framing: **"Agency Lead Placement & Management SaaS."**

The product's specific characteristics, as declared:

- **Stand-alone server-side app.** Not an embedded module or a plugin — a first-class product in its own right.
- **Multi-tenant SaaS.** Each tenant is an agency running the product. FCTS is the first.
- **Industry-agnostic.** Customizable titles and properties per use case. The same engine can run therapy staffing, other clinical fields, or unrelated lead-placement verticals.
- **Modular microservices.** Broken into independently deployable services, exposed via API and webhooks.
- **"Uber-for-X" matching pattern.** Requests are dispatched to qualified providers based on availability and location, with provider acceptance as part of the flow.
- **Kanban-style case pipeline.** Each request becomes a project tracked through pipeline stages with stakeholder notifications at each phase transition.

### 2.2 FCTS as first tenant

FCTS, LLC is a South Florida healthcare staffing agency placing physical, occupational, and speech therapists into facilities. Their public-facing operation is phone-driven (561-320-1491) with a minimal marketing site. The platform being built is their internal operating system, but it is not specific to them — their requirements shape the first vertical configuration.

The clinical specifics of FCTS's work — Medicare Part B therapy, SNF/HHA placements, PT Start-of-Care vs. Eval visits, re-certifications — will all need to be expressible in the industry-agnostic model. They are the forcing functions for what "customizable titles and properties" actually has to cover.

---

## 3. Source Material

| Source | Status | What it contributes |
|---|---|---|
| firstclasstherapy.net | Ingested ✓ | Public business profile, service lines (PT/OT/SLP), mission, contact channel |
| StoriesOnBoard export (xlsx) | Ingested ✓ | 10 epics, 19 features, ~60 user stories. Epic ownership by Angela Searcy, Tena Stafson, Dr. Gregg. |
| Boardmix — Entity diagram | Ingested ✓ | 26+ entities with fields and foreign keys. The system of record for the data model. |
| Boardmix — Swim-lane workflow | Ingested ✓ | Four-lane BPMN: Agency → FCTS Staff → CliniConnect → Clinicians. Covers the happy-path case flow. |
| Boardmix — Golden Road Checklist | Ingested ✓ | 9-step operational checklist in domain-specific language. Sharpest view of day-to-day clinical workflow. |
| Boardmix — StoriesOnBoard Prompt | Ingested ✓ | Judd's 14-phase narrative spec + declared technical choices + role inventory. The product brief. |
| Boardmix — Handwritten notes | Reviewed | Judd's working-thinking artifacts. Not meant as deliverables; corroborate the structured material. |

---

## 4. Stakeholders and Roles

### 4.1 FCTS stakeholders (from story map)

| Stakeholder | Epic ownership / involvement |
|---|---|
| Angela Searcy | Intake Request Management; Provider Communication & Collaboration; Ongoing Process Improvement |
| Tena Stafson | Appears across all listed epic ownership rows — day-to-day operational owner |
| Dr. Gregg | Provider Matching; Provider Communication; Service Delivery & Documentation; Financial Management |

### 4.2 Platform roles (from Judd's spec)

The platform defines seven roles, each with a domain term and a platform-generic term. These are first-class in the data model and auth layer.

| Platform role | Therapy-vertical term | Function |
|---|---|---|
| Source | Agency | Originates the request; pays the invoice |
| Subject | Patient | The person receiving services; signs off on plan/visits |
| Proxy / Associated Contact | Care-giver | Signs off on behalf of Subject when needed; involvement is tracked |
| Tenant | Staffing Agency (FCTS) | The Go-between; runs the request/project lifecycle |
| Tenant Staff | Processor / Staff | Internal operators at the Tenant — process requests, approve matches, send invoices |
| Provider | Clinician (PT/OT/SLP) | Performs the Service; receives Payment |
| Service | Therapy | The unit of work delivered under a Resolution Plan |

---

## 5. Domain Language (Canonical)

To prevent drift, the following are the canonical terms used in the platform. The therapy-vertical labels are configuration, not product terminology.

| Canonical term | Therapy vertical maps to | Definition |
|---|---|---|
| Request / Project / Case | Intake Request | The central work item. Its lifecycle is what the product orchestrates. |
| Resolution Plan | Treatment Plan | The plan of service derived from assessment; has schedule, frequency, versions. |
| Service | Therapy session / visit | A unit of work delivered against the Resolution Plan. |
| Assessment | PT/OT/ST Evaluation, PT SOC, Re-eval, Final Eval | Clinical evaluation event. Feeds or concludes a Resolution Plan. |
| Pending List / Active List / Discharged List | Same | Lifecycle segmentation for Subjects within an Agency (from Golden Road). |

The word "Tenant" in Judd's writing always means a customer of the platform (an agency running the product), never a patient. This is different from some healthcare usage and should be watched for in tenant-facing UI.

---

## 6. The 14-Phase Lifecycle (Judd's Spec)

This is the definitive process narrative, captured verbatim in intent from Judd's StoriesOnBoard Prompt. The 14 phases form the end-to-end happy path.

**Phase 1 — Intake Request Received**
Request arrives via email, webform, API call, or file transfer. The required information is sanitized and entered directly into the relational database. Multiple ingestion channels must normalize into one record.

**Phase 2 — Intake Request Review (optional)**
Optional gating step. If enabled, new Intake Requests are displayed to Tenant Staff to formally accept before Provider matching begins. Requests are accepted by default unless the Tenant configures otherwise.

**Phase 3 — Kickoff Provider Matching**
Using Intake Request data plus Provider data, the system:
(a) Finds potential Providers by matching day/time availability plus coverage location
(b) Prioritizes candidates (optional)
(c) Polls prioritized candidates for acceptance
(d) Returns a matched Provider to Tenant Staff

**Phase 4 — Provider Match Review**
Tenant Staff accepts/approves the match (record assignment, advance to phase 5) or rejects and sends phase 3 back for a new match.

**Phase 5 — Provider Match Notification**
Tenant Staff notifies Source and/or Subject of the Provider match and assignment. Flagged by Judd as a candidate for automation.

**Phase 6 — Intake Request Communication / Collaboration**
Tenant Staff starts a chat for the Request with all relevant parties including the Provider, and exchanges the information needed to begin service.

**Phase 7 — Initial Provider Assessment**
Assigned Provider performs an initial Assessment on the Subject, determines the Resolution Plan, and communicates the plan back to the group chat from phase 6. Assessment details are recorded in the database.

**Phase 8 — Resolution Plan Documentation**
Tenant Staff records the Resolution Plan including schedule. This information feeds tracking, reporting, invoicing, and (optionally) Source reporting. Judd flags automation/optimization opportunity.

**Phase 9 — Provider Service and Documentation**
Provider delivers Services per the Resolution Plan — typically multiple repeating visits. After each visit, the Subject (or Proxy) signs off on the Service. Each signed visit is recorded for tracking, invoicing, and payment. Proxy sign-offs are tracked on the Intake Request as well. This phase repeats until additional assessment is needed.

**Phase 10 — Subsequent Provider Assessment**
Provider performs additional Assessment to determine Resolution Plan status — complete, needs update, needs extension. If complete, Provider performs and documents a Final Evaluation and the flow advances to phase 11. Otherwise the flow returns to phase 8 to update the plan.

**Phase 11 — Resolution Plan Completion**
On Resolution Plan completion, three sub-steps occur:
(a) Invoice Generation — automated; visits performed drive what is invoiced
(b) Notification Generation — automated; Source notification attached to invoice, queued for Tenant Staff review
(c) Notification Review and Sending — Tenant Staff reviews, approves or modifies, then sends to Source

**Phase 12 — Invoice Review and Payment**
Source reviews invoice and notification, either approves and pays, or contacts Tenant to discuss. Results are recorded in the database. Paid invoices advance to phase 13.

**Phase 13 — Provider Payment**
After Source payment is received, Tenant pays out to the Providers involved for the work they performed (Assessments + Services under the Resolution Plan). The system must generate a report or artifact that makes Provider payment efficient to execute.

**Phase 14 — Intake Request Closed**
After Providers are paid, the Intake Request is marked completed and closed — automated, or with an optional review/signoff step. Process End.

---

## 7. Golden Road Operational Checklist

Captured directly from the Boardmix note. This is the tactical, day-to-day operator view — shorter than the 14 phases, in domain-specific language.

1. Agency referral contact (email)
2. Scheduler acknowledges agency — Pending status
3. CliniConnect's process (2+ hours)
4. (Wait/Loop) Check needs EMR tab
5. Scheduler informs agency (PT & PTA & Eval date)
6. Add new patient to pending list
7. (Wait) Lead informs scheduler of frequency (48 hrs)
8. Scheduler informs agency
9. (Optional) Delay, cancellation → jump to #9
10. Group chat created
11. Initial data added — Frequency
12. Pending to Active status
13. Move patient to active list
14. (Loop) Treatment
15. EMR sync (Payroll)
16. Signature obtained
17. Note is completed
18. (Optional) Re-evaluation / re-certification
19. (Optional) Re-staff — jump up to #3
20. Closed status
21. Lead collects form from patient
22. Move patient to discharged list

### Reconciling the Golden Road with the 14 phases

The Golden Road is the CliniConnect-era operational reality. The 14 phases are the target product lifecycle. They're not in conflict, but they're not a 1:1 mapping either — the Golden Road's "CliniConnect process (2+ hours)" step is precisely what the new platform is replacing.

In the new platform, phases 3–5 (matching, review, notification) absorb Golden Road steps 1–4. Phase 6 (collaboration) absorbs step 6. Phases 9–10 (service + repeat assessment) absorb step 7's loop. Phases 11–13 (invoice/payment/provider payout) sit outside the Golden Road entirely because the Golden Road is operational; finance is assumed downstream.

The EMR sync for payroll, signature, and note completion (step 7 sub-items) is the TheraHealth integration surface. That is a real external dependency, not a future concern.

---

## 8. Epic and Feature Scope

From StoriesOnBoard. Ten epics: six decomposed, four placeholder. The placeholder epics (recruitment, onboarding, credentialing, process improvement) remain the largest known scope gap.

| ID | Epic | Purpose | State |
|---|---|---|---|
| EP-01001 | Intake Request Management | Capture, validate, accept/reject from email/webform/API/file transfer | Decomposed |
| EP-01002 | Provider Matching | Find, prioritize, poll, and assign Providers | Decomposed |
| EP-01003 | Provider Communication and Collaboration | Notifications, chat, multi-party coordination on a Request | Decomposed |
| EP-01004 | Service Delivery and Documentation | Assessments, Resolution Plans, Service records, follow-ups, close-out | Decomposed |
| EP-01005 | Filestore and Referential DB Links | File storage vendor, folder design, DB-to-file reference model | Decomposed |
| EP-01006 | Financial Management and Reporting | Invoicing Sources, paying Providers, tracking, reporting | Decomposed |
| EP-01007 | Provider Recruitment | Sourcing and pipelining new Providers | Placeholder |
| EP-01008 | Provider Onboarding and Training | Hire → active availability | Placeholder |
| EP-01009 | Provider Credentialing | License verification, expiration tracking, compliance artifacts | Placeholder |
| EP-01010 | Ongoing Process Improvement | Meta-epic for iteration, feedback, operational tuning | Placeholder |

---

## 9. Data Model (Entity Inventory)

From Judd's Boardmix entity diagram. Each entity below has fields and foreign keys already sketched. This is the system of record for the data model and supersedes the entity sketch in discovery v0.1.

### 9.1 Core entities

| Entity | Notes |
|---|---|
| IntakeRequest / Case | Root work item. Joins Provider, Subject, Diagnosis, CareGiver, Assessments. Email-originated variant: IntakeRequest/CaseEmail. |
| Subject | Patient record. Has emergency contact, preferred language, home address. SubjectNotes split into its own entity. |
| Provider | Clinician. Fields for name, email, phone, specialty, NPI, provider type, tax info. ProviderAddress and ProviderAvailability split into their own entities. |
| Agency / Source | The originating organization. AgencyContact splits contact people out. Primary address (full, separated). |
| TenantStaff | Staff employed by the Tenant. Email, phone, name, role, staff type. |
| CareGiver | Proxy/Associated Contact. Name, phone, address, preferred language. |
| ResolutionPlan & Schedule | Plan + recurring schedule in one entity. Start/end, frequency, services. |
| Assessment | Clinical evaluation event. Links to AssessmentMeasure. |
| AssessmentMeasure | A specific measure/instrument used in an Assessment. |
| AssessmentMeasureOption | Allowed values/choices for a measure — MultipleChoice, Range, FreeText, etc. |
| IntakeRequestAssessmentMeasureResponse | The actual captured answer for a measure on a specific Request — the response-level data. |
| NotificationLog | All notifications sent — type, recipient, content, delivery timestamp. |
| UserActivityLog | Audit trail of user actions across the platform. |
| User / UserRole | Auth layer. Users, their roles, parent/child relationships between users. Separate from domain roles. |
| Lists / ListItems | Generic reference data — likely supporting the customizable titles/properties model. |

### 9.2 Join / relationship entities

| Entity | Purpose |
|---|---|
| IntakeRequestTenantStaff | Tenant Staff assigned to a Request |
| IntakeRequestProvider | Provider assigned to a Request (the match/assignment join) |
| IntakeRequestDiagnosis | Diagnosis code(s) for the Request |
| IntakeRequestCareGiver | CareGiver(s) associated with the Request |

### Flagged in diagram: "Case Relationships, need to abstract"

Judd flagged this grouping as requiring abstraction. It's where the industry-agnostic metadata layer likely needs to live.

If each vertical can have different Subject↔Provider↔CareGiver↔Diagnosis link shapes, the join tables above can't be hard-coded per vertical. Either a generic "CaseRelationship" model with typed edges, or a per-tenant schema generator, is implied.

This is probably the single hardest piece of the data model to get right, and deserves its own design conversation before implementation.

---

## 10. Declared Technical Architecture

Direct from Judd's StoriesOnBoard prompt. These are declarations, not my recommendations — but Section 13 flags tensions inside them.

| Layer | Declared choice / intent |
|---|---|
| App model | Stand-alone server-side app |
| Primary database | Azure SQL Server (relational) |
| Service topology | Microservices |
| Integration surface | API + webhooks |
| Tenancy | Multi-tenant, "possibly using Turso" |
| Extensibility model | Industry-agnostic — customizable titles and properties per use case |
| Matching pattern | "Uber-for-X" style — find, prioritize, poll, accept |
| Case tracking | Kanban-style pipeline per Request/Project |

---

## 11. Integrations and External Dependencies

| System | Status | Notes |
|---|---|---|
| TheraHealth EMR | Explicit | EMR sync covers payroll data, signature capture, and note completion (from Golden Road step 7). Also handles Medicare Part B therapy transition referrals. API surface: TBD. |
| CliniConnect | Present | Appears as its own swim-lane in the workflow. Unclear whether it's the legacy system being replaced, a product Judd is building, or a third-party to continue integrating with. Needs resolution. |
| Slack / chat platform | Undecided | Group chat is phase 6. StoriesOnBoard mentions Slack Workflows; Golden Road mentions WhatsApp. Open: build in-app, integrate, or both? |
| External file storage | Explicit, undecided | EP-01005 covers vendor selection. Candidates: Azure Blob Storage (aligns with Azure SQL), S3, Dropbox Business, Google Workspace. Reference table in DB. |
| Email ingestion | Explicit | Phase 1. Needs mailbox-to-API pipeline. Azure Logic Apps + Graph / inbound SMTP + parser / Mailgun routes are candidates. |
| Inbound webform | Explicit | Phase 1. Form likely on firstclasstherapy.net or tenant-owned domain. |
| Inbound API + file transfer | Explicit | Phase 1. Partner systems push Requests. Auth model, rate limits, schema TBD. |
| Make.com / Robomotion RPA / Google Opal | Referenced | Pinned to the Boardmix as candidate automation/integration platforms. Scope of their role undecided. |
| TherapySync ($150/mo) | Referenced | Noted as competing/adjacent product or reference tool. |
| Payments / payroll | Unnamed | Inbound (Source → Tenant) and outbound (Tenant → Provider) flows. Processor not chosen. See Section 12 — classification (W-2 vs 1099) is flagged on the board as needing documentation. |
| Accounting / ERP | Unnamed | In-platform financial reports, but export/sync destinations (QuickBooks, Xero, NetSuite) not declared. |

---

## 12. Cross-Cutting Concerns

### 12.1 Regulatory and compliance

- **HIPAA:** Subject records, Assessments, Resolution Plans, and Service documentation are PHI. Platform, storage, TheraHealth, chat, and email ingestion all fall under HIPAA. BAAs required with every processor.
- **Medicare Part B / CMS:** Golden Road references EMR sync for signatures and notes; CMS documentation standards apply.
- **State licensure:** Florida first, but multi-tenant architecture implies other states. Licensure rules must be modeled in Credentialing.
- **W-2 vs 1099:** Flagged on the Boardmix ("Staff VS Contractor Invoicing & Payroll (Documentation)"). Directly affects payment rails, tax reporting, onboarding, and provider UX.

### 12.2 Multi-tenancy mechanics

Multi-tenant is decided. What's not yet specified:

- Tenant isolation model — database-per-tenant (supported natively by Turso), schema-per-tenant, or row-level with tenant_id discriminator
- Per-tenant branding — logos, domain, email templates
- Per-tenant configuration of labels and properties (the "customizable titles" commitment)
- Tenant admin role — who creates users, configures labels, manages billing
- Platform-operator role — who runs the platform itself (the company behind the SaaS)

### 12.3 Authentication

Seven platform roles are defined. Auth must support:

- Tenant-scoped accounts (Tenant Staff, Provider)
- Cross-tenant or limited-scope accounts (Source contacts interacting across multiple tenants? TBD)
- Subject and Proxy sign-off flows — likely passwordless/signed-link, not full accounts
- Mobile experience for Provider (service delivery + sign-off) vs desktop for Tenant Staff
- MFA policy baseline appropriate for PHI access

### 12.4 Notification fabric

First-class notification infrastructure: multi-channel (email/SMS/push/in-app), delivery receipts, retry. NotificationLog is already in the data model. Providers: SendGrid / Postmark / Azure Communication Services for email; Twilio or Azure Communication Services for SMS (Jeremy is already familiar with the Twilio surface via Quo/OpenPhone).

### 12.5 Forms with branching logic

AssessmentMeasure, AssessmentMeasureOption, and IntakeRequestAssessmentMeasureResponse in the data model form a classic dynamic-form schema. Option types include MultipleChoice, Range, FreeText. A JSON-Schema-driven renderer, or SurveyJS, would fit naturally. This is also the mechanism by which "customizable properties per use case" is delivered for clinical capture.

### 12.6 Audit & activity logging

UserActivityLog is already present in the data model. Scope (every mutation? every login? PHI access events?) needs definition. HIPAA expects audit trails on PHI access at minimum.

---

## 13. Architectural Tensions to Resolve

These are genuine tensions inside the declared architecture, not gaps. Each should be talked through with Judd before implementation.

### 13.1 Azure SQL Server alongside Turso

Azure SQL Server is a heavyweight, centralized, enterprise relational database. Turso is distributed libSQL/SQLite — its strength is edge-replicated per-tenant databases. Pairing them requires a clear split of responsibilities.

Two plausible patterns:

- Turso for per-tenant operational data (one Turso database per Tenant, cheap to spin up, isolation by design), Azure SQL for platform-wide or cross-tenant data (billing, platform accounts, aggregate reporting).
- Turso for a tenant metadata / routing catalog, Azure SQL for everything operational. Simpler, but wastes most of what makes Turso interesting.

The first pattern is more interesting and matches Turso's design intent, but it implies a significant operational discipline (migrations per tenant, backup per tenant, query federation for cross-tenant reports). Worth deciding early.

### 13.2 Industry-agnostic model on fixed entities

The entity diagram has therapy-specific names: Subject, Provider, Assessment, AssessmentMeasure. But the spec says industry-agnostic with customizable labels and properties. Tension: where does configuration end and schema begin?

Three options, increasing in flexibility and complexity:

- Fixed schema, tenant-configurable labels only (Subject → "Patient" or "Client" or "Candidate"). Simplest. Likely adequate for 80% of tenants.
- Fixed schema plus an EAV/JSON property bag on core entities for tenant-defined custom fields. Middle ground.
- Per-tenant generated schemas. Maximum flexibility, maximum operational burden.

Judd's flag on "Case Relationships, need to abstract" suggests he's already thinking about at least the middle option. This should be designed explicitly, not discovered.

### 13.3 "Uber-for-X" matching vs. staffing-agency matching

The spec says "Uber-for-X style system" — imagining a pool of available Providers being polled for a Request and accepting. But Phase 4 says Tenant Staff reviews and approves the match. These are different patterns.

Reconciliation: probably Uber-style auto-dispatch for candidate surfacing, with Tenant Staff approval as the gate before the match becomes an assignment. Optional per-tenant configuration could bypass the gate for tenants who trust the automation. Worth specifying.

### 13.4 CliniConnect's status

CliniConnect appears as its own swim-lane in the workflow. Possible interpretations:

- Legacy system being fully replaced by this platform
- Judd's existing tool, being rebuilt / subsumed into the new platform
- Separate product that will integrate with the new platform

This is a factual question with a single answer. Whatever it is, it's probably the biggest single piece of historical context for this project.

### 13.5 Microservices from day one

Microservices are declared. With a single tenant and pre-PMF product, microservices impose coordination cost without corresponding benefit. Worth a conversation about whether the starting deployment is a well-modularized monolith (clear service boundaries, single deployable) that can be split later, or whether microservices are load-bearing for some specific reason (tenant isolation? team boundaries?) that justifies the up-front cost.

---

## 14. Open Questions

Non-exhaustive. Grouped by topic.

### 14.1 Product and business

- Who owns the product commercially? Judd alone, Judd + Jeremy, a formed entity?
- Pricing model for tenants — per-seat, per-Request, tiered?
- Go-to-market beyond FCTS — who is tenant #2?
- Target go-live for FCTS?

### 14.2 FCTS-specific current state

- Active placement volume — how many in flight at once?
- Provider roster size?
- Source/Agency customer count?
- What data must be migrated and from where?
- What is CliniConnect today (see 13.4)?

### 14.3 Architecture specifics

- Turso role — per-tenant DBs or metadata catalog? (13.1)
- Configuration depth — labels only, labels + custom fields, or schema-per-tenant? (13.2)
- Matching — auto-assign, auto-candidate-with-approval, or configurable per tenant? (13.3)
- Microservices scope — true microservices from day one, or modular monolith with service boundaries? (13.5)
- Host on Azure end-to-end, or Azure SQL with a different compute platform?

### 14.4 Compliance

- HIPAA BAA posture — existing or to be established?
- SOC2 or HITRUST on the roadmap?
- Audit trail scope for UserActivityLog?
- Data residency — US-only fine, or any stricter requirement?

### 14.5 Users and workflow

- Provider experience — mobile-first? Native app or PWA?
- Do Subjects ever log in, or only sign via link?
- Source portal vs. email/API only?
- Authoritative product owner for scope disputes?

---

## 15. Recommended Next Steps

In priority order.

1. **Architecture working session.** Resolve the four highest-leverage open architecture questions (13.1–13.4). These shape everything downstream. A single working session with Judd should close most of them.
2. **Close out placeholder epics.** Decompose EP-01007 (Recruitment), EP-01008 (Onboarding), EP-01009 (Credentialing), and EP-01010 (Process Improvement). Credentialing in particular is non-trivial and state-specific.
3. **Define MVP slice.** Using the 14-phase lifecycle, identify a minimum viable slice — likely phases 1, 3, 4, 6, 7, 9 (single-loop), 11, 12, 13. Defer branching edge cases to v2.
4. **FCTS discovery session.** Structured Q&A with Angela, Tena, Dr. Gregg against Section 14. Validate the domain-specific pieces and nail down volumes.
5. **Draft architecture document.** Once the architecture tensions are resolved, produce a concrete architecture document: hosting, service boundaries, data stores, auth, file storage, notification fabric, integration surface, HIPAA posture.
6. **Align on engagement structure with Judd.** Scope, shape, commercial terms. Separate track from this discovery work but downstream of it.

End of Draft v0.2.
