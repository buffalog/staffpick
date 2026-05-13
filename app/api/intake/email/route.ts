import { NextResponse } from "next/server";

// Stub — email ingestion is deferred from MVP (see docs/mvp-gaps.md).
// Once wired, this endpoint will accept parsed email payloads from
// Azure Logic Apps / Mailgun routes / SES inbound.
export function POST() {
  return NextResponse.json(
    { error: "Email ingestion not implemented in MVP" },
    { status: 501 },
  );
}
