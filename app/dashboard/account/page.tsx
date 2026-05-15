import { redirect } from "next/navigation";
import { auth } from "@/auth";
import { prismaBase } from "@/lib/prisma";
import { AddPasskeyButton } from "./add-passkey-button";

export const dynamic = "force-dynamic";

export default async function AccountPage() {
  const session = await auth();
  if (!session?.user?.id) redirect("/login");

  // Authenticator isn't tenant-scoped — query the base client by userId.
  const passkeys = await prismaBase.authenticator.findMany({
    where: { userId: session.user.id },
  });

  return (
    <div className="space-y-6 max-w-2xl">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Account</h1>
        <p className="text-sm text-muted-foreground">
          {session.user.email} · {(session.user.roles ?? []).join(", ") || "no roles"}
        </p>
      </header>

      <section className="rounded-md border bg-card p-4 space-y-3">
        <div>
          <h2 className="text-sm font-medium">Passkeys</h2>
          <p className="text-sm text-muted-foreground mt-1">
            A passkey is the day-to-day sign-in factor — Face ID, Touch ID,
            Windows Hello, or a hardware key. Phishing-resistant, no codes to
            type. Enroll one per device you sign in from.
          </p>
        </div>

        {passkeys.length === 0 ? (
          <div className="rounded-md bg-amber-500/10 border border-amber-500/30 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
            No passkeys enrolled yet. You&apos;re signing in with your password
            (the bootstrap factor) — add a passkey to skip it next time.
          </div>
        ) : (
          <ul className="space-y-1 text-sm">
            {passkeys.map((pk, i) => (
              <li
                key={pk.id}
                className="flex items-center justify-between rounded-md border bg-background px-3 py-2"
              >
                <span>
                  Passkey {i + 1}
                  <span className="text-muted-foreground">
                    {" "}· {pk.credentialDeviceType}
                    {pk.credentialBackedUp ? " · synced" : " · device-bound"}
                  </span>
                </span>
                <span className="font-mono text-xs text-muted-foreground">
                  {pk.credentialID.slice(0, 12)}…
                </span>
              </li>
            ))}
          </ul>
        )}

        <AddPasskeyButton />
      </section>

      <section className="rounded-md border bg-card p-4">
        <h2 className="text-sm font-medium">Password</h2>
        <p className="text-sm text-muted-foreground mt-1">
          Your password is the bootstrap and recovery factor. It still works at
          sign-in if you don&apos;t have a passkey on the device you&apos;re using.
        </p>
      </section>
    </div>
  );
}
