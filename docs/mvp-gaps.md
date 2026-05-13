# StaffPick — MVP Gaps

Every shortcut, stub, and deferred item in the MVP. Each entry will get a post-MVP effort estimate at Phase 5 closeout.

Seeded at Phase 0 from `<scope_decisions_locked>` and known design punts.

## Domain modeling

### Case Relationships abstraction
Discovery §9.2 flags "Case Relationships, need to abstract" — a generic `CaseRelationship` model with typed edges that allows per-vertical link shapes (Subject↔Provider↔CareGiver↔Diagnosis). Punted entirely for MVP. The current schema hard-codes the therapy-vertical join tables (`IntakeRequestProvider`, `IntakeRequestDiagnosis`, etc.).

**Post-MVP**: design typed-edge relationship model; migrate existing join tables to it; back-fill UI.

### W-2 vs 1099 Provider classification
Discovery §12.1 flagged "Staff VS Contractor Invoicing & Payroll (Documentation)" as needing real treatment. The current schema has a `Provider.classification` String column but no downstream logic — payment CSV does not distinguish. Affects payment rails, tax reporting, onboarding flow, provider UX, 1099 generation.

**Post-MVP**: classification-aware payout flow; W-2 payroll integration; 1099-NEC generation pipeline.

## Lifecycle cuts

### Phase 3(c) provider self-accept poll
Discovery §6 Phase 3 includes "(c) Polls prioritized candidates for acceptance." MVP cuts this — auto-rank surfaces candidates directly to Tenant Staff with no provider opt-in step.

**Post-MVP**: add provider-side accept/decline flow with timeout fallback to staff manual selection.

### Phase 9 signed-link visit signature flow
Discovery §12.3 specifies signed-link auth for Subject/Proxy sign-off (passwordless, no full accounts). MVP captures typed-name only and stores in `Service.subject_signature_value` with `subject_signature_type = "TypedName"`. Schema is forward-compatible (other SignatureType values supported).

**Post-MVP**: signed-link email/SMS flow that links to a sign page, image-based signature capture stored in Blob.

## Integration stubs

### TheraHealth EMR integration
Stubbed entirely. No API calls. Phase 10/13 references to EMR sync exist as comments only.

**Post-MVP**: research TheraHealth API surface (Discovery §11 says "TBD"); implement payroll data pull, signature capture, note completion sync.

### Real payment processing (Source → Tenant)
Phase 12 records invoice status only. "Mark Paid" is a manual action by the Source via a magic-link page. No Stripe/ACH integration.

**Post-MVP**: Stripe Invoicing API for hosted invoices and ACH/card payment capture; webhook → `Invoice.paid_at`.

### Real payment processing (Tenant → Provider)
Phase 13 generates a CSV export of payable visits per Provider. No actual ACH/payroll integration.

**Post-MVP**: integrate Gusto or Stripe Connect for W-2 payroll and 1099 contractor payouts; classification-aware routing (see W-2 vs 1099 above).

### Email / API / file ingestion
Discovery §6 Phase 1 lists email, webform, API, and file transfer. MVP webform only; email/API/file ingestion routes exist and return 501.

**Post-MVP**: Azure Logic Apps + Graph mailbox-to-API pipeline for email; OpenAPI-defined inbound API for partner systems; SFTP/Blob-watcher for file transfer.

### External chat (Slack/WhatsApp)
Phase 6 collaboration is in-app threaded messaging only. Discovery §11 mentions Slack Workflows and WhatsApp as candidates.

**Post-MVP**: Slack thread mirroring as opt-in; WhatsApp Business API integration if FCTS volume justifies.

## UX cuts

### Provider native mobile app
Responsive web only with mobile-first CSS. No native iOS/Android.

**Post-MVP**: evaluate Expo PWA wrapper vs Tauri Mobile vs full native. Provider sign-off and visit recording are the primary mobile flows.

### Branching dynamic forms / SurveyJS / JSON-Schema renderer
Static forms only. `AssessmentMeasure` types limited to `MultipleChoice | FreeText | NumericRange`. No conditional logic, no skip patterns.

**Post-MVP**: introduce SurveyJS or build a JSON-Schema-driven renderer to support clinical capture variability across tenants.

## Compliance posture

### HIPAA compliance certification
Architectural prerequisites in place (TLS, TDE, audit log, RBAC, secrets discipline, BAA-ready vendor list). No active certification effort.

**Post-MVP**: execute BAAs with Resend, Azure, Railway. Pursue SOC2 / HITRUST when revenue justifies.

## Cut epics (entirely deferred from MVP)

- **EP-01007 Provider Recruitment** — sourcing and pipelining new Providers.
- **EP-01008 Provider Onboarding and Training** — hire → active availability.
- **EP-01009 Provider Credentialing** — license verification, expiration tracking, compliance artifacts. **Note**: state-specific and non-trivial; will be needed before second tenant.
- **EP-01010 Ongoing Process Improvement** — meta-epic for iteration, feedback, operational tuning.
