# Slack Integration

StaffPick can talk to your team's Slack two ways: **outbound** alerts pushed into a channel, and **inbound** referral messages that create draft cases. Both are configured per tenant on the **Slack Integration** settings page.

## Outbound: alerts into Slack

Outbound uses a Slack **Incoming Webhook**.

1. In Slack, create an Incoming Webhook for the channel you want alerts in (e.g. `#staffpick-staging`) and copy the webhook URL.
2. Paste it into **Slack Webhook URL** on the settings page and save.
3. Use **Test** to fire a sample message — if it lands in the channel, you're connected.

Once configured, StaffPick posts Block Kit alerts for key events: a new intake received, a provider assigned, a credential expiring, a provider profile submitted for review, and **no clinicians available**. Each alert includes a deep link straight to the case in the dashboard.

> If the webhook URL is blank, outbound alerts are simply skipped — nothing errors.

## Inbound: referrals from Slack

Inbound lets a referring teammate drop a message in a channel and have StaffPick create a **draft intake** from it.

1. Copy the tenant's **Inbound URL** and **referral keyword** from the settings page (use the **Copy** button).
2. In Slack, add the Inbound URL to your app's Event Subscriptions and subscribe to `message.channels`.
3. Post a message containing the keyword (default: *new referral*) in the connected channel.

A signed request comes back to StaffPick, which creates a draft intake and posts a confirmation reply. Messages from bots are ignored so the integration never loops on its own confirmations.

## Security notes

- Inbound requests are verified with Slack's signing secret — unsigned or stale requests are rejected.
- The inbound token is unique per tenant; treat the Inbound URL like a secret.
- Outbound posting is queued, so a slow or down Slack endpoint never blocks the action that triggered it.

## Troubleshooting

- **Test does nothing** — re-check the webhook URL and that the channel still exists.
- **Inbound not creating drafts** — confirm the keyword matches and the Event Subscription is verified in Slack.
