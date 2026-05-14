"use client";

import { useState, useTransition } from "react";
import {
  requestProviderLoginOtp,
  completeProviderLogin,
} from "./actions";

export function ProviderLoginForm() {
  const [step, setStep] = useState<1 | 2>(1);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  async function handleRequestCode(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    startTransition(async () => {
      const result = await requestProviderLoginOtp(email, password);
      if (result.ok) {
        setStep(2);
      } else {
        setError(result.error ?? "Sign-in failed.");
      }
    });
  }

  if (step === 1) {
    return (
      <form onSubmit={handleRequestCode} className="space-y-4">
        {error && (
          <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {error}
          </div>
        )}
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
            value={email}
            onChange={(e) => setEmail(e.target.value)}
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
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
        </div>
        <button
          type="submit"
          disabled={pending}
          className="w-full rounded-md bg-primary text-primary-foreground py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
        >
          {pending ? "Sending code…" : "Continue"}
        </button>
      </form>
    );
  }

  // Step 2: enter the emailed code, then submit to NextAuth via server action
  return (
    <form action={completeProviderLogin} className="space-y-4">
      <input type="hidden" name="email" value={email} />
      <input type="hidden" name="password" value={password} />
      <div className="rounded-md border bg-card px-3 py-2 text-sm">
        Code sent to <span className="font-medium">{email}</span>. It expires in 10 minutes.
      </div>
      <div className="space-y-1">
        <label htmlFor="emailOtpCode" className="text-sm font-medium">
          6-digit code
        </label>
        <input
          id="emailOtpCode"
          name="emailOtpCode"
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          autoComplete="one-time-code"
          maxLength={6}
          required
          autoFocus
          className="w-full rounded-md border bg-background px-3 py-2 text-sm tracking-widest font-mono text-center"
        />
      </div>
      <button
        type="submit"
        className="w-full rounded-md bg-primary text-primary-foreground py-2 text-sm font-medium hover:opacity-90"
      >
        Sign in
      </button>
      <button
        type="button"
        onClick={() => setStep(1)}
        className="w-full text-xs text-muted-foreground underline"
      >
        Use a different email
      </button>
    </form>
  );
}
