import { NextResponse } from "next/server";

// Stub — file-transfer ingestion (SFTP/Blob) is deferred from MVP.
export function POST() {
  return NextResponse.json(
    { error: "File-transfer ingestion not implemented in MVP" },
    { status: 501 },
  );
}
