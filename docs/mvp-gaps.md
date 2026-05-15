# StaffPick — MVP Gaps

Every shortcut, stub, and deferred item in the MVP, with a rough post-MVP
effort estimate. Effort bands are developer-time for one engineer:
**S** ≈ ≤3 days · **M** ≈ ~1 week · **L** ≈ 2–3 weeks · **XL** ≈ a month+.

Finalized at Phase 5. Product/feature shortcuts live here; implementation
compromises live in `tech-debt.md`.

## Domain modeling

### Case Relationships abstraction
Discovery §9.2 flags "Case Relationships, need to abstract" — a generic `CaseRelationship` model with typed edges that allows per-vertical link shapes (Subject↔Provider↔CareGiver↔Diagnosis). Punted entirely for MVP. The current schema hard-codes the therapy-vertical join tables (`IntakeRequestProvider`, `IntakeRequestDiagnosis`, etc.).

**Post-MVP**: design a typed-edge relationship model; migrate the existing join tables onto it; back-fill UI. **Effort: L** — schema redesign + data migration + UI rework. Needed before a non-therapy tenant onboards.

### W-2 vs 1099 Provider classification
Discovery §12.1 flagged "Staff VS Contractor Invoicing & Payroll" as needing real treatment. The schema has a `Provider.classification` string column but no downstream logic — the payroll CSV doesn't distinguish. Affects payment rails, tax reporting, onboarding, provider UX, 1099 generation.

**Post-MVP**: classification-aware payout flow; W-2 payroll integration; 1099-NEC generation. **Effort: L** — entangled with the payroll integration below.

## Lifecycle cuts

### Phase 3(c) provider self-accept poll
Discovery §6 Phase 3 includes "(c) Polls prioritized candidates for acceptance." MVP cuts this — auto-rank surfaces candidates straight to Tenant Staff with no provider opt-in step.

**Post-MVP**: provider-side accept/decline flow with a timeout fallback to staff manual selection. **Effort: M** — new flow + notifications + a timeout job.

### Phase 9 signed-link visit signature flow
Discovery §12.3 specifies signed-link auth for Subject/Proxy sign-off (passwordless, no full accounts). MVP captures typed-name only — `Service.subject_signature_value` with `subject_signature_type = "TypedName"`. Schema is forward-compatible (other `SignatureType` values supported).

**Post-MVP**: signed-link email/SMS flow to a sign page; image-based signature capture stored in Azure Blob. **Effort: M**.

## Integration stubs

### TheraHealth EMR integration
Stubbed entirely — no API calls. Phase 10/13 EMR-sync references exist as comments only.

**Post-MVP**: research the TheraHealth API surface (Discovery §11 says "TBD"); implement payroll-data pull, signature capture, note-completion sync. **Effort: XL** — and gated on a discovery spike, since the API surface is unknown.

### Real payment processing (Source → Tenant)
Phase 12 records invoice status only. "Mark Paid" is a manual action by the Source via a magic-link page. No Stripe/ACH.

**Post-MVP**: Stripe Invoicing API for hosted invoices + ACH/card capture; webhook → `Invoice.paid_at`. **Effort: M**.

### Real payment processing (Tenant → Provider)
Phase 13 exports a CSV of payable visits per Provider. No ACH/payroll integration.

**Post-MVP**: Gusto or Stripe Connect for W-2 payroll and 1099 contractor payouts; classification-aware routing (see W-2 vs 1099). **Effort: L**.

### Email / API / file ingestion
Discovery §6 Phase 1 lists email, webform, API, and file transfer. MVP is webform only; the email/API/file routes exist and return 501.

**Post-MVP**: Azure Logic Apps + Graph mailbox-to-API pipeline; OpenAPI-defined inbound API for partner systems; SFTP/Blob-watcher for file transfer. **Effort: L** — three channels, each with its own parsing + auth.

### External chat (Slack/WhatsApp)
Phase 6 collaboration is in-app threaded messaging only. Discovery §11 mentions Slack Workflows and WhatsApp.

**Post-MVP**: opt-in Slack thread mirroring; WhatsApp Business API if FCTS volume justifies. **Effort: M** per channel.

## UX cuts

### Provider native mobile app
Responsive web only, mobile-first CSS. No native iOS/Android.

**Post-MVP**: evaluate Expo / PWA wrapper vs full native. Provider sign-off + visit recording are the primary mobile flows. **Effort: XL**.

### Branching dynamic forms / SurveyJS / JSON-Schema renderer
Static forms only. `AssessmentMeasure` types limited to `MultipleChoice | FreeText | NumericRange` — no conditional logic, no skip patterns.

**Post-MVP**: SurveyJS or a JSON-Schema-driven renderer for clinical-capture variability across tenants. **Effort: L**.

### List pagination is single-page
`/dashboard/inbox` and `/dashboard/cases` cap at `take: 25` with no cursor / next-page — beyond 25 records you can't reach them (filters mitigate). The matcher's `provider.findMany` is unbounded (fine at MVP provider counts, not at scale).

**Post-MVP**: cursor pagination on the list pages; bound or DB-side the matcher's candidate query. **Effort: S**.

## Compliance posture

### HIPAA compliance certification
Architectural prerequisites in place (TLS, Azure SQL TDE, audit log, RBAC, secrets discipline, BAA-ready vendor list). No active certification effort. Note: the HIPAA Security Rule NPRM (Dec 2024) makes MFA explicitly mandatory — passkeys already satisfy it.

**Post-MVP**: execute BAAs with Resend, Azure, Railway; pursue SOC2 / HITRUST when revenue justifies. **Effort: XL** — mostly vendor paperwork + audit, not engineering.

### Password bootstrap is single-factor
Auth is WebAuthn passkeys (day-to-day, phishing-resistant, NIST AAL2/3) with email+password as the bootstrap/recovery factor. The bootstrap step itself is single-factor — a user with no passkey enrolled yet is one credential away from access. Acceptable for MVP: passkeys are the real factor; the bootstrap exists only because passkeys are device-bound and can't be pre-provisioned. (TOTP and email-OTP were removed — email-OTP isn't valid MFA under NIST 800-63B, the HHS HIPAA benchmark.)

**Post-MVP**: harden the bootstrap — email-verification on password login, or force passkey enrollment before full PHI access, or fall the password path back to a magic-link. **Effort: S**.

### Notification body previews in dev logs
`lib/email.ts` falls back to a structured `pino` log when `RESEND_API_KEY` is unset — including a 200-char body preview, which for Phase 5 match-notification emails can contain a patient name. Fine for local dev; on a real deployment `RESEND_API_KEY` must be set so bodies aren't logged.

**Post-MVP**: redact names from the preview, or drop the preview entirely and log only metadata. **Effort: S**.

## Cut epics (entirely deferred from MVP)

- **EP-01007 Provider Recruitment** — sourcing and pipelining new Providers. **Effort: L**.
- **EP-01008 Provider Onboarding and Training** — hire → active availability. **Effort: L**.
- **EP-01009 Provider Credentialing** — license verification, expiration tracking, compliance artifacts. State-specific and non-trivial; **needed before a second tenant**. **Effort: XL**.
- **EP-01010 Ongoing Process Improvement** — meta-epic for iteration, feedback, operational tuning. **Effort: ongoing**, not a bounded deliverable.
