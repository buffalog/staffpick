import { PrismaClient } from "@/prisma/generated/client";
import { PrismaMssql } from "@prisma/adapter-mssql";

declare global {
  var __prismaBase: PrismaClient | undefined;
}

function buildClient(): PrismaClient {
  const url = process.env.DATABASE_URL;
  if (!url) {
    throw new Error(
      "DATABASE_URL is not set. Populate .env.local before instantiating Prisma.",
    );
  }
  const adapter = new PrismaMssql(url);
  return new PrismaClient({ adapter });
}

// Singleton base client. App code should import `prisma` (below) which is the
// fully-extended client (tenant scope + audit). Use `prismaBase` ONLY for
// auth/setup paths that must bypass tenant scoping.
export const prismaBase: PrismaClient =
  globalThis.__prismaBase ?? buildClient();

if (process.env.NODE_ENV !== "production") {
  globalThis.__prismaBase = prismaBase;
}
