import type { NextConfig } from "next";
import path from "node:path";

const nextConfig: NextConfig = {
  // Standalone output keeps the Docker runner image lean — Next traces only
  // the files the server actually needs. See Dockerfile.
  output: "standalone",
  // Pin the file-tracing root to THIS project. Without it, Next walks up and
  // finds ~/package.json, treats the home dir as a monorepo root, and nests
  // the standalone output (server.js ends up under standalone/projects/...).
  outputFileTracingRoot: path.join(__dirname),
};

export default nextConfig;
