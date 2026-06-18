# Running the Matching Engine

The matching engine ranks every eligible clinician for a case so you can offer it to the best fit first.

## Triggering matching

Open a Pending intake request and use **Find Matches**. The engine evaluates the active clinician pool for the case's tenant and returns a ranked list. You can preview the ranked candidates before any offer goes out.

## Hard filters (a clinician is excluded entirely)

- **Discipline** — must match the case's discipline (PT, OT, SLP).
- **Distance** — the clinician's location must fall within the search radius (their own preferred/maximum radius, or a one-time override you set).
- **Active status** — only approved, active clinicians are considered.

## How scoring works

Candidates that pass the hard filters get a **0–100 match score**. Scoring is tiered first, then adjusted with additive bonuses:

- **Distance** — closer is better; this is the dominant factor.
- **Specialty match** — the clinician carries one or more of the case's requested specialties.
- **Language match** — the clinician speaks the patient's preferred language.
- **Availability** — the clinician has availability windows that fit.
- **Experience** — years of experience contributes a modest bonus.
- **Preferred provider** — clinicians your tenant has marked **preferred** get a ranking boost so trusted providers surface first.

Read the list top-down: the highest score is offered first.

## The language warning flag

If **no clinician in the match pool speaks the patient's preferred language**, results are flagged with a **language warning**. This is surfaced on match results and on the board card so you know the eventual assignment will have a language gap. When you see it, consider **expanding the radius** (to reach more clinicians) or **adjusting the language preference** on the case.

## Preferred providers

A **preferred** clinician is one your tenant trusts and wants to favour. Preferred status adds a scoring boost — it doesn't bypass the hard filters, so a preferred clinician of the wrong discipline or out of range still won't appear. Mark providers preferred from their profile.

## When matching comes up empty

If no clinician passes the filters, the case moves toward **No clinicians available**. Re-run with an expanded radius from the case — see **[Managing Offers](managing-offers)**.
