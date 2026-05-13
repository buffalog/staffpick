import { notFound } from "next/navigation";
import { prismaBase } from "@/lib/prisma";
import { IntakeForm } from "./intake-form";

// Single-tenant deployment for MVP — webform always targets FCTS.
// Multi-tenant routing (`/intake/[tenantSlug]`) is post-MVP.
const TENANT_SLUG = "fcts";

export const dynamic = "force-dynamic";

export default async function IntakeWebformPage() {
  const tenant = await prismaBase.tenant.findUnique({
    where: { slug: TENANT_SLUG },
    include: {
      labels: true,
      lists: {
        where: { key: "icd10_codes" },
        include: { items: { where: { active: true }, orderBy: { display_order: "asc" } } },
      },
    },
  });
  if (!tenant) notFound();

  const labelMap = new Map(tenant.labels.map((l) => [l.entity, l.label]));
  const icd10 = (tenant.lists[0]?.items ?? []).map((i) => ({
    value: i.value,
    label: i.label,
  }));

  return (
    <div className="min-h-screen bg-background text-foreground">
      <div className="max-w-3xl mx-auto px-4 py-10">
        <header className="mb-6">
          <h1 className="text-2xl font-semibold tracking-tight">
            Refer a {labelMap.get("Subject") ?? "patient"} to {tenant.name}
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Complete the form below to submit a new {labelMap.get("IntakeRequest")?.toLowerCase() ?? "referral"}.
            Required fields are marked *. You can edit details with our team after submission.
          </p>
        </header>
        <IntakeForm
          icd10Options={icd10}
          subjectLabel={labelMap.get("Subject") ?? "Patient"}
          providerLabel={labelMap.get("Provider") ?? "Clinician"}
          turnstileSiteKey={process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? ""}
        />
      </div>
    </div>
  );
}
