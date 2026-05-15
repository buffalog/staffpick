"use server";

import { redirect } from "next/navigation";
import { AuthError } from "next-auth";
import { signIn } from "@/auth";

/**
 * Email + password bootstrap sign-in. This is the recovery / first-login
 * path — the day-to-day factor is a passkey (see the login form).
 *
 * NextAuth's `signIn()` throws AuthError on failure and re-throws
 * NEXT_REDIRECT on success; catch the former, let the latter propagate.
 */
export async function signInWithPassword(
  callbackUrl: string,
  formData: FormData,
): Promise<void> {
  const email = String(formData.get("email") ?? "");
  const password = String(formData.get("password") ?? "");
  const safeCallback = callbackUrl.startsWith("/") ? callbackUrl : "/dashboard";
  try {
    await signIn("credentials", {
      email,
      password,
      redirectTo: safeCallback,
    });
  } catch (error) {
    if (error instanceof AuthError) {
      redirect(`/login?error=${encodeURIComponent(error.type)}`);
    }
    throw error;
  }
}
