import { NextResponse } from "next/server";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";

export async function GET(
  _req: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  try {
    const messages = await withSession(async () => {
      return prisma.caseMessage.findMany({
        where: { request_id: id },
        orderBy: { created_at: "asc" },
        include: {
          sender: { select: { email: true, name: true } },
        },
        take: 200,
      });
    });
    return NextResponse.json({
      messages: messages.map((m) => ({
        id: m.id,
        body: m.body,
        senderEmail: m.sender.email,
        senderName: m.sender.name,
        createdAt: m.created_at.toISOString(),
      })),
    });
  } catch {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }
}
