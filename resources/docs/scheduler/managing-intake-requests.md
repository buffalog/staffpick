# Managing Intake Requests

An **intake request** is a single patient referral. This is the record everything else hangs off of.

## Creating a request

There are three ways a request is created:

- **Referral source link** — a referring office submits the public intake form at their unique link. The request arrives with a reference number (e.g. `R-4F9A2C`) already assigned.
- **Manually** — click **New Intake** (on the board's Pending column or the Intake Requests list) and fill in the form yourself.
- **Slack** — a message containing the tenant's referral keyword in a connected channel creates a draft request automatically.

## Reviewing a request

Open any request to see the patient (subject), referral source, discipline, requested specialties, service location, authorization details, and the activity history. Check that the **discipline** and **address** are correct before matching — both are hard filters, so a wrong value will exclude every good clinician.

## Status meanings

| Status | Meaning |
|---|---|
| **Pending** | New, not yet matched. Awaiting your action. |
| **Matching** | The engine is building (or has built) a ranked candidate list. Engine-controlled. |
| **Offered** | One or more clinicians have been offered the case and a response window is open. Engine-controlled. |
| **Assigned (pending)** | A clinician accepted; awaiting activation/paperwork. |
| **Active** | The case is live with an assigned clinician. |
| **On hold** | Temporarily paused with a reason (e.g. awaiting authorization). |
| **No clinicians available** | The offer queue was exhausted with no acceptance. Needs attention. |
| **Completed** | Care delivered and the case closed out. |
| **Cancelled** | Withdrawn before assignment. Read-only. |

Cases in **No clinicians available** and **Cancelled** don't appear as board columns — find them in the board's **Needs Attention** section.

## Good habits

- Confirm discipline and geocoded location before running matching.
- Add any specialty requirements so scoring favours the right clinicians.
- Use **On hold** (not Cancelled) when a case is only paused — it keeps the history intact and can be resumed.
