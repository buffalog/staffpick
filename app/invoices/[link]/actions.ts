"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { markInvoicePaid } from "@/app/dashboard/invoices/actions";

export async function markPaidViaLink(externalLink: string): Promise<void> {
  await markInvoicePaid(externalLink);
  revalidatePath(`/invoices/${externalLink}`);
  redirect(`/invoices/${externalLink}?paid=1`);
}
