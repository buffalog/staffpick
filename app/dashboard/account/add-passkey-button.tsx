"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { signIn } from "next-auth/webauthn";

export function AddPasskeyButton() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  function handleAdd() {
    setError(null);
    startTransition(async () => {
      try {
        // `action: "register"` enrolls a new passkey for the signed-in user.
        await signIn("passkey", { action: "register" });
        router.refresh();
      } catch {
        setError("Passkey enrollment failed or was cancelled. Try again.");
      }
    });
  }

  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={handleAdd}
        disabled={pending}
        className="rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
      >
        {pending ? "Follow your device prompt…" : "Add a passkey"}
      </button>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
