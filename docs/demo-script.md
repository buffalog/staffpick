# StaffPick — demo script

A ~8-minute walkthrough for Judd and Jon. Three acts following a referral
through the platform, plus a short under-the-hood coda. The middle and final
acts use pre-seeded cases so you don't click through all 14 phases live.

**Before you start:**
- App: `https://app-production-58e5.up.railway.app`
- Reset the demo cases: `pnpm exec tsx prisma/seed-demo.ts` against the live DB
  (see `runbook.md`) — gives you `DEMO-1` (Phase 2), `DEMO-2` (Phase 9),
  `DEMO-3` (Phase 12) fresh.
- Sign in ahead of time as `angela.searcy@fcts.local` (password
  `LocalDev_Pa55word!`, "Sign in with email & password"). Optionally enroll a
  passkey at **Account** first so you can show the one-tap sign-in.
- Have a second browser/incognito window ready for the public webform.

---

## Act 1 — A referral comes in (~2 min) · *live*

> "A referral to FCTS today is a phone call or a fax. Here's what it becomes."

1. In the incognito window, go to **`/intake`** — the public referral webform.
2. Fill it in as if you're a discharge planner at a hospital: a patient name,
   date of birth, pick a diagnosis from the **ICD-10 search** (type "stroke"
   or "Z47.1"), requested service PT, a schedule preference. Submit.
3. Switch to Angela's window → **Intake Inbox**. The referral is sitting there
   — sanitized, structured, with the diagnosis attached. **Accept** it.

> "No re-keying. The referral is a structured case the moment it arrives, and
> it's already scoped to FCTS — every other agency on the platform is invisible."

---

## Act 2 — An active case (~2.5 min) · *pre-seeded `DEMO-2`*

> "Here's a case three weeks in — Marcus Bell, stroke rehab."

1. **Cases** → open **Bell, Marcus** (Phase 9, Service Delivery).
2. Walk the case detail top to bottom:
   - **Patient / Source / Diagnosis** — the referral data, structured.
   - **Provider assignment** — matched and approved. Mention the matcher ranks
     candidates by availability + location; staff approve before it binds.
   - **Resolution plan + visits** — the plan, and every logged visit with the
     patient's sign-off.
   - **Conversation** — the in-app thread tying staff and the clinician together.
   - **Activity timeline** — every state change and PHI access, audited.

> "One screen is the whole operational picture. The 'CliniConnect 2-hour
> process' from the discovery doc — that's this, and it's instant."

---

## Act 3 — The financial close (~2.5 min) · *pre-seeded `DEMO-3`*

> "And it carries the case all the way to paid."

1. **Cases** → open **Inglesias, Rosa** (Phase 12, Invoice & Payment).
2. In the **Invoices** card, open the **Source view** link in the incognito
   window — that's the magic-link invoice the agency receives. No account, no
   login. Click **Mark paid**.
3. Back in Angela's window, the case is now Phase 13. Click **Payroll CSV** —
   it downloads the provider-payment export, and the case closes (Phase 14).

> "Invoice generation is automatic off the visit log against the rate table.
> The Source pays through a link. Provider payroll is a one-click export.
> Money in, money out, case closed."

---

## Coda — under the hood (~1 min) · *for Judd*

- **Auth** — passkeys (Face ID / Touch ID), phishing-resistant, the NIST-grade
  factor. Email-OTP was cut on purpose: it isn't valid MFA under NIST 800-63B,
  which is the HIPAA benchmark.
- **Multi-tenant** — every record carries `tenant_id`, enforced at the database
  layer by a Prisma extension. FCTS is tenant #1; the engine is industry-agnostic.
- **Audit** — every PHI read and every mutation is logged automatically.
- **The 14-phase lifecycle** is a real state machine — illegal transitions throw,
  the UI only ever offers legal next steps.
- **Built in ~5 weeks**, phased, every phase reviewed. `docs/mvp-gaps.md` is the
  honest list of what's stubbed and what's next.

---

## If something goes sideways

- Stale demo cases / wrong phase → re-run `prisma/seed-demo.ts`, refresh.
- Can't sign in → use the password path ("Sign in with email & password"), not
  a passkey, if you're on a device without one enrolled.
- A page errors → `railway logs --service app`; most live issues are the DB
  proxy or a missing env var, not app logic.
