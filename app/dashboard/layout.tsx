import { redirect } from "next/navigation";
import Link from "next/link";
import { auth, signOut } from "@/auth";
import type { UserRoleType } from "@/lib/enums";

type NavItem = {
  href: string;
  label: string;
  roles?: ReadonlyArray<UserRoleType>;
};

// 7-role-aware nav. `roles` undefined means visible to everyone.
const NAV: ReadonlyArray<NavItem> = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/dashboard/inbox", label: "Intake Inbox", roles: ["TenantStaff", "TenantAdmin"] },
  { href: "/dashboard/cases", label: "Cases", roles: ["TenantStaff", "TenantAdmin", "Provider"] },
  { href: "/dashboard/providers", label: "Providers", roles: ["TenantStaff", "TenantAdmin"] },
  { href: "/dashboard/invoices", label: "Invoices", roles: ["TenantStaff", "TenantAdmin", "Source"] },
  { href: "/dashboard/admin", label: "Platform Admin", roles: ["PlatformAdmin"] },
];

async function handleSignOut() {
  "use server";
  await signOut({ redirectTo: "/login" });
}

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await auth();
  if (!session?.user) redirect("/login");

  const userRoles = session.user.roles ?? [];
  const visibleNav = NAV.filter(
    (item) => !item.roles || item.roles.some((r) => userRoles.includes(r)),
  );

  return (
    <div className="min-h-screen flex bg-background text-foreground">
      <aside className="w-60 border-r bg-card">
        <div className="px-4 py-4 border-b">
          <Link href="/dashboard" className="text-lg font-semibold tracking-tight">
            StaffPick
          </Link>
          {session.user.tenantId ? (
            <p className="text-xs text-muted-foreground mt-1">
              tenant: {session.user.tenantId.slice(0, 8)}…
            </p>
          ) : (
            <p className="text-xs text-muted-foreground mt-1">platform</p>
          )}
        </div>
        <nav className="px-2 py-3 space-y-1">
          {visibleNav.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="block px-3 py-2 rounded-md text-sm hover:bg-accent"
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>
      <div className="flex-1 flex flex-col">
        <header className="border-b px-6 py-3 flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Signed in as <span className="text-foreground">{session.user.email}</span>
            <span className="mx-2">·</span>
            <span className="font-mono">{userRoles.join(", ") || "(no roles)"}</span>
          </div>
          <div className="flex items-center gap-2">
            <Link
              href="/dashboard/account"
              className="text-sm rounded-md border px-3 py-1 hover:bg-accent"
            >
              Account
            </Link>
            <form action={handleSignOut}>
              <button
                type="submit"
                className="text-sm rounded-md border px-3 py-1 hover:bg-accent"
              >
                Sign out
              </button>
            </form>
          </div>
        </header>
        <main className="flex-1 p-6 overflow-auto">{children}</main>
      </div>
    </div>
  );
}
