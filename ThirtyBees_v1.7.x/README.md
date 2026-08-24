# thirty bees 1.7.x — PlugnPay Payment Modules

Payment modules for thirty bees **1.7.0**. Install through Back Office → Modules and Services.

| Module | Package | Current version |
|---|---|---|
| Remote API (onsite card form) | `plugnpayapi` | **1.0.8** |
| Smart Screens v2 (hosted redirect) | `plugnpayss2` | **1.0.2** |

Setup follows the same Remote API / Smart Screens v2 split used in the PrestaShop 9.1 and Zen Cart 2.2 packages.

## Choose a module

| | Remote API | Smart Screens v2 |
|---|---|---|
| Package | `plugnpayapi` | `plugnpayss2` |
| Download | [thirtybees_1.7.0_api_module.zip](./thirtybees_1.7.0_api_module.zip) | [thirtybees_1.7.0_ss2_module.zip](./thirtybees_1.7.0_ss2_module.zip) |
| Payments step (checkout step 5) | **Secured card payment** box: name, number, expiry, CVV, Complete Purchase | **Pay securely by credit card with PlugnPay** button (icon + redirect) |
| Card data on your server | Yes | No |
| PCI scope | Higher | Lower |
| Gateway | `pnpremote.cgi` | `https://pay1.plugnpay.com/pay/` |
| Transaction mode | Auth-only or sale (`authonly` / `authpostauth`) | Authorization-only |
| Admin Capture / Void / Refund | No (use PlugnPay Admin) | No (use PlugnPay Admin) |
| Public demo account | No — merchant credentials only | No — merchant credentials only |

You may install both modules; enable only the payment method(s) you need under Payment → Preferences.

## Remote API (onsite)

- Source: [src/modules/plugnpayapi/](./src/modules/plugnpayapi/)
- Full docs: [src/modules/plugnpayapi/README.md](./src/modules/plugnpayapi/README.md)
- Quick install: [INSTALL.txt](./INSTALL.txt)

The customer enters the card on your Payments step. The store posts to PlugnPay Remote API (`authonly` or `authpostauth`). Capture / void / refund are done in PlugnPay Merchant Admin.

## Smart Screens v2 (hosted)

- Source: [src/modules/plugnpayss2/](./src/modules/plugnpayss2/)
- Full docs: [src/modules/plugnpayss2/README.md](./src/modules/plugnpayss2/README.md)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)

The customer clicks the PlugnPay payment button and is redirected to hosted Smart Screens. Return POST completes the thirty bees order as **Preparation/Pending** (authorization-only). Capture / void / refund are done in PlugnPay Merchant Admin, not from thirty bees.

## Common install steps (both)

1. In Back Office → **Modules and Services** → **Upload a module**, upload `thirtybees_1.7.0_api_module.zip` or `thirtybees_1.7.0_ss2_module.zip`.
   Alternatively, unzip into `modules/` so `modules/plugnpayapi/…` or `modules/plugnpayss2/…` exists, **or** copy from `src/modules/…`.
2. Install / enable the module. If thirty bees offers **Upgrade**, click it (required when replacing an older zip).
3. Configure credentials (and authorization type for Remote API).
4. In Preferences → **SEO & URLs**, set the shop SSL URL to `https://…`. Then in Preferences → **General**, enable SSL.
5. Select allowed currencies, countries, groups, and carriers under Payment preferences / module restrictions.
6. Clear Smarty cache (Preferences → Performance).

If checkout reports “too many redirects” after enabling SSL, fix the SSL shop URL and PHP HTTPS detection before turning on “Enable SSL on all pages”. See [INSTALL.txt](./INSTALL.txt).

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
