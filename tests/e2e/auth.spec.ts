import { test, expect } from "@playwright/test";

// E2E signs in via the email+password bootstrap path. The passkey flow needs
// a WebAuthn virtual authenticator (CDP) — out of scope for these tests; the
// password path is a real, supported login route and keeps the suite simple.
const E2E_EMAIL = "e2e@staffpick.local";
const E2E_PASSWORD = "LocalDev_Pa55word!";

test("user logs in → lands on dashboard → logs out", async ({ page }) => {
  await page.goto("/login");
  await expect(page.locator("h1", { hasText: "StaffPick" })).toBeVisible();

  // Reveal the password form (passkey is the primary, password is behind a toggle)
  await page.click('button:has-text("Sign in with email & password")');

  await page.fill('input[name="email"]', E2E_EMAIL);
  await page.fill('input[name="password"]', E2E_PASSWORD);
  await page.click('button[type="submit"]:has-text("Sign in with password")');

  await page.waitForURL(/\/dashboard$/, { timeout: 15_000 });
  await expect(page.locator("h1", { hasText: "Dashboard" })).toBeVisible();
  await expect(page.locator("text=Signed in as")).toContainText(E2E_EMAIL);

  await page.click('button[type="submit"]:has-text("Sign out")');
  await page.waitForURL(/\/login$/, { timeout: 10_000 });
  await expect(page.locator("h1", { hasText: "StaffPick" })).toBeVisible();
});
