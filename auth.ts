import NextAuth, { type DefaultSession } from "next-auth";
import Credentials from "next-auth/providers/credentials";
import Resend from "next-auth/providers/resend";
import Passkey from "next-auth/providers/passkey";
import type { JWT } from "next-auth/jwt";
import { PrismaAdapter } from "@auth/prisma-adapter";
import bcrypt from "bcryptjs";
import { z } from "zod";
import { authConfig } from "@/auth.config";
import { prismaBase } from "@/lib/prisma";
import type { UserRoleType } from "@/lib/enums";
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
});

export const { handlers, signIn, signOut, auth } = NextAuth({
  ...authConfig,
  adapter: PrismaAdapter(prismaBase),
  experimental: { enableWebAuthn: true },
  providers: [
    // Passkey (WebAuthn) — the day-to-day factor for every role. Phishing-
    // resistant, NIST AAL2/3. Enrolled after a first password login; see the
    // /login page and the dashboard "Add a passkey" surface.
    Passkey,
    // Email + password — bootstrap (first login, before a passkey exists)
    // and account recovery only. Single-factor by design; hardening the
    // bootstrap is tracked in docs/mvp-gaps.md.
    Credentials({
      name: "Email & password",
      credentials: {
        email: { label: "Email", type: "email" },
        password: { label: "Password", type: "password" },
      },
      async authorize(raw) {
        const parsed = credentialsSchema.safeParse(raw);
        if (!parsed.success) return null;
        const { email, password } = parsed.data;

        const user = await prismaBase.user.findUnique({
          where: { email },
          include: { roles: true },
        });
        if (!user || !user.active || !user.password_hash) return null;

        const passwordOk = await bcrypt.compare(password, user.password_hash);
        if (!passwordOk) return null;

        const roles = user.roles.map((r) => r.role as UserRoleType);

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
          metadata: { method: "credentials-bootstrap" },
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
      // Server-only: re-enrich from DB. Also covers passkey sign-in, where
      // the WebAuthn provider returns the adapter User without our extras.
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
