# thirty bees 1.7.x — PlugnPay Payment Modules

Payment modules for thirty bees **1.7.0**. Current module version: **v1.0.1**. Both modules install through Back Office → Modules and Services.

Setup and usage follow the same Remote API / Smart Screens v2 split used in the PrestaShop 9.1 and Zen Cart 2.2 packages.

## Choose a module

| | Remote API | Smart Screens v2 |
|---|---|---|
| Package | `plugnpayapi` | `plugnpayss2` |
| Download | [thirtybees_1.7.0_api_module.zip](./thirtybees_1.7.0_api_module.zip) | [thirtybees_1.7.0_ss2_module.zip](./thirtybees_1.7.0_ss2_module.zip) |
| Checkout | Onsite card fields → `pnpremote.cgi` | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card data on your server | Yes | No |
| PCI scope | Higher | Lower |
| Transaction mode | Auth-only or sale (`authonly` / `authpostauth`) | Authorization-only |
| Admin Capture / Void / Refund | No (use PlugnPay Admin) | No (use PlugnPay Admin) |
| Public demo account | No — merchant credentials only | No — merchant credentials only |

You may install both modules; enable only the payment method(s) you need under Payment → Preferences.

## Remote API (onsite)

- Source: [src/modules/plugnpayapi/](./src/modules/plugnpayapi/)
- Full docs: [src/modules/plugnpayapi/README.md](./src/modules/plugnpayapi/README.md)
- Quick install: [INSTALL.txt](./INSTALL.txt)

Collects card data on your storefront and posts from the server to PlugnPay Remote API (`authonly` or `authpostauth`). Capture / void / refund are done in PlugnPay Merchant Admin.

## Smart Screens v2 (hosted)

- Source: [src/modules/plugnpayss2/](./src/modules/plugnpayss2/)
- Full docs: [src/modules/plugnpayss2/README.md](./src/modules/plugnpayss2/README.md)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)

Redirects customers to PlugnPay hosted Smart Screens. Return POST completes the thirty bees order as **Preparation/Pending** (authorization-only). Capture / void / refund are done in PlugnPay Merchant Admin, not from thirty bees.

## Common install steps (both)

1. In Back Office → **Modules and Services** → **Upload a module**, upload `thirtybees_1.7.0_api_module.zip` or `thirtybees_1.7.0_ss2_module.zip`.
   Alternatively, unzip the zip into the thirty bees `modules/` directory so `modules/plugnpayapi/…` or `modules/plugnpayss2/…` is created, **or** copy from `src/modules/…`.
2. Install / enable the module in Modules and Services.
3. Configure credentials (and authorization type for Remote API).
4. Ensure storefront HTTPS is enabled.
5. Select allowed currencies (and countries) under Payment preferences / module restrictions.

## Development layout

```
ThirtyBees_v1.7.x/
  INSTALL.txt                 # API quick install
  INSTALL_SS2.txt             # Smart Screens v2 quick install
  thirtybees_1.7.0_api_module.zip
  thirtybees_1.7.0_ss2_module.zip
  README.md
  src/modules/
    plugnpayapi/
    plugnpayss2/
```
