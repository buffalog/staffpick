"use server";

import bcrypt from "bcryptjs";
import { redirect } from "next/navigation";
import { AuthError } from "next-auth";
import { prismaBase } from "@/lib/prisma";
import { mintProviderOtp } from "@/lib/provider-otp";
import { sendEmail } from "@/lib/email";
import { EMAIL_OTP_ROLES, type UserRoleType } from "@/lib/enums";
import { signIn } from "@/auth";

type RequestResult = { ok: boolean; error?: string };

/**
 * Step 1 of the Provider login flow. Verifies email+password, generates an
 * OTP code, emails it to the user. Returns `ok: true` on success.
 *
 * Deliberately uniform error message ("Invalid credentials") for any failure
 * mode that would otherwise leak which step failed (wrong email vs wrong
 * password vs not-a-Provider).
 */
export async function requestProviderLoginOtp(
  email: string,
  password: string,
): Promise<RequestResult> {
  const user = await prismaBase.user.findUnique({
    where: { email: email.toLowerCase() },
    include: { roles: true },
  });
  if (!user || !user.active || !user.password_hash) {
    return { ok: false, error: "Invalid credentials" };
  }
  const isProvider = user.roles.some((r) =>
    EMAIL_OTP_ROLES.has(r.role as UserRoleType),
  );
  if (!isProvider) {
    return { ok: false, error: "This sign-in page is for clinicians only." };
  }
  const passwordOk = await bcrypt.compare(password, user.password_hash);
  if (!passwordOk) {
    return { ok: false, error: "Invalid credentials" };
  }

  const code = await mintProviderOtp(email);

  const result = await sendEmail({
    to: email,
    subject: "Your StaffPick sign-in code",
    text:
      `Your StaffPick sign-in code is: ${code}\n\n` +
      `Enter this 6-digit code on the sign-in page within 10 minutes.\n\n` +
      `If you did not try to sign in, ignore this message — your account remains secure.`,
  });
  if (!result.delivered) {
    return { ok: false, error: "Could not send code. Try again in a moment." };
  }
  return { ok: true };
}

/**
 * Step 2: completes sign-in with email + password + OTP code. On success
 * NextAuth redirects to the dashboard; on failure we send the user back
 * to /login/provider with ?error= so the inline banner renders.
 */
export async function completeProviderLogin(formData: FormData): Promise<void> {
  const email = String(formData.get("email") ?? "");
  const password = String(formData.get("password") ?? "");
  const emailOtpCode = String(formData.get("emailOtpCode") ?? "");
  try {
    await signIn("credentials", {
      email,
      password,
      emailOtpCode,
      redirectTo: "/dashboard",
    });
  } catch (error) {
    if (error instanceof AuthError) {
      redirect(`/login/provider?error=${encodeURIComponent(error.type)}`);
    }
    throw error;
  }
}
