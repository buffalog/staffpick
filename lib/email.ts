import { Resend } from "resend";
import pino from "pino";

const log = pino({ name: "email" });

export type EmailMessage = {
  to: string | string[];
  subject: string;
  text: string;
  html?: string;
  from?: string;
};

export type EmailResult = {
  delivered: boolean;
  /** Real Resend message id if sent via API, else null. */
  externalId: string | null;
  /** "resend" when the API was hit, "console" for the dev stub. */
  channel: "resend" | "console";
};

const defaultFrom =
  process.env.RESEND_FROM ?? "StaffPick <noreply@staffpick.local>";

let resend: Resend | null = null;
function getResend(): Resend | null {
  const key = process.env.RESEND_API_KEY;
  if (!key) return null;
  if (!resend) resend = new Resend(key);
  return resend;
}

/**
 * Send a transactional email. Falls back to a structured console log when
 * RESEND_API_KEY is unset so dev can still exercise the full notification
 * flow without an account.
 */
export async function sendEmail(msg: EmailMessage): Promise<EmailResult> {
  const from = msg.from ?? defaultFrom;
  const client = getResend();

  if (!client) {
    log.info(
      { to: msg.to, subject: msg.subject, from, body_preview: msg.text.slice(0, 200) },
      "[email/stub] RESEND_API_KEY not set — logging email instead of sending",
    );
    return { delivered: true, externalId: null, channel: "console" };
  }

  try {
    const result = await client.emails.send({
      from,
      to: msg.to,
      subject: msg.subject,
      text: msg.text,
      html: msg.html,
    });
    if (result.error) {
      log.error({ err: result.error, to: msg.to }, "Resend send failed");
      return { delivered: false, externalId: null, channel: "resend" };
    }
    return {
      delivered: true,
      externalId: result.data?.id ?? null,
      channel: "resend",
    };
  } catch (err) {
    log.error({ err, to: msg.to }, "Resend threw");
    return { delivered: false, externalId: null, channel: "resend" };
  }
}
