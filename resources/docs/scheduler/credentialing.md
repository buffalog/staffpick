# Credentialing

The **Credentialing Queue** is where you keep provider documents current. It surfaces every credential across your tenant that needs attention — unverified, failed, or expiring within 30 days — so nothing lapses silently.

## What's in the queue

Each row is a single provider credential: the provider, the document type (e.g. State License, CPR, Liability Insurance, W-9, Background Check), the licence number where relevant, its verification status, the expiry date, and the verification method. Filter by status, document type, or "expiring soon".

## The three verification methods

StaffPick verifies different documents in different ways:

- **Automated (API)** — for disciplines with a live licensing API (e.g. PT licences). **Verify Now** calls the board's verification service with the provider's licence number and their state, and marks the credential **verified** or **failed** automatically based on the response.
- **Deep-link** — for disciplines whose board only offers a lookup page (e.g. OT and SLP via the Florida MQA). **Verify Now** opens the pre-filled licensing-board page in a new tab; you confirm the result by eye, and the credential is marked **pending manual confirmation**.
- **Manual** — for documents with no online source (CPR cards, W-9, background checks, liability insurance). **Verify Now** opens a short form where you record the outcome (verified/failed) and optional notes; your name is stamped on the result.

## Using Verify Now

1. Find the credential in the queue (or on the provider's record).
2. Click **Verify Now**.
3. Depending on the method, the action either runs the API check, opens the licensing-board page, or shows the manual confirmation form.
4. The status updates immediately and the audit trail records who verified it and when.

## Expiry alerts

A daily check finds credentials expiring within the warning window and alerts tenant admins via the in-app bell and Slack. A credential whose type is set to **deactivate on expiry** will pull the provider out of matching once it lapses — so clearing the queue keeps your active pool clean.

## The license number matters

Automated and deep-link verification both need the provider's **licence number** on file. Clinicians enter this during onboarding; if it's missing, the credential falls back to manual.
