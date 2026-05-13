import type { NextAuthConfig } from "next-auth";
import type { UserRoleType } from "@/lib/enums";

/**
 * Edge-safe shared auth config. NO Prisma, NO provider implementations
 * (their callbacks may pull node-only deps). Used directly by middleware.ts,
 * and merged with providers + adapter in auth.ts for full server config.
 */
export const authConfig = {
  session: { strategy: "jwt" },
  pages: {
    signIn: "/login",
    verifyRequest: "/login/verify",
  },
  providers: [], // Filled in auth.ts
  callbacks: {
    async authorized({ auth, request }) {
      const isLoggedIn = !!auth;
      const pathname = request.nextUrl.pathname;
      const isPublic =
        pathname.startsWith("/login") ||
        pathname.startsWith("/api/auth") ||
        pathname.startsWith("/api/intake") ||
        pathname === "/";
      if (isPublic) return true;
      return isLoggedIn;
    },
    async jwt({ token, user }) {
      // Edge-safe pieces only — DB enrichment happens in auth.ts's override.
      if (user && user.id) {
        token.id = user.id;
        token.tenantId =
          (user as { tenantId?: string | null }).tenantId ?? null;
        token.roles =
          (user as { roles?: UserRoleType[] }).roles ?? [];
      }
      return token;
    },
    async session({ session, token }) {
      if (token) {
        session.user.id = token.id;
        session.user.tenantId = token.tenantId;
        session.user.roles = token.roles;
      }
      return session;
    },
  },
} satisfies NextAuthConfig;
