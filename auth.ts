import NextAuth, { type DefaultSession } from "next-auth";
import Credentials from "next-auth/providers/credentials";
import Resend from "next-auth/providers/resend";
import type { JWT } from "next-auth/jwt";
import { PrismaAdapter } from "@auth/prisma-adapter";
import bcrypt from "bcryptjs";
import { TOTP } from "otpauth";
import { z } from "zod";
import { authConfig } from "@/auth.config";
import { prismaBase } from "@/lib/prisma";
import { MFA_REQUIRED_ROLES, type UserRoleType } from "@/lib/enums";
import { writeAudit } from "@/lib/audit";

type SessionUserExtras = {
  id: string;
  tenantId: string | null;
  roles: UserRoleType[];
};

declare module "next-auth" {
  interface Session {
    user: SessionUserExtras & DefaultSession["user"];
  }
  interface User {
    id?: string;
    tenantId?: string | null;
    roles?: UserRoleType[];
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    id: string;
    tenantId: string | null;
    roles: UserRoleType[];
  }
}

const credentialsSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
  totpCode: z.string().optional(),
});

function verifyTotp(secret: string, token: string): boolean {
  const totp = new TOTP({
    issuer: "StaffPick",
    label: "StaffPick",
    algorithm: "SHA1",
    digits: 6,
    period: 30,
    secret,
  });
  return totp.validate({ token, window: 1 }) !== null;
}

export const { handlers, signIn, signOut, auth } = NextAuth({
  ...authConfig,
  adapter: PrismaAdapter(prismaBase),
  providers: [
    Credentials({
      name: "Email & password",
      credentials: {
        email: { label: "Email", type: "email" },
        password: { label: "Password", type: "password" },
        totpCode: { label: "TOTP code (if enabled)", type: "text" },
      },
      async authorize(raw) {
        const parsed = credentialsSchema.safeParse(raw);
        if (!parsed.success) return null;
        const { email, password, totpCode } = parsed.data;

        const user = await prismaBase.user.findUnique({
          where: { email },
          include: { roles: true },
        });
        if (!user || !user.active || !user.password_hash) return null;

        const passwordOk = await bcrypt.compare(password, user.password_hash);
        if (!passwordOk) return null;

        const roles = user.roles.map((r) => r.role as UserRoleType);
        const requiresMfa = roles.some((r) => MFA_REQUIRED_ROLES.has(r));

        if (requiresMfa) {
          if (!user.totp_enabled || !user.totp_secret) return null;
          if (!totpCode) return null;
          if (!verifyTotp(user.totp_secret, totpCode)) return null;
        }

        await prismaBase.user.update({
          where: { id: user.id },
          data: { last_login_at: new Date() },
        });
        await writeAudit({
          action: "Login",
          entity_type: "User",
          entity_id: user.id,
          user_id: user.id,
          tenant_id: user.tenant_id,
          metadata: { method: "credentials", mfa: requiresMfa },
        });

        return {
          id: user.id,
          email: user.email,
          name: user.name ?? undefined,
          tenantId: user.tenant_id,
          roles,
        };
      },
    }),
    Resend({
      apiKey: process.env.RESEND_API_KEY,
      from: process.env.RESEND_FROM ?? "StaffPick <noreply@staffpick.local>",
    }),
  ],
  callbacks: {
    ...authConfig.callbacks,
    async jwt({ token, user, trigger }) {
      // Run the shared edge-safe pass first.
      const base = await authConfig.callbacks!.jwt!({
        token,
        user,
        trigger,
      } as Parameters<NonNullable<typeof authConfig.callbacks.jwt>>[0]);
      const next = base as JWT;
      // Server-only: re-enrich from DB if tenantId/roles are stale.
      if (next.id) {
        const u = await prismaBase.user.findUnique({
          where: { id: next.id },
          include: { roles: true },
        });
        if (u) {
          next.tenantId = u.tenant_id;
          next.roles = u.roles.map((r) => r.role as UserRoleType);
        }
      }
      return next;
    },
  },
  events: {
    async signOut(message) {
      if ("token" in message && message.token) {
        await writeAudit({
          action: "Logout",
          entity_type: "User",
          entity_id: message.token.id as string,
          user_id: message.token.id as string,
          tenant_id: (message.token.tenantId as string | null) ?? undefined,
        });
      }
    },
  },
});
