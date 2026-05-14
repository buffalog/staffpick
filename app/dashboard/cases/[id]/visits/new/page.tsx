import { notFound, redirect } from "next/navigation";
import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import type { CasePhase } from "@/lib/enums";
import { recordVisit } from "./actions";

export const dynamic = "force-dynamic";

export default async function NewVisitPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  const data = await withSession(async () => {
    const request = await prisma.intakeRequest.findFirst({
      where: { id },
      include: {
        subject: true,
        resolution_plans: {
          where: { active: true },
          orderBy: { start_date: "desc" },
          take: 1,
        },
        provider_assignments: {
          where: { approved: true },
          include: { provider: true },
        },
      },
    });
    const rates = await prisma.tenantServiceRate.findMany({
      orderBy: { service_code: "asc" },
    });
    return { request, rates };
  });

  if (!data.request) notFound();
  const phase = data.request.phase as CasePhase;
  if (phase !== "Phase9_ServiceDelivery") {
    redirect(`/dashboard/cases/${id}`);
  }
  const plan = data.request.resolution_plans[0];
  if (!plan) redirect(`/dashboard/cases/${id}`);

  const provider = data.request.provider_assignments[0]?.provider;
  if (!provider) redirect(`/dashboard/cases/${id}`);

  const subjectName = data.request.subject
    ? `${data.request.subject.given_name} ${data.request.subject.family_name}`
    : "(no subject)";
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
          Record visit — {subjectName}
        </h1>
        <p className="text-sm text-muted-foreground">
          Provider: {provider.given_name} {provider.family_name} ·{" "}
          {provider.specialty}
          {" · "}
          Plan: {plan.frequency}
        </p>
      </header>

      <form
        action={recordVisit.bind(null, id)}
        className="space-y-4 rounded-md border bg-card p-4"
      >
        <input type="hidden" name="plan_id" value={plan.id} />
        <input type="hidden" name="provider_id" value={provider.id} />

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="space-y-1">
            <label htmlFor="visit_date" className="text-sm font-medium">
              Visit date *
            </label>
            <input
              id="visit_date"
              name="visit_date"
              type="date"
              defaultValue={today}
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <div className="space-y-1">
            <label htmlFor="duration_minutes" className="text-sm font-medium">
              Duration (min) *
            </label>
            <input
              id="duration_minutes"
              name="duration_minutes"
              type="number"
              min={1}
              max={600}
              defaultValue={45}
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <div className="space-y-1">
            <label htmlFor="service_code" className="text-sm font-medium">
              Service code *
            </label>
            <select
              id="service_code"
              name="service_code"
              required
              defaultValue=""
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            >
              <option value="" disabled>
                Select…
              </option>
              {data.rates.map((r) => (
                <option key={r.id} value={r.service_code}>
                  {r.service_code} — {r.description ?? "—"} · $
                  {(r.rate_cents / 100).toFixed(2)}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="space-y-1">
          <label htmlFor="notes" className="text-sm font-medium">
            Session notes
          </label>
          <textarea
            id="notes"
            name="notes"
            rows={3}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
        </div>

        <fieldset className="space-y-3 rounded-md border bg-muted/30 p-3">
          <legend className="text-sm font-medium px-1">Sign-off</legend>
          <p className="text-xs text-muted-foreground">
            Type the {subjectName.split(" ")[0]}&apos;s name as their
            signature. If signed by a proxy/caregiver, enter their name in
            the proxy field as well.
          </p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1">
              <label
                htmlFor="subject_signature_value"
                className="text-sm font-medium"
              >
                Subject signature (typed) *
              </label>
              <input
                id="subject_signature_value"
                name="subject_signature_value"
                type="text"
                required
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
            </div>
            <div className="space-y-1">
              <label
                htmlFor="proxy_signature_value"
                className="text-sm font-medium"
              >
                Proxy signature (optional)
              </label>
              <input
                id="proxy_signature_value"
                name="proxy_signature_value"
                type="text"
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
            </div>
          </div>
        </fieldset>

        <button
          type="submit"
          className="rounded-md bg-primary text-primary-foreground px-6 py-2 text-sm font-medium hover:opacity-90"
        >
          Record visit
        </button>
      </form>
    </div>
  );
}
