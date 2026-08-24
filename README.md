# Shopping Cart - thirty bees Payment Modules

Easy to install payment modules for the [thirty bees](https://thirtybees.com/) shopping cart.
**thirty bees 1.7** includes Remote API (onsite) checkout and Smart Screens v2 hosted checkout.

## Downloads by thirty bees version

### thirty bees v1.7.x (current)

* **Remote API** — onsite card form, server-to-server authorization
  - [Download](./ThirtyBees_v1.7.x/thirtybees_1.7.0_api_module.zip)
  - Source: [./ThirtyBees_v1.7.x/src/modules/plugnpayapi/](./ThirtyBees_v1.7.x/src/modules/plugnpayapi/)
  - Docs: [package README](./ThirtyBees_v1.7.x/README.md) · [INSTALL.txt](./ThirtyBees_v1.7.x/INSTALL.txt) · [module README](./ThirtyBees_v1.7.x/src/modules/plugnpayapi/README.md)
* **Smart Screens v2** — gateway hosted checkout
  - [Download](./ThirtyBees_v1.7.x/thirtybees_1.7.0_ss2_module.zip)
  - Source: [./ThirtyBees_v1.7.x/src/modules/plugnpayss2/](./ThirtyBees_v1.7.x/src/modules/plugnpayss2/)
  - Docs: [package README](./ThirtyBees_v1.7.x/README.md) · [INSTALL_SS2.txt](./ThirtyBees_v1.7.x/INSTALL_SS2.txt) · [module README](./ThirtyBees_v1.7.x/src/modules/plugnpayss2/README.md)

Package overview: [./ThirtyBees_v1.7.x/README.md](./ThirtyBees_v1.7.x/README.md)

## Installation

For complete instructions, open the README inside the zip (or the linked docs above).

### thirty bees 1.7.x — Remote API

1. Download [thirtybees_1.7.0_api_module.zip](./ThirtyBees_v1.7.x/thirtybees_1.7.0_api_module.zip).
2. In Back Office → Modules and Services → Upload a module, upload the zip.
   Or unzip so the store has `modules/plugnpayapi/`.
3. Install / enable **PlugnPay Remote API** and enter Publisher Name plus Remote Client Password.
4. Choose `authonly` or `authpostauth`. Confirm currency / country / group / carrier restrictions under Payment → Preferences.

- Quick install: [ThirtyBees_v1.7.x/INSTALL.txt](./ThirtyBees_v1.7.x/INSTALL.txt)

### thirty bees 1.7.x — Smart Screens v2

1. Download [thirtybees_1.7.0_ss2_module.zip](./ThirtyBees_v1.7.x/thirtybees_1.7.0_ss2_module.zip).
2. In Back Office → Modules and Services → Upload a module, upload the zip.
   Or unzip so the store has `modules/plugnpayss2/`.
3. Install / enable **PlugnPay Smart Screens v2** and enter the Gateway Account.
4. Confirm currency / country / group / carrier restrictions under Payment → Preferences.

- Quick install: [ThirtyBees_v1.7.x/INSTALL_SS2.txt](./ThirtyBees_v1.7.x/INSTALL_SS2.txt)

You may install both modules; enable only the payment method(s) you need.

## Usage

### Remote API (thirty bees 1.7.x)

* Onsite card fields; the store posts to `https://pay1.plugnpay.com/payment/pnpremote.cgi`.
* thirty bees **does** collect sensitive payment data at checkout (higher PCI scope).
* Authorization type is `authonly` or `authpostauth`.
* Capture / void / refund are done in PlugnPay Merchant Admin.
* Requires PHP **8.0+** with cURL and OpenSSL, and storefront HTTPS.

### Smart Screens v2 (thirty bees 1.7.x)

* Hosted checkout at `https://pay1.plugnpay.com/pay/`.
* thirty bees does **not** collect sensitive payment data at checkout.
* Transactions are authorization-only (`pb_post_auth=no`); successful orders remain Preparation/Pending until settled.
* Capture / void / refund are done in PlugnPay Merchant Admin.
* Compatible with thirty bees **1.7.x**.

## Repository layout

```
shopping-cart-ThirtyBees/
  README.md
  .gitignore
  ThirtyBees_v1.7.x/          # current (1.7.x) — Remote API + Smart Screens v2
    README.md
    INSTALL.txt
    INSTALL_SS2.txt
    thirtybees_1.7.0_api_module.zip
    thirtybees_1.7.0_ss2_module.zip
    src/modules/
      plugnpayapi/
      plugnpayss2/
```

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the module README for integration details.
