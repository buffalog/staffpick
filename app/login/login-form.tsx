"use client";

import { useState, useTransition } from "react";
import { signIn } from "next-auth/webauthn";
import { signInWithPassword } from "./actions";

type Props = {
  callbackUrl: string;
};

export function LoginForm({ callbackUrl }: Props) {
  const [passkeyPending, startPasskey] = useTransition();
  const [passwordPending, startPassword] = useTransition();
  const [passkeyError, setPasskeyError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  function handlePasskey() {
    setPasskeyError(null);
    startPasskey(async () => {
      try {
        await signIn("passkey", { callbackUrl });
      } catch {
        setPasskeyError(
          "Passkey sign-in failed or was cancelled. Use your password below, then enroll a passkey from your account.",
        );
      }
    });
  }

  function handlePassword(formData: FormData) {
    startPassword(async () => {
      await signInWithPassword(callbackUrl, formData);
    });
  }

  return (
    <div className="space-y-6">
      {/* ── Passkey: the primary path ──────────────────────────────────────── */}
      <div className="space-y-2">
        <button
          type="button"
          onClick={handlePasskey}
          disabled={passkeyPending}
          className="w-full rounded-md bg-primary text-primary-foreground py-2.5 text-sm font-medium hover:opacity-90 disabled:opacity-50"
        >
          {passkeyPending ? "Waiting for passkey…" : "Sign in with a passkey"}
        </button>
        {passkeyError && (
          <p className="text-xs text-destructive">{passkeyError}</p>
        )}
        <p className="text-xs text-muted-foreground text-center">
          Face ID, Touch ID, Windows Hello, or a security key.
        </p>
      </div>

      {/* ── Divider ────────────────────────────────────────────────────────── */}
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <span className="w-full border-t" />
        </div>
        <div className="relative flex justify-center text-xs uppercase">
          <span className="bg-background px-2 text-muted-foreground">
            first time, or no passkey yet
          </span>
        </div>
      </div>

      {/* ── Password: bootstrap / recovery ─────────────────────────────────── */}
      {showPassword ? (
        <form action={handlePassword} className="space-y-4">
          <div className="space-y-1">
            <label htmlFor="email" className="text-sm font-medium">
              Email
            </label>
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
            <label htmlFor="password" className="text-sm font-medium">
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>
          <button
            type="submit"
            disabled={passwordPending}
            className="w-full rounded-md border bg-background py-2 text-sm font-medium hover:bg-accent disabled:opacity-50"
          >
            {passwordPending ? "Signing in…" : "Sign in with password"}
          </button>
          <p className="text-xs text-muted-foreground">
            After signing in, enroll a passkey from <span className="font-medium">Account</span> so
            you can skip the password next time.
          </p>
        </form>
      ) : (
        <button
          type="button"
          onClick={() => setShowPassword(true)}
          className="w-full rounded-md border bg-background py-2 text-sm font-medium hover:bg-accent"
        >
          Sign in with email & password
        </button>
      )}
    </div>
  );
}
