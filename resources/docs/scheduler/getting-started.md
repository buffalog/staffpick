# Getting Started

Welcome to StaffPick. As a scheduler you move a patient referral from the moment it arrives to the moment a clinician is confirmed on the case. This guide explains the core pipeline so the rest of the system makes sense.

## The pipeline at a glance

Every case flows through the same stages:

**Intake → Matching → Offer → Assignment**

1. **Intake** — a referral enters the system, either submitted by a referral source through their public link, created by you in the admin panel, or pulled in from a Slack message. It starts in **Pending**.
2. **Matching** — the matching engine scores every eligible clinician against the case (discipline, distance, language, availability, specialties, preferred status) and ranks them.
3. **Offer** — the top-ranked clinicians are offered the case one at a time. Each offer has a response window; if it expires or is declined, the next clinician is offered automatically.
4. **Assignment** — the first clinician to accept becomes the assigned provider and the case moves to **Active**.

## Key concepts

- **Discipline** — the therapy type a case requires (PT, OT, SLP). Only clinicians of that discipline are ever matched.
- **Match score** — a 0–100 ranking. Distance and discipline are hard filters; language, specialties, availability, experience and preferred status adjust the score.
- **Offer queue** — the ordered list of clinicians waiting to be offered the case. You can re-trigger it with an expanded radius if it runs out.
- **The Dispatch Board** — the visual Kanban view of every active case by status. It's where you'll spend most of your day.

## Where to go next

- **[The Dispatch Board](dispatch-board)** — your day-to-day cockpit.
- **[Managing Intake Requests](managing-intake-requests)** — create and review cases.
- **[Running the Matching Engine](running-the-matching-engine)** — find and rank clinicians.

> Tip: a **?** icon in the top-right of most pages opens contextual help for exactly what you're looking at.
