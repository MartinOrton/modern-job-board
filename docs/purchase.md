# Modern Job Board — purchase & license keys

**Audience:** product owner (vendor) and site operators activating a paid plan.

## Gateway model (important)

MJB does **not** integrate a payment processor directly for plugin licenses.

- **Buy** buttons use a configurable checkout URL (or a mailto fallback).
- **Key delivery** can be automated when a **WooCommerce** order completes for a product marked as an MJB license.
- **Payment** is entirely handled by **WooCommerce + whatever gateway plugin you install**.

### Any WooCommerce-supported gateway

If it works with WooCommerce in your country, it works with MJB. Examples (availability depends on merchant country and account approval):

| Region / type | Examples |
|---------------|----------|
| **Global / multi-country** | PayPal, Stripe, Square, Mollie, Adyen, Braintree, 2Checkout / Verifone |
| **Card / wallets (various)** | Authorize.net, Worldpay, Klarna (via gateway), Apple Pay / Google Pay (via supporting gateways) |
| **India & APAC (examples)** | Razorpay, PayU, Paytm |
| **Africa (examples)** | Paystack, Flutterwave, Payfast, Yoco, Ozow, Peach |
| **Offline / manual** | Bank transfer (BACS), cheque, invoice — complete order manually, then issue a key |

You are not limited to the names above. Use the official WooCommerce extension (or a reputable third-party WC gateway) that fits your business.

### Country restrictions (e.g. Stripe)

Some processors **do not onboard merchants** in every country. For example, **Stripe often cannot be used as a *seller* gateway from South Africa**, even though SA customers can pay *other* merchants who use Stripe elsewhere. That is a processor policy issue, not an MJB limitation—pick another WooCommerce gateway (or manual EFT + key issue) if a given provider is unavailable for you.

---

## Customer flow (board owner)

1. Choose a plan (Free / Pro / Business / Complete Site).
2. Pay via your checkout URL (WooCommerce product, or invoice / EFT).
3. Receive a license key by email (automatic or from support).
4. In WordPress: **Job Board → Settings → License & plan** → paste key → Save.
5. Features unlock immediately (offline signed key). Optional: `define('MJB_LICENSE_PLAN', 'pro');` in `wp-config.php`.

Key format: `MJB-{PLAN}-{YYYYMMDD|00000000}-{checksum}`  
Plan codes: `PRO`, `BUS`, `CS`.

---

## Vendor setup

### Recommended: WooCommerce sales site + any WC gateway

Use a dedicated sales WordPress site (or the same host as marketing):

1. Install **WooCommerce** + your chosen **payment gateway** extension.
2. Create products (e.g. “MJB Pro — annual”, “MJB Business — annual”). Currency can be USD, EUR, ZAR, etc.
3. On each product: set **MJB plugin license** → Pro / Business / Complete Site.
4. On **order completed** (or payment complete), MJB:
   - generates a signed key  
   - emails the buyer with activation steps  
   - adds an order note  

Leave the license field empty on *employer* job-credit / pay-per-post products (those are for board operators, not plugin licenses).

### Checkout URL fields (Settings → License & plan)

| Option | What to put |
|--------|-------------|
| Pro checkout URL | Direct link to the Woo **Pro** product (or cart/checkout with that product) |
| Business checkout URL | Same for Business |
| Complete Site checkout URL | Enquiry form, quote page, or high-touch booking URL |
| Sales email | Mailto fallback when a URL is empty |

Locked features show **Buy {Plan}** using these URLs.

### Manual sales (EFT, invoice, Complete Site)

1. Customer pays via bank transfer or invoice.
2. **Settings → License & plan → Issue a license key (vendor)**.
3. Email the key (or use the optional “email key to” field).

Works with zero online gateway.

### Other options (optional)

| Approach | Notes |
|----------|--------|
| **Merchant-of-record** (e.g. Paddle, Lemon Squeezy, Gumroad) | They take payment as the seller; check tax/compliance and seller eligibility. Point checkout URLs at their product pages if you use them instead of Woo. |
| **Mailto only** | Leave checkout URLs blank; CTAs open a structured sales email. Fine for early volume. |

---

## Marketing site CTAs

1. Prefer **product / checkout URLs** once Woo + gateway is live.  
2. Until then, structured **mailto** is intentional.  
3. Free tier: docs / get started — not a payment link.

---

## Security note

Offline keys use an HMAC salt in the plugin (soft DRM). They deter casual piracy; they are not a remote license server. Remote validation can be added later without changing activation UX.
