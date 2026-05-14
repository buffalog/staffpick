// One-off: create the `staffpick` database on a fresh SQL Server.
// Uses the JS `mssql` driver (same one Prisma's adapter uses) so it isn't
// affected by Go-sqlcmd's strict x509 parsing.
//
//   DATABASE_HOST=host DATABASE_PORT=port SA_PASSWORD=pw node scripts/railway-db-create.mjs
import sql from "mssql";

const host = process.env.DATABASE_HOST;
const port = Number(process.env.DATABASE_PORT);
const password = process.env.SA_PASSWORD;
if (!host || !port || !password) {
  console.error("Set DATABASE_HOST, DATABASE_PORT, SA_PASSWORD");
  process.exit(1);
}

const pool = await sql.connect({
  server: host,
  port,
  user: "sa",
  password,
  database: "master",
  options: { encrypt: true, trustServerCertificate: true },
  connectionTimeout: 30000,
});

const version = await pool.request().query("SELECT @@VERSION AS v");
console.log("connected:", version.recordset[0].v.split("\n")[0].trim());

const existing = await pool
  .request()
  .query("SELECT name FROM sys.databases WHERE name = 'staffpick'");
if (existing.recordset.length > 0) {
  console.log("database 'staffpick' already exists — nothing to do");
} else {
  await pool.request().query("CREATE DATABASE staffpick");
  console.log("database 'staffpick' created");
}

await pool.close();
