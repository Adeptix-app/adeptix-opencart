# Adeptix Payment Extension for OpenCart

Accept direct on-chain crypto payments (USDT/USDC) and hosted card/bank/wallet checkouts through
[Adeptix](https://adeptix.app), from one OpenCart extension.

Full API reference: **https://docs.adeptix.app**

## What this adds

Two independent payment methods at checkout, each with its own enable/sort-order/order-status
settings under **Extensions → Payments → Adeptix**:

- **Card / Bank / Wallet (Adeptix)** — hosted checkout via whichever provider you configure (e.g.
  `stripe`). Confirming redirects the customer to the hosted checkout; the order moves to your
  configured "paid" status automatically when Adeptix's webhook confirms payment.
- **Crypto (USDT/USDC via Adeptix)** — direct on-chain payment on BSC, Polygon, or Tron. Confirming
  shows the customer a deposit address and exact amount in place, without leaving your store; the
  order moves to your configured "paid" status once the deposit is matched.

## Install

1. Zip this extension's folder (containing `install.json`, `admin/`, `catalog/`, `vendor/`) and
   upload it via **Extensions → Installer**. Put those files at the root of the zip (not inside a
   subfolder) and name it `adeptix.ocmod.zip` — OpenCart derives the extension code from the file
   name, and this extension's routes all live under `extension/adeptix/`.
2. Go to **Extensions → Payments**, find **Adeptix**, install it, then open its settings.
3. Set your **API key** (from your Adeptix dashboard's API Keys page), **webhook secret** (from
   Settings), provider ID, and crypto chain/token. Enable each payment method you want to offer,
   and set which order status each one should move to when awaiting payment vs. once paid.
4. The settings page shows the exact webhook URLs to register in your Adeptix dashboard's Settings
   page (one per payment method).

## How it works

Both flows build on the [official Adeptix PHP SDK](https://github.com/Adeptix-app/adeptix-php)
(bundled in `vendor/`, not a separate install step). Order matching uses OpenCart's own order id as
the `order_ref` sent to Adeptix — unlike some other platforms, OpenCart's core checkout flow already
creates the order row before a payment method's `confirm()` action runs, so there's no
cart-vs-order-id indirection needed. Crypto deposit details (address/amount/expiry) are kept in a
small dedicated table (`adeptix_crypto_order`, created when the extension is installed).

## Requirements

- PHP 8.0+
- OpenCart 4.x (uses OpenCart 4's namespaced MVC-L extension structure)

## Verification note

Built directly against OpenCart's own official extension patterns (its documented MVC-L structure
and the confirm/redirect flow shown in OpenCart's own current example payment extensions) rather
than guessed at, and every PHP file passes `php -l`. Like the Adeptix PrestaShop module
and unlike the Adeptix WooCommerce plugin, this was **not** exercised against a real running OpenCart
install — review and test a real checkout/webhook flow in a staging store before using this in
production.

## License

MIT
