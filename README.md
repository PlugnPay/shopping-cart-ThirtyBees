# Shopping Cart - thirty bees Payment Modules

Easy to install payment modules for the [thirty bees](https://thirtybees.com/) shopping cart.
**thirty bees 1.7** includes Remote API (onsite card form) and Smart Screens v2 (hosted redirect).

Current module versions: **Remote API 1.0.8** · **Smart Screens v2 1.0.2**.

## Downloads by thirty bees version

### thirty bees v1.7.x (current)

* **Remote API** — onsite card form on checkout step 5 (Payments), then server-to-server authorization
  - [Download](./ThirtyBees_v1.7.x/thirtybees_1.7.0_api_module.zip)
  - Source: [./ThirtyBees_v1.7.x/src/modules/plugnpayapi/](./ThirtyBees_v1.7.x/src/modules/plugnpayapi/)
  - Docs: [package README](./ThirtyBees_v1.7.x/README.md) · [INSTALL.txt](./ThirtyBees_v1.7.x/INSTALL.txt) · [module README](./ThirtyBees_v1.7.x/src/modules/plugnpayapi/README.md)
* **Smart Screens v2** — “Pay securely by credit card with PlugnPay” button, then hosted checkout
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
3. Install / enable **PlugnPay Remote API**. If thirty bees offers **Upgrade**, click it.
4. Enter Publisher Name plus Remote Client Password. Choose `authonly` or `authpostauth`.
5. In Preferences → SEO & URLs, set the shop SSL URL to `https://…`, then in Preferences → General enable SSL.
6. Confirm currency / country / group / carrier restrictions under Payment → Preferences.
7. Clear Smarty cache (Preferences → Performance).

- Quick install: [ThirtyBees_v1.7.x/INSTALL.txt](./ThirtyBees_v1.7.x/INSTALL.txt)

### thirty bees 1.7.x — Smart Screens v2

1. Download [thirtybees_1.7.0_ss2_module.zip](./ThirtyBees_v1.7.x/thirtybees_1.7.0_ss2_module.zip).
2. In Back Office → Modules and Services → Upload a module, upload the zip.
   Or unzip so the store has `modules/plugnpayss2/`.
3. Install / enable **PlugnPay Smart Screens v2**. If thirty bees offers **Upgrade**, click it.
4. Enter the Gateway Account.
5. Enable SSL as above (SEO & URLs first, then Preferences → General).
6. Confirm currency / country / group / carrier restrictions under Payment → Preferences.
7. Clear Smarty cache.

- Quick install: [ThirtyBees_v1.7.x/INSTALL_SS2.txt](./ThirtyBees_v1.7.x/INSTALL_SS2.txt)

You may install both modules; enable only the payment method(s) you need.

## Usage

### Remote API (thirty bees 1.7.x)

* On the Payments step the customer sees a **Secured card payment** box with
  cardholder name, number, expiration, optional CVV, and **Complete Purchase**.
* The store posts to `https://pay1.plugnpay.com/payment/pnpremote.cgi`.
* thirty bees **does** collect sensitive payment data at checkout (higher PCI scope).
* Authorization type is `authonly` or `authpostauth`.
* Capture / void / refund are done in PlugnPay Merchant Admin.
* Requires PHP **8.0+** with cURL and OpenSSL. HTTPS is required to submit the card.

### Smart Screens v2 (thirty bees 1.7.x)

* On the Payments step the customer clicks **Pay securely by credit card with PlugnPay**
  (PlugnPay icon, then hosted redirect).
* Hosted checkout at `https://pay1.plugnpay.com/pay/`.
* thirty bees does **not** collect sensitive payment data at checkout.
* Transactions are authorization-only (`pb_post_auth=no`); successful orders remain Preparation/Pending until settled.
* Capture / void / refund are done in PlugnPay Merchant Admin.
* Compatible with thirty bees **1.7.x**. Storefront HTTPS is required to list the method.

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
