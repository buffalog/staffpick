import { NextResponse } from "next/server";

// Stub — partner-system API ingestion is deferred from MVP.
// (Path is /api/intake/external rather than /api/intake/api to avoid
// the visual "api/intake/api" confusion in logs.)
export function POST() {
  return NextResponse.json(
    { error: "Partner API ingestion not implemented in MVP" },
    { status: 501 },
  );
}
