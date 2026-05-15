import { test, expect, type Page } from "@playwright/test";

// E2E signs in via the email+password bootstrap path (passkeys need a CDP
// virtual authenticator — out of scope here; password is a real login route).
const E2E_EMAIL = "e2e@staffpick.local";
const E2E_PASSWORD = "LocalDev_Pa55word!";

// Stamp keeps every test run's source/subject unique.
const stamp = Date.now().toString(36);
const SOURCE_NAME = `E2E Source ${stamp}`;
const SUBJECT_FIRST = "E2E";
const SUBJECT_LAST = `Patient-${stamp}`;

async function loginAsTenantStaff(page: Page) {
  await page.goto("/login");
  await page.click('button:has-text("Sign in with email & password")');
  await page.fill('input[name="email"]', E2E_EMAIL);
  await page.fill('input[name="password"]', E2E_PASSWORD);
  await page.click('button[type="submit"]:has-text("Sign in with password")');
  await page.waitForURL(/\/dashboard$/, { timeout: 15_000 });
}

test.describe.serial("intake flow — webform → inbox → case advance", () => {
  test("public webform submits a referral", async ({ page }) => {
    await page.goto("/intake");

    await page.fill('input[name="source_name"]', SOURCE_NAME);
    await page.fill('input[name="source_contact_name"]', "Jane Doe");
    await page.fill('input[name="source_email"]', `e2e-${stamp}@source.local`);
    await page.fill('input[name="source_phone"]', "561-555-9000");

    await page.fill('input[name="subject_given"]', SUBJECT_FIRST);
    await page.fill('input[name="subject_family"]', SUBJECT_LAST);
    await page.fill('input[name="subject_dob"]', "1955-04-12");

    // Diagnosis combobox — type, click the result
    await page.fill('input[placeholder*="dysphagia"]', "Z47.1");
    await page.getByRole("button", { name: /Z47\.1/ }).click();
    await expect(
      page.getByText("Aftercare following joint replacement surgery"),
    ).toBeVisible();

    await page.selectOption('select[name="requested_service"]', "PT");
    await page.fill(
      'input[name="schedule_preference"]',
      "weekday afternoons, 3x/week",
    );

    // Turnstile test key auto-passes; wait for the hidden response input.
    await page.waitForFunction(
      () => {
        const inputs = document.querySelectorAll<HTMLInputElement>(
          'input[name="cf-turnstile-response"]',
        );
        return Array.from(inputs).some((i) => i.value && i.value.length > 0);
      },
      { timeout: 20_000 },
    );

    await page.click('button[type="submit"]:has-text("Submit referral")');
    await page.waitForURL(/\/intake\/thanks$/, { timeout: 15_000 });
    await expect(page.getByText("Referral received")).toBeVisible();
  });

  test("tenant staff sees the request in the inbox and opens case detail", async ({
    page,
  }) => {
    await loginAsTenantStaff(page);
    await page.goto("/dashboard/inbox");

    const row = page.locator("tr", {
      hasText: `${SUBJECT_LAST}, ${SUBJECT_FIRST}`,
    });
    await expect(row).toBeVisible({ timeout: 10_000 });
    await expect(row.getByText(SOURCE_NAME)).toBeVisible();

    // Patient name is a link to case detail
    await row.getByRole("link", { name: /^Patient-/ }).click();
    await page.waitForURL(/\/dashboard\/cases\/[^/]+$/, { timeout: 10_000 });

    // Phase badge should read Phase 2 (gate enabled by default in seed)
    await expect(page.getByText("2 · Intake Review")).toBeVisible();
    // Action button to advance
    await expect(
      page.getByRole("button", { name: /Advance to 3 · Matching Kickoff/ }),
    ).toBeVisible();
  });

  test("advancing to Phase 3 updates the badge", async ({ page }) => {
    await loginAsTenantStaff(page);
    // Use the SUBJECT_LAST to find the case via the inbox is gone after advance.
    // Phase 2 + Phase 1 list filters to Active phase1/phase2 — once we advance,
    // it leaves the inbox. So we navigate via the inbox before advancing.
    await page.goto("/dashboard/inbox");
    const row = page.locator("tr", {
      hasText: `${SUBJECT_LAST}, ${SUBJECT_FIRST}`,
    });
    await row.getByRole("link", { name: /^Patient-/ }).click();
    await page.waitForURL(/\/dashboard\/cases\/[^/]+$/, { timeout: 10_000 });

    await page
      .getByRole("button", { name: /Advance to 3 · Matching Kickoff/ })
      .click();

    // Badge should now read Phase 3
    await expect(page.getByText("3 · Matching Kickoff")).toBeVisible({
      timeout: 10_000,
    });

    // Inbox should no longer contain this request
    await page.goto("/dashboard/inbox");
    await expect(
      page.locator("tr", { hasText: `${SUBJECT_LAST}, ${SUBJECT_FIRST}` }),
    ).toHaveCount(0);
  });
});
