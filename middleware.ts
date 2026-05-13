import NextAuth from "next-auth";
import { authConfig } from "@/auth.config";

// Edge-safe middleware: uses ONLY authConfig (no Prisma, no providers).
// `authorized` callback in authConfig handles redirect decisions.
export const { auth: middleware } = NextAuth(authConfig);
export default middleware((req) => {
  // authorized() already gated public/private. If we reach here for an
  // unauthenticated request to a private path, redirect to /login.
  if (!req.auth && !isPublicPath(req.nextUrl.pathname)) {
    const url = new URL("/login", req.url);
    url.searchParams.set("callbackUrl", req.nextUrl.pathname);
    return Response.redirect(url);
  }
});

function isPublicPath(pathname: string): boolean {
  return (
    pathname.startsWith("/login") ||
    pathname.startsWith("/api/auth") ||
    pathname.startsWith("/api/intake") ||
    pathname.startsWith("/intake") ||
    pathname === "/"
  );
}

export const config = {
  matcher: [
    "/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)",
  ],
};
