import { redirect } from "next/navigation";
import { auth } from "@/auth";
import { LoginForm } from "./login-form";

type SearchParams = Promise<{ callbackUrl?: string; error?: string }>;

export default async function LoginPage({
  searchParams,
}: {
  searchParams: SearchParams;
}) {
  const session = await auth();
  if (session?.user) redirect("/dashboard");

  const { callbackUrl, error } = await searchParams;
  const safeCallback =
    callbackUrl && callbackUrl.startsWith("/") ? callbackUrl : "/dashboard";

  return (
    <div className="min-h-screen flex items-center justify-center bg-background px-4">
      <div className="w-full max-w-md space-y-8">
        <header className="text-center">
          <h1 className="text-3xl font-semibold tracking-tight">StaffPick</h1>
          <p className="text-sm text-muted-foreground mt-2">
            Sign in to your workspace.
          </p>
        </header>

        {error ? (
          <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            Sign-in failed. Check your email and password, or try your passkey.
          </div>
        ) : null}

        <LoginForm callbackUrl={safeCallback} />
      </div>
    </div>
  );
}
