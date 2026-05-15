import { test, expect } from "@playwright/test";

/**
 * Round-trip E2E: one referral from public webform intake all the way to a
 * closed case — all 14 lifecycle phases. Single serial test; the case
 * threads through every step.
 *
 * Signs in via the email+password bootstrap path (passkeys need a CDP
 * virtual authenticator — out of scope here).
 */

const STAFF_EMAIL = "e2e@staffpick.local";
const STAFF_PASSWORD = "LocalDev_Pa55word!";

const stamp = Date.now().toString(36);
const SUBJECT_FIRST = "Roundtrip";
const SUBJECT_LAST = `Case-${stamp}`;
const SOURCE_NAME = `Roundtrip Source ${stamp}`;

test("referral travels intake → closed across all 14 phases", async ({ page }) => {
  test.setTimeout(120_000);

  // ── Phase 1: public webform intake ─────────────────────────────────────────
  await page.goto("/intake");
  await page.fill('input[name="source_name"]', SOURCE_NAME);
  await page.fill('input[name="source_contact_name"]', "Pat Referrer");
  await page.fill('input[name="source_email"]', `rt-${stamp}@source.local`);
  await page.fill('input[name="source_phone"]', "561-555-7000");
  await page.fill('input[name="subject_given"]', SUBJECT_FIRST);
  await page.fill('input[name="subject_family"]', SUBJECT_LAST);
  await page.fill('input[name="subject_dob"]', "1948-09-30");

  await page.fill('input[placeholder*="dysphagia"]', "Z47.1");
  await page.getByRole("button", { name: /Z47\.1/ }).click();
  await expect(
    page.getByText("Aftercare following joint replacement surgery"),
  ).toBeVisible();

  await page.selectOption('select[name="requested_service"]', "PT");
  await page.fill('input[name="schedule_preference"]', "weekday mornings, 3x/week");

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

  // ── Sign in as Tenant Staff ────────────────────────────────────────────────
  await page.goto("/login");
  await page.click('button:has-text("Sign in with email & password")');
  await page.fill('input[name="email"]', STAFF_EMAIL);
  await page.fill('input[name="password"]', STAFF_PASSWORD);
  await page.click('button[type="submit"]:has-text("Sign in with password")');
  await page.waitForURL(/\/dashboard$/, { timeout: 15_000 });

  // ── Phase 2: inbox → accept ────────────────────────────────────────────────
  await page.goto("/dashboard/inbox");
  const inboxRow = page.locator("tr", {
    hasText: `${SUBJECT_LAST}, ${SUBJECT_FIRST}`,
  });
  await expect(inboxRow).toBeVisible({ timeout: 10_000 });
  await inboxRow.getByRole("button", { name: "Accept" }).click();
  // After accept the row leaves the inbox (now Phase 3).
  await expect(inboxRow).toHaveCount(0, { timeout: 10_000 });

  // ── Open the case from the cases list, capture its URL ─────────────────────
  await page.goto("/dashboard/cases");
  const caseRow = page.locator("tr", {
    hasText: `${SUBJECT_LAST}, ${SUBJECT_FIRST}`,
  });
  await caseRow.getByRole("link", { name: /^Case-/ }).click();
  await page.waitForURL(/\/dashboard\/cases\/[^/]+$/, { timeout: 10_000 });
  const caseUrl = page.url();
  await expect(page.getByText("3 · Matching Kickoff")).toBeVisible();

  // ── Phase 3 → 5: find providers, approve a match ───────────────────────────
  await page.getByRole("link", { name: "Find providers" }).click();
  await page.waitForURL(/\/match$/, { timeout: 10_000 });
  await page.getByRole("button", { name: "Approve match" }).first().click();
  // approveMatch walks Phase 3→4→5 and redirects back to the case.
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("5 · Match Notification")).toBeVisible();

  // ── Phase 6: post a message in the collaboration thread ────────────────────
  await page.fill('textarea[name="body"]', `Kickoff note for ${SUBJECT_LAST}`);
  await page.getByRole("button", { name: "Send" }).click();
  await expect(page.getByText("6 · Collaboration")).toBeVisible({ timeout: 10_000 });

  // ── Phase 7: initial assessment ────────────────────────────────────────────
  await page.getByRole("button", { name: "Start initial assessment" }).click();
  await page.waitForURL(/\/assess$/, { timeout: 10_000 });
  await page.fill('textarea[name="notes"]', "Initial eval — baseline captured.");
  await page.getByRole("button", { name: /Submit assessment/ }).click();
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("8 · Plan Documentation")).toBeVisible();

  // ── Phase 8 → 9: document the resolution plan ──────────────────────────────
  await page.getByRole("link", { name: "Document resolution plan" }).click();
  await page.waitForURL(/\/plan\/new$/, { timeout: 10_000 });
  await page.fill('input[name="frequency"]', "3x/week for 6 weeks");
  await page.fill(
    'textarea[name="services_summary"]',
    "PT-VISIT — gait + balance training",
  );
  await page.getByRole("button", { name: /Save plan/ }).click();
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("9 · Service Delivery")).toBeVisible();

  // ── Phase 9: record a service visit ────────────────────────────────────────
  await page.getByRole("link", { name: "Record visit" }).click();
  await page.waitForURL(/\/visits\/new$/, { timeout: 10_000 });
  await page.fill('input[name="duration_minutes"]', "45");
  // Option value is the service_code itself.
  await page.selectOption('select[name="service_code"]', "PT-VISIT");
  await page.fill('input[name="subject_signature_value"]', `${SUBJECT_FIRST} ${SUBJECT_LAST}`);
  await page.getByRole("button", { name: "Record visit" }).click();
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("9 · Service Delivery")).toBeVisible();

  // ── Phase 9 → 10: subsequent assessment ────────────────────────────────────
  await page.getByRole("link", { name: "Subsequent assessment" }).click();
  await page.waitForURL(/\/assess\?type=Subsequent$/, { timeout: 10_000 });
  await page.fill('textarea[name="notes"]', "Mid-plan — progressing.");
  await page.getByRole("button", { name: /Submit subsequent assessment/ }).click();
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("10 · Subsequent Assessment")).toBeVisible();

  // ── Phase 10 → 11: final assessment (auto-generates the invoice) ───────────
  await page.getByRole("link", { name: "Final assessment" }).click();
  await page.waitForURL(/\/assess\?type=Final$/, { timeout: 10_000 });
  await page.fill('textarea[name="notes"]', "Goals met — discharge.");
  await page.getByRole("button", { name: /Submit final assessment/ }).click();
  await page.waitForURL(caseUrl, { timeout: 15_000 });
  await expect(page.getByText("11 · Plan Completion")).toBeVisible();

  // ── Phase 11 → 12: send the invoice to the source ──────────────────────────
  const sendInvoice = page.getByRole("button", { name: "Send to source" });
  await expect(sendInvoice).toBeVisible({ timeout: 10_000 });
  await sendInvoice.click();
  await expect(page.getByText("12 · Invoice & Payment")).toBeVisible({ timeout: 10_000 });

  // ── Phase 12 → 13: source opens the magic-link invoice and marks it paid ───
  const sourceLink = await page
    .getByRole("link", { name: "Source view" })
    .getAttribute("href");
  expect(sourceLink).toBeTruthy();
  await page.goto(sourceLink!);
  await page.getByRole("button", { name: "Mark paid" }).click();
  await expect(page.getByText(/payment recorded/)).toBeVisible({ timeout: 10_000 });

  // ── Phase 13 → 14: export payroll CSV, which closes the case ───────────────
  await page.goto(caseUrl);
  await expect(page.getByText("13 · Provider Payment")).toBeVisible();
  const [download] = await Promise.all([
    page.waitForEvent("download"),
    page.getByRole("link", { name: "Payroll CSV" }).click(),
  ]);
  expect(download.suggestedFilename()).toMatch(/payroll-INV-.*\.csv/);

  // ── Phase 14: case is closed ───────────────────────────────────────────────
  await page.goto(caseUrl);
  await expect(page.getByText("14 · Closed")).toBeVisible({ timeout: 10_000 });
  await expect(page.locator("text=Closed").first()).toBeVisible();
});
