import { notFound, redirect } from "next/navigation";
import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import type { CasePhase } from "@/lib/enums";
import { createResolutionPlan } from "./actions";

export const dynamic = "force-dynamic";

export default async function NewPlanPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  const request = await withSession(async () =>
    prisma.intakeRequest.findFirst({
      where: { id },
      include: { subject: true },
    }),
  );
  if (!request) notFound();
  if ((request.phase as CasePhase) !== "Phase8_PlanDocumentation") {
    redirect(`/dashboard/cases/${id}`);
  }

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="space-y-6 max-w-2xl">
      <header className="space-y-1">
        <p className="text-xs text-muted-foreground">
          <Link href={`/dashboard/cases/${id}`} className="underline">
            ← Case
          </Link>
        </p>
        <h1 className="text-2xl font-semibold tracking-tight">
          New Resolution Plan —{" "}
          {request.subject
            ? `${request.subject.given_name} ${request.subject.family_name}`
            : "(no subject)"}
        </h1>
        <p className="text-sm text-muted-foreground">
          Document the plan derived from the initial assessment. Saving advances
          the case to Phase 9 (Service Delivery).
        </p>
      </header>

      <form
        action={createResolutionPlan.bind(null, id)}
        className="space-y-4 rounded-md border bg-card p-4"
      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-1">
            <label htmlFor="start_date" className="text-sm font-medium">
              Start date *
            </label>
            <input
              id="start_date"
              name="start_date"
              type="date"
              defaultValue={today}
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <div className="space-y-1">
            <label htmlFor="end_date" className="text-sm font-medium">
              End date (optional)
            </label>
            <input
              id="end_date"
              name="end_date"
              type="date"
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div className="space-y-1">
          <label htmlFor="frequency" className="text-sm font-medium">
            Frequency *
          </label>
          <input
            id="frequency"
            name="frequency"
            type="text"
            required
            placeholder="e.g. 3x/week for 6 weeks"
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
        </div>

        <div className="space-y-1">
          <label htmlFor="services_summary" className="text-sm font-medium">
            Services
          </label>
          <textarea
            id="services_summary"
            name="services_summary"
            rows={3}
            placeholder="e.g. PT-VISIT focusing on gait + balance training; bi-weekly re-evaluation."
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
        </div>

        <button
          type="submit"
          className="rounded-md bg-primary text-primary-foreground px-6 py-2 text-sm font-medium hover:opacity-90"
        >
          Save plan & advance to Phase 9
        </button>
      </form>
    </div>
  );
}
