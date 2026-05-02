# Email Deliverability — keeping confirmations & reminders out of spam

If clients aren't receiving booking confirmations or reminder emails, the issue is almost never the plugin's templates — it's how the message gets sent and whether the receiving server trusts your sending domain. This page walks you through the standard fixes, in order of impact.

## TL;DR — three things to do

1. **Send through a real SMTP provider** (not your web host's PHP `mail()`).
2. **Add SPF, DKIM, and DMARC DNS records** for your domain.
3. **Use a "From" address on your own domain** (not `@gmail.com`).

The plugin includes diagnostics for all three. Run them at **Settings → Caswell Booking → Tools & Diagnostics → Email Delivery Test**.

---

## 1. Send through a real SMTP provider

WordPress's `wp_mail()` defaults to PHP's built-in `mail()` function, which goes out through your hosting server (Bluehost, GoDaddy, etc.). Shared hosting servers have a poor sender reputation; large inbox providers (Gmail, Outlook, iCloud) silently drop or spam-fold those messages.

### The fix

Install **[WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/)** (free) and route through one of:

| Provider | Free tier | Notes |
|---|---|---|
| **Gmail / Google Workspace SMTP** | Free with Workspace | Easiest if you already have a Workspace mailbox. Sends "as" your real address. |
| **SendGrid** | 100/day | Strong deliverability, simple setup. |
| **Mailgun** | 100/day (3-month trial) then pay-as-you-go | Good for transactional. |
| **Postmark** | Pay-as-you-go (~$1.25 per 10k) | Best transactional reputation. |
| **Amazon SES** | $0.10 per 1k | Cheapest at scale; setup is fiddly. |

Any of these will do more than every other tip combined.

### Verification

After connecting a provider, run **Tools → Email Delivery Test**. The plugin reports whether `wp_mail()` accepted the message — a "yes" means the provider received it, then check your inbox (and spam folder).

---

## 2. Set up SPF, DKIM, and DMARC

Even with a great provider, mail to Gmail and Outlook gets spam-filtered without these DNS records. They prove the sending server is allowed to send mail "from" your domain.

| Record | Purpose |
|---|---|
| **SPF** (TXT) | Lists IPs / providers allowed to send from your domain. |
| **DKIM** (TXT or CNAME) | Cryptographic signature on each message. |
| **DMARC** (TXT) | Tells receivers what to do if SPF/DKIM fail (reject / quarantine / monitor). |

### Setup walkthrough — SendGrid

1. **Sign up** at [sendgrid.com](https://sendgrid.com) and verify your account.
2. **Settings → Sender Authentication → Authenticate Your Domain**.
3. Pick your DNS host (GoDaddy, Cloudflare, Namecheap, etc.) and enter your domain (e.g. `castherapylmt.com`).
4. SendGrid generates 3 CNAME records. Add them at your DNS host:
   ```
   em1234.castherapylmt.com.       CNAME  u123.wl.sendgrid.net.
   s1._domainkey.castherapylmt.com CNAME  s1.domainkey.u123.wl.sendgrid.net.
   s2._domainkey.castherapylmt.com CNAME  s2.domainkey.u123.wl.sendgrid.net.
   ```
5. Wait 5–60 min for DNS propagation, click **Verify** in SendGrid.
6. **Generate an API key** at SendGrid → Settings → API Keys → "Restricted Access" → enable Mail Send → Save the key.
7. Back in WordPress: install **WP Mail SMTP**, choose **SendGrid**, paste the API key, set the From address to `bookings@castherapylmt.com` (or whatever).
8. Run the plugin's Email Delivery Test.

### Add DMARC

After SPF + DKIM pass, add a DMARC record (also a TXT record) at your DNS host:

```
_dmarc.castherapylmt.com   TXT   "v=DMARC1; p=none; rua=mailto:postmaster@castherapylmt.com; pct=100"
```

`p=none` means "report-only" — start there to see what's failing without rejecting anything. After a couple of weeks of clean reports, tighten to `p=quarantine` or `p=reject`.

### Setup walkthrough — Google Workspace

If you already have a Google Workspace account for `castherapylmt.com`:

1. **WP Mail SMTP** → Settings → choose **Other SMTP** or **Gmail / Google Workspace**.
2. Either:
   - Use **App Password**: easiest. Workspace Admin → Security → 2-Step Verification → App passwords → generate one for "Mail" → paste in WP Mail SMTP.
   - Or **OAuth**: more work but doesn't break if password rotates.
3. Workspace handles SPF + DKIM automatically for emails sent through their SMTP. You still need a DMARC TXT record (above).

---

## 3. Use a From address on your own domain

The plugin's **Settings → Caswell Booking → Email** has a **From Address** field. **Always set this to an address on the domain you own** (e.g. `bookings@castherapylmt.com`).

If the From address is on a free provider (`@gmail.com`, `@yahoo.com`, `@outlook.com`), most receivers reject the message outright due to DMARC enforcement. The plugin's Email Delivery Test now flags this with a yellow warning.

### What if my domain doesn't have email yet?

Two cheap options:

- **Domain registrar email forwarding** — Namecheap and Cloudflare offer free email forwarding. Create `bookings@castherapylmt.com` and forward to your real Gmail. You can't *send* from it, but the plugin doesn't need to receive — only send through SendGrid/Mailgun/Postmark, which doesn't care that your domain has no inbox.
- **Google Workspace** — $6/mo, full mailbox. Recommended once volume picks up.

---

## What the plugin does on its end

When you upgrade to v1.4.7 or later, the plugin attaches these headers to every outgoing email — automatically:

- **`From`** — uses your configured From address
- **`Reply-To`** — matches the From address so client replies route correctly
- **`Message-ID`** — uniquely identifies each message; some hosts drop emails without one
- **`Date`** — explicit RFC 2822 date (some hosts mis-format wp_mail's auto-date)
- **`List-Unsubscribe: <mailto:From-address?subject=Unsubscribe>`** — Gmail and Outlook give cleaner inbox placement to messages that expose a one-click unsubscribe path. The mailto pattern routes any "unsubscribe" click straight to the practitioner.

Reminder emails also get a small visible footer link inviting clients to reply with "stop reminders" — Gmail's spam filters reward emails with clear opt-outs.

## Verification checklist

After completing the steps above, send yourself a test booking and check the email's headers. In Gmail, click the message → ⋮ → "Show original". Look for:

- `SPF: PASS`  ✓
- `DKIM: PASS`  ✓
- `DMARC: PASS`  ✓
- `Authentication-Results:` header showing all three

If any are missing or fail, that's the next thing to fix. SendGrid, Mailgun, and Postmark all have great support for diagnosing this.

## Common gotchas

- **Different domain in From and SMTP** — sending via `gmail.com` SMTP but with `From: name@castherapylmt.com` will fail DMARC. Either send via Workspace (which signs for `castherapylmt.com`), or set From to the Gmail address.
- **DNS not propagated** — TTL can be 24 hours on some registrars. Be patient.
- **Subject line too "promotional"** — confirmations are transactional and rarely flagged, but reminders sometimes are. Avoid all-caps, "Free!", excessive punctuation.
- **No plain-text alternative** — HTML-only emails score worse on some filters. The plugin currently sends HTML only; planned for a future release.
- **Sending too many at once** — if you suddenly send 1000 cold-list reminders, expect a deliverability hit. The plugin's per-booking trigger keeps you well below that threshold organically.

---

> 💡 If you're stuck after working through this page, the **debug log** (Tools → Debug Logging → enable, then trigger a send) shows the exact headers and the wp_mail response. Paste those into your provider's support chat — SendGrid/Mailgun/Postmark all have good first-line support for deliverability questions.
