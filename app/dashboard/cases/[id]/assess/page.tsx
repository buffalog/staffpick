import { notFound, redirect } from "next/navigation";
import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { submitInitialAssessment } from "./actions";
import type { CasePhase } from "@/lib/enums";

export const dynamic = "force-dynamic";

export default async function AssessPage({
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
        provider_assignments: {
          include: { provider: true },
          where: { approved: true },
        },
      },
    });
    const measures = await prisma.assessmentMeasure.findMany({
      where: { active: true },
      orderBy: { display_order: "asc" },
      include: { options: { orderBy: { display_order: "asc" } } },
    });
    return { request, measures };
  });

  if (!data.request) notFound();
  const phase = data.request.phase as CasePhase;
  if (phase !== "Phase7_InitialAssessment") {
    // Don't render the form if the case is in the wrong phase.
    redirect(`/dashboard/cases/${id}`);
  }

  const assignedProvider = data.request.provider_assignments[0]?.provider ?? null;

  return (
    <div className="space-y-6 max-w-3xl">
      <header className="space-y-1">
        <p className="text-xs text-muted-foreground">
          <Link href={`/dashboard/cases/${id}`} className="underline">
            ← Case
          </Link>
        </p>
        <h1 className="text-2xl font-semibold tracking-tight">
          Initial Assessment —{" "}
          {data.request.subject
            ? `${data.request.subject.given_name} ${data.request.subject.family_name}`
            : "(no subject)"}
        </h1>
        {assignedProvider && (
          <p className="text-sm text-muted-foreground">
            Provider: {assignedProvider.given_name} {assignedProvider.family_name} ·{" "}
            {assignedProvider.specialty ?? assignedProvider.provider_type}
          </p>
        )}
      </header>

      {!assignedProvider ? (
        <div className="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
          No approved Provider on this case. Approve a match first.
        </div>
      ) : (
        <form
          action={submitInitialAssessment.bind(null, id)}
          className="space-y-6"
        >
          <input type="hidden" name="provider_id" value={assignedProvider.id} />

          {data.measures.length === 0 ? (
            <div className="rounded-md border bg-card p-4 text-sm text-muted-foreground">
              No assessment measures configured for this tenant.
            </div>
          ) : (
            <div className="space-y-4">
              {data.measures.map((m) => (
                <MeasureField key={m.id} measure={m} />
              ))}
            </div>
          )}

          <div className="space-y-1">
            <label htmlFor="notes" className="text-sm font-medium">
              Clinician notes
            </label>
            <textarea
              id="notes"
              name="notes"
              rows={4}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>

          <button
            type="submit"
            className="rounded-md bg-primary text-primary-foreground px-6 py-2 text-sm font-medium hover:opacity-90"
          >
            Submit assessment & advance to Phase 8
          </button>
        </form>
      )}
    </div>
  );
}

type MeasureWithOptions = {
  id: string;
  code: string;
  label: string;
  measure_type: string;
  unit: string | null;
  min_value: number | null;
  max_value: number | null;
  options: Array<{ value: string; label: string }>;
};

function MeasureField({ measure }: { measure: MeasureWithOptions }) {
  const inputName = `measure_${measure.id}`;
  return (
    <div className="rounded-md border bg-card p-3 space-y-2">
      <label htmlFor={inputName} className="block text-sm font-medium">
        <span className="font-mono text-xs text-muted-foreground mr-2">
          {measure.code}
        </span>
        {measure.label}
      </label>
      {measure.measure_type === "NumericRange" ? (
        <input
          id={inputName}
          name={inputName}
          type="number"
          step="any"
          min={measure.min_value ?? undefined}
          max={measure.max_value ?? undefined}
          placeholder={
            measure.unit ? `Value in ${measure.unit}` : "Numeric value"
          }
          className="w-full md:w-48 rounded-md border bg-background px-3 py-2 text-sm"
        />
      ) : measure.measure_type === "MultipleChoice" ? (
        <select
          id={inputName}
          name={inputName}
          defaultValue=""
          className="w-full md:w-72 rounded-md border bg-background px-3 py-2 text-sm"
        >
          <option value="">Select…</option>
          {measure.options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      ) : (
        <textarea
          id={inputName}
          name={inputName}
          rows={2}
          className="w-full rounded-md border bg-background px-3 py-2 text-sm"
        />
      )}
    </div>
  );
}
