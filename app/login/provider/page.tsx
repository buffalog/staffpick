import { redirect } from "next/navigation";
import { auth } from "@/auth";
import { ProviderLoginForm } from "./provider-login-form";

type SearchParams = Promise<{ error?: string }>;

export default async function ProviderLoginPage({
  searchParams,
}: {
  searchParams: SearchParams;
}) {
  const session = await auth();
  if (session?.user) redirect("/dashboard");
  const { error } = await searchParams;

  return (
    <div className="min-h-screen flex items-center justify-center bg-background px-4">
      <div className="w-full max-w-md space-y-8">
        <header className="text-center">
          <h1 className="text-3xl font-semibold tracking-tight">StaffPick</h1>
          <p className="text-sm text-muted-foreground mt-2">
            Clinician sign-in. We&apos;ll email you a 6-digit code.
          </p>
        </header>
        {error ? (
          <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            Sign-in failed. The code may have expired — request a new one.
          </div>
        ) : null}
        <ProviderLoginForm />
        <p className="text-center text-xs text-muted-foreground">
          Staff or admin? <a href="/login" className="underline">Sign in here</a>.
        </p>
      </div>
    </div>
  );
}
