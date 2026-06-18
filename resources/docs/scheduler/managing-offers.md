# Managing Offers

Once a case is matched, StaffPick offers it to clinicians **one at a time, in ranked order**. This keeps a case from being double-booked and gives your best-fit clinician first refusal.

## How the offer pipeline works

1. The engine builds the ranked candidate list and the case moves to **Offered**.
2. The top clinician receives the offer through their preferred channel (portal, email, or SMS). The offer contains **no protected health information** — just enough for the clinician to decide (discipline, general area, schedule, rate).
3. A response window opens. The clinician can **accept** or **decline** (with a reason).
4. If they accept, they become the assigned provider and the case moves to **Assigned Pending**. Any still-open offers for that case are withdrawn.
5. If they decline or the window **expires**, the next clinician in the queue is offered automatically.

## Offer timeouts

Each offer has a sequential response window. When it elapses without a response, the offer expires and the queue advances on its own — you don't have to babysit it. A scheduled check sweeps for expired offers and moves the queue along.

## When the queue is exhausted

If every clinician in the queue declines or times out, the case moves to **No Clinicians Available** and lands in the board's **Needs Attention** section. You and your tenant admins are alerted via the in-app bell and Slack.

## Re-triggering with an expanded radius

From a No-Clinicians-Available case (or its Needs Attention card), use **Re-trigger Matching** and enter a larger radius. This:

- re-runs the engine for **this case only** with the expanded radius,
- does **not** change your tenant defaults or any clinician's own radius preferences,
- rebuilds the offer queue and starts offering again.

Use it when a case is in a thin coverage area — widening the search often surfaces clinicians who were just outside the original radius.

## Tips

- If a case keeps timing out, check whether the rate, schedule, or area is realistic before widening the radius further.
- A **language warning** on the case is a sign the pool is thin for that language — expanding the radius is usually the first move.
