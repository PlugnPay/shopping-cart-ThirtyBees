# PlugnPay Remote API Module for thirty bees 1.7.x

**Version:** 1.0.5

Accept credit cards with PlugnPay's production Remote API. Checkout remains on
the merchant storefront, and the module posts the authorization request to
`https://pay1.plugnpay.com/payment/pnpremote.cgi`.

## Features

- Native thirty bees `PaymentModule` using the `displayPayment` hook
- Onsite cardholder name, PAN, expiration, and optional CVV collection
- Authorize-only (`authonly`) or sale (`authpostauth`)
- HTTPS required only when submitting the card form
- Server-side cart, customer, token, card, expiry, CVV, and amount validation
- Billing, shipping, tax, freight, and line-item Remote API fields
- PlugnPay order ID, authorization, AVS, and CVV result tracking
- Debug logs with PAN, CVV, and password redaction
- No thirty bees core file changes

Capture, void, and refund are not available in the thirty bees back office.
Perform these operations in
[PlugnPay Merchant Admin](https://pay1.plugnpay.com/admin/).

## PCI notice

This module collects cardholder data on the merchant server. This increases
PCI DSS scope. The store, hosting environment, logs, backups, extensions, and
operational processes must meet the applicable PCI DSS requirements.

For lower PCI scope, use a hosted PlugnPay checkout product instead.

## Requirements

- thirty bees 1.7.0
- PHP 8.0 or later with cURL and OpenSSL
- HTTPS enabled for card submit (the payment method still lists without it)
- PlugnPay publisher name
- Remote Client Password from PlugnPay Security Administration

The Remote Client Password is not the Merchant Admin login password.

## Configuration

| Setting | Configuration key | Behavior |
|---|---|---|
| Publisher Name | `PLUGNPAY_API_LOGIN` | Remote API `publisher-name` |
| Remote Client Password | `PLUGNPAY_API_PASSWORD` | Remote API `publisher-password`; masked in the back office |
| Publisher Email | `PLUGNPAY_API_PUBEMAIL` | Optional `publisher-email` and `notify-email` |
| Authorization Type | `PLUGNPAY_API_AUTHTYPE` | `authonly` or `authpostauth` |
| Prevent Gateway Customer Email | `PLUGNPAY_API_EMAILCUST` | `yes` sends `dontsndmail=yes` |
| Request CVV Number | `PLUGNPAY_API_USE_CVV` | Shows and validates the CVV field |
| Completed Order Status | `PLUGNPAY_API_ORDER_STATUS_ID` | Used by `authpostauth` |
| Debug Logging | `PLUGNPAY_API_DEBUGGING` | `Off` or `Log File` |

Enablement, display order, currency, country, customer group, and carrier
restrictions use standard thirty bees module and Payment Preferences controls.

### Authorization behavior

- `authonly`: authorizes the amount and creates the order in the standard
  Preparation/Pending state. Settle it in PlugnPay Merchant Admin.
- `authpostauth`: authorizes and settles the amount, then uses the configured
  Completed Order Status.

There is no test/production switch. The module always uses the production
Remote API endpoint.

## Checkout flow

1. The `payment` / `displayPayment` hooks display the card form when the module is
   active, configured, and currency-eligible. Missing HTTPS does not hide the method.
2. The validation controller verifies the active cart, customer, secure
   checkout token, currency, addresses, card number, expiration, and CVV.
3. The API client posts a form-encoded request with SSL peer and host
   verification.
4. `FinalStatus=success` or `success=yes` is treated as approval.
5. The module compares a returned amount to the cart total when the response
   includes an amount, then creates the order.
6. The PlugnPay `orderID` is stored as the transaction ID. Authorization, AVS,
   and CVV results are included in the non-sensitive order message.
7. Declines return the customer to payment. Communication failures use a
   generic message and do not create an order.

Orders totaling zero use Remote API `mode=checkcard` and omit `card-amount`.

## Logging

When Debug Logging is `Log File`, logs are written under the store `log`
directory:

```text
plugnpay_api_YYYYMMDD.log
```

The logger redacts:

- Full card number (only the last four may remain)
- CVV
- Publisher password

Do not weaken these protections or add raw request/response dumps elsewhere.

## Troubleshooting

- **Module does not appear:** verify it is enabled, Publisher Name and Remote
  Client Password are saved, and it is allowed in Modules and Services →
  Payment (currency, country, and carrier restrictions). Upload 1.0.5, click
  Upgrade if thirty bees offers it, then clear Smarty cache.
- **cURL warning:** enable PHP cURL and verify outbound TCP 443 access.
- **Empty response:** check DNS, firewall, proxy, CA certificates, and outbound
  HTTPS connectivity.
- **Decline:** review the customer-safe gateway message and the matching
  transaction in PlugnPay Merchant Admin.
- **HTTP 500 on payment step:** upload 1.0.5 and upgrade. Version 1.0.3
  registered `displayPaymentEU` (array return) which PHP 8 can fatal when
  concatenated as a string. Do not resubmit a card if the gateway already
  approved it.
- **Approved but order creation failed:** do not resubmit. Use the displayed
  gateway reference to reconcile the transaction in Merchant Admin and review
  the thirty bees log.

Connectivity can be checked from the store server with merchant-approved test
credentials and card data:

```bash
curl -d "publisher-name=ACCOUNT&publisher-password=REMOTE_PASSWORD&mode=auth&authtype=authonly&card-name=cardtest&card-number=4111111111111111&card-exp=01/30&card-cvv=123&card-amount=1.23" \
  https://pay1.plugnpay.com/payment/pnpremote.cgi
```

## Manual test checklist

- [ ] Module installs and uninstalls without core changes.
- [ ] All configuration values save; saved password is not displayed.
- [ ] HTTPS is required only when submitting the card form, not to list the method.
- [ ] Module follows currency, country, group, and carrier restrictions.
- [ ] CVV field and server validation follow the CVV setting.
- [ ] Invalid PAN, expired card, invalid CVV, or invalid token is rejected
      before a gateway request.
- [ ] Approved `authonly` creates a Preparation/Pending order containing the
      PlugnPay order ID and authorization details.
- [ ] Approved `authpostauth` creates an order with the configured completed
      status.
- [ ] Decline and fraud responses return a safe message to checkout.
- [ ] Network failure does not create an order.
- [ ] Re-submitting an already ordered cart does not contact the gateway.
- [ ] A returned amount mismatch does not create an order and is logged.
- [ ] Debug log masks PAN and redacts CVV and publisher password.
- [ ] No capture, void, refund, or test-mode controls appear in the module.

## Uninstall

Uninstall the module from Modules and Services. Module configuration is
removed; historical order and transaction records remain.

## Support

Provided AS IS. See the
[Remote API integration specification](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/)
and [PlugnPay documentation](https://docs.plugnpay.com/).
