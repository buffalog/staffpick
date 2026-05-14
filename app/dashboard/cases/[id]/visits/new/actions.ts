"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";

const visitSchema = z.object({
  plan_id: z.string().min(1),
  provider_id: z.string().min(1),
  service_code: z.string().min(1),
  visit_date: z.string().min(1),
  duration_minutes: z.coerce.number().int().positive().max(600),
  notes: z.string().optional(),
  subject_signature_value: z.string().min(1),
  proxy_signature_value: z.string().optional(),
});

export async function recordVisit(
  requestId: string,
  formData: FormData,
): Promise<void> {
  const parsed = visitSchema.safeParse({
    plan_id: formData.get("plan_id"),
    provider_id: formData.get("provider_id"),
    service_code: formData.get("service_code"),
    visit_date: formData.get("visit_date"),
    duration_minutes: formData.get("duration_minutes"),
    notes: formData.get("notes"),
    subject_signature_value: formData.get("subject_signature_value"),
    proxy_signature_value: formData.get("proxy_signature_value"),
  });
  if (!parsed.success) {
    throw new Error(
      `Visit validation failed: ${parsed.error.issues.map((i) => i.message).join("; ")}`,
    );
  }
  const data = parsed.data;
  const proxy = data.proxy_signature_value?.trim();

  await withSession(async (ctx) => {
    await prisma.service.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        plan_id: data.plan_id,
        provider_id: data.provider_id,
        service_code: data.service_code,
        visit_date: new Date(data.visit_date),
        duration_minutes: data.duration_minutes,
        notes: data.notes ?? null,
        subject_signature_type: "TypedName",
        subject_signature_value: data.subject_signature_value,
        proxy_signature_type: proxy ? "TypedName" : null,
        proxy_signature_value: proxy ?? null,
        signed_at: new Date(),
        billable: true,
      },
    });
  });

  revalidatePath(`/dashboard/cases/${requestId}`);
  redirect(`/dashboard/cases/${requestId}`);
}
