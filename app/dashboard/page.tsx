import { auth } from "@/auth";

export default async function DashboardHome() {
  const session = await auth();
  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Welcome{session?.user?.name ? `, ${session.user.name}` : ""}.
        </p>
      </header>
      <section className="rounded-lg border bg-card p-4">
        <h2 className="text-sm font-medium mb-2">Phase 1 status</h2>
        <ul className="text-sm space-y-1 text-muted-foreground">
          <li>✓ Auth (credentials + TOTP + magic-link via Resend)</li>
          <li>✓ Tenant scope middleware (Prisma extension)</li>
          <li>✓ Audit log on mutations + PHI reads</li>
          <li>✓ Seed: FCTS tenant, 3 Tenant Staff, 5 Providers, 2 Sources</li>
          <li>○ Intake / cases / matching / invoicing — Phase 2 onward</li>
        </ul>
      </section>
    </div>
  );
}
