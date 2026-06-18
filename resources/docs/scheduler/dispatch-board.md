# The Dispatch Board

The Dispatch Board is the visual pipeline that replaces tab-filtered tables. Each column is a status; each card is a case. It's your at-a-glance view of everything in flight.

## The columns

Left to right: **Pending → Matching → Offered → Assigned Pending → Active → On Hold → Completed**.

Cancelled and No-Clinicians-Available cases are deliberately kept off the board and shown in **Needs Attention** below it.

## Reading a card

Each card shows the patient's first name and last initial (full names are never shown — this is protected health information), a colour-coded discipline badge (PT/OT/SLP), the referral source, the reference number, and how many days the case has sat in its current status (green under 3 days, amber 3–7, red over 7). An on-hold case has a red border; a language mismatch shows an amber flag. Click any card to open the full case.

## Moving cards (drag transitions)

Drag a card to another column to change its status. The board is **server-authoritative** — only valid moves stick, and an invalid drag snaps the card back with a message. The moves you own:

- **Pending → On Hold** (asks for a reason)
- **On Hold → Pending** (resume)
- **Offered → On Hold** (asks for a reason)
- **Assigned Pending → Active** (activate the case)
- **Active → Completed** (close it out)
- **Active → On Hold** (asks for a reason)

Cards in **Matching** and **Completed** have no manual move, so they aren't draggable. Moving anything **into** Matching or Offered is blocked — those are driven by the engine, not by hand. Backwards moves (e.g. Active → Pending) are rejected.

## Needs Attention

The amber band under the board holds the cases that fell out of the happy path:

- **No Clinicians Available** — the offer queue was exhausted. Each card has a **Re-trigger** action to re-run matching with an expanded radius.
- **Cancelled** — read-only record of withdrawn cases.

## Monitor mode & live updates

The board refreshes every 30 seconds and shows an "updated X seconds ago" indicator. Use the **full-screen** button (top-right, Esc to exit) to put the board on an office monitor edge-to-edge. The right-hand stats panel shows live totals — active cases, the PT/OT/SLP breakdown, cases offered, and how many need attention.
