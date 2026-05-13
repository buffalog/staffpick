import { redirect } from "next/navigation";
import { AuthError } from "next-auth";
import { auth, signIn } from "@/auth";

type SearchParams = Promise<{ callbackUrl?: string; error?: string }>;

export default async function LoginPage({
  searchParams,
}: {
  searchParams: SearchParams;
}) {
  const session = await auth();
  if (session?.user) redirect("/dashboard");

  const { callbackUrl, error } = await searchParams;
  const safeCallback = callbackUrl && callbackUrl.startsWith("/") ? callbackUrl : "/dashboard";

  async function signInCredentials(formData: FormData) {
    "use server";
    const email = String(formData.get("email") ?? "");
    const password = String(formData.get("password") ?? "");
    const totpCode = String(formData.get("totpCode") ?? "");
    try {
      await signIn("credentials", {
        email,
        password,
        totpCode,
        redirectTo: safeCallback,
      });
    } catch (error) {
      // NextAuth `signIn()` throws AuthError on auth failure and re-throws
      // NEXT_REDIRECT on success. Catch AuthError → redirect with ?error;
      // let NEXT_REDIRECT propagate.
      if (error instanceof AuthError) {
        redirect(`/login?error=${encodeURIComponent(error.type)}`);
      }
      throw error;
    }
  }

  async function signInMagicLink(formData: FormData) {
    "use server";
    const email = String(formData.get("email") ?? "");
    try {
      await signIn("resend", { email, redirectTo: safeCallback });
    } catch (error) {
      if (error instanceof AuthError) {
        redirect(`/login?error=${encodeURIComponent(error.type)}`);
      }
      throw error;
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-background px-4">
      <div className="w-full max-w-md space-y-8">
        <header className="text-center">
          <h1 className="text-3xl font-semibold tracking-tight">StaffPick</h1>
          <p className="text-sm text-muted-foreground mt-2">
            Sign in to your tenant workspace.
          </p>
        </header>

        {error ? (
          <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            Sign-in failed. Check email, password, and TOTP (if enabled).
          </div>
        ) : null}

        <form action={signInCredentials} className="space-y-4">
          <div className="space-y-1">
            <label htmlFor="email" className="text-sm font-medium">Email</label>
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <div className="space-y-1">
            <label htmlFor="password" className="text-sm font-medium">Password</label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <div className="space-y-1">
            <label htmlFor="totpCode" className="text-sm font-medium">
              TOTP code <span className="text-muted-foreground">(staff & admins)</span>
            </label>
            <input
              id="totpCode"
              name="totpCode"
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              autoComplete="one-time-code"
              maxLength={6}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm tracking-widest font-mono"
            />
          </div>
          <button
            type="submit"
            className="w-full rounded-md bg-primary text-primary-foreground py-2 text-sm font-medium hover:opacity-90"
          >
            Sign in
          </button>
        </form>

        <div className="relative">
          <div className="absolute inset-0 flex items-center">
            <span className="w-full border-t" />
          </div>
          <div className="relative flex justify-center text-xs uppercase">
            <span className="bg-background px-2 text-muted-foreground">or</span>
          </div>
        </div>

        <form action={signInMagicLink} className="space-y-3">
          <input
            name="email"
            type="email"
            placeholder="you@tenant.local"
            autoComplete="email"
            required
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
          <button
            type="submit"
            className="w-full rounded-md border bg-background py-2 text-sm font-medium hover:bg-accent"
          >
            Email me a magic link
          </button>
        </form>
      </div>
    </div>
  );
}
