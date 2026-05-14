import bcrypt from "bcryptjs";
import { prismaBase } from "@/lib/prisma";

const OTP_TTL_MS = 10 * 60 * 1000; // 10 minutes
const OTP_LENGTH = 6;

function identifier(email: string): string {
  return `provider-otp:${email.toLowerCase()}`;
}

function generateCode(): string {
  // Cryptographically random 6-digit code
  const buf = new Uint32Array(1);
  crypto.getRandomValues(buf);
  const n = buf[0] % 10 ** OTP_LENGTH;
  return String(n).padStart(OTP_LENGTH, "0");
}

/**
 * Create a fresh OTP for `email`, store its bcrypt hash with a TTL, and
 * return the plaintext code so the caller can deliver it via email.
 *
 * Invalidates any pending tokens for the same identifier so a Provider
 * can request a new code without stale codes also being valid.
 */
export async function mintProviderOtp(email: string): Promise<string> {
  await prismaBase.verificationToken.deleteMany({
    where: { identifier: identifier(email) },
  });
  const code = generateCode();
  const hash = await bcrypt.hash(code, 10);
  await prismaBase.verificationToken.create({
    data: {
      identifier: identifier(email),
      token: hash,
      expires: new Date(Date.now() + OTP_TTL_MS),
    },
  });
  return code;
}

/**
 * Returns true if `code` matches a non-expired stored OTP for `email`.
 * On success the token is consumed (deleted) so it can't be replayed.
 */
export async function verifyAndConsumeProviderOtp(
  email: string,
  code: string,
): Promise<boolean> {
  const tokens = await prismaBase.verificationToken.findMany({
    where: { identifier: identifier(email) },
  });
  const now = new Date();
  for (const t of tokens) {
    if (t.expires < now) continue;
    if (await bcrypt.compare(code, t.token)) {
      await prismaBase.verificationToken.deleteMany({
        where: { identifier: identifier(email) },
      });
      return true;
    }
  }
  return false;
}
