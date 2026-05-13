import { test, expect } from "@playwright/test";
import { TOTP } from "otpauth";
import { E2E_TOTP_SECRET } from "../../prisma/seed";

const E2E_EMAIL = "e2e@staffpick.local";
const E2E_PASSWORD = "LocalDev_Pa55word!";

function currentTotpCode(): string {
  const totp = new TOTP({
    issuer: "StaffPick",
    label: "StaffPick",
    algorithm: "SHA1",
    digits: 6,
    period: 30,
    secret: E2E_TOTP_SECRET,
  });
  return totp.generate();
}

test("user logs in → lands on dashboard → logs out", async ({ page }) => {
  await page.goto("/login");
  await expect(page.locator("h1", { hasText: "StaffPick" })).toBeVisible();

  await page.fill('input[name="email"]', E2E_EMAIL);
  await page.fill('input[name="password"]', E2E_PASSWORD);
  await page.fill('input[name="totpCode"]', currentTotpCode());

  await page.click('button[type="submit"]:has-text("Sign in")');

  await page.waitForURL(/\/dashboard$/, { timeout: 15_000 });
  await expect(page.locator("h1", { hasText: "Dashboard" })).toBeVisible();
  await expect(page.locator("text=Signed in as")).toContainText(E2E_EMAIL);

  await page.click('button[type="submit"]:has-text("Sign out")');
  await page.waitForURL(/\/login$/, { timeout: 10_000 });
  await expect(page.locator("h1", { hasText: "StaffPick" })).toBeVisible();
});
