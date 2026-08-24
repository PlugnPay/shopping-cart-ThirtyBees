# PlugnPay Smart Screens v2 Module for thirty bees 1.7.x

**Version:** 1.0.1

Hosted **authorization-only** payments via PlugnPay Smart Screens v2:

`https://pay1.plugnpay.com/pay/`

This module mirrors the PlugnPay Zen Cart 2.2.x Smart Screens v2 offering.
Customers are redirected to PlugnPay for card entry. Your store does not
collect card data onsite. Capture, void, and refund are performed in
PlugnPay Merchant Admin.

## Features

- Native thirty bees `PaymentModule` using the `displayPayment` hook
- Offsite / hosted checkout (card data collected on PlugnPay)
- Authorization-only (`pb_post_auth=no`) — orders use Preparation/Pending
- Gateway currency setting with store exchange-rate conversion
- Optional `plugnpay_ss2` database table for sanitized submit/response snapshots
- Return validation for amount, gateway account, cart ID, and checkout token
- Debug logging with PAN / CVV / password redaction
- No thirty bees core file changes

## When to use this vs Remote API

| | Smart Screens v2 (this module) | Remote API (`plugnpayapi`) |
|---|---|---|
| Card data | Collected on PlugnPay | Collected on your store |
| Customer experience | Redirect to hosted billing page | Stays on your checkout |
| PCI scope | Lower | Higher |
| Checkout endpoint | `https://pay1.plugnpay.com/pay/` | `pnpremote.cgi` |
| Transaction mode | Authorization-only | Auth-only or sale |
| Remote Client Password | Not required | Required |

## Requirements

- thirty bees 1.7.0
- PHP 8.0 or later
- HTTPS enabled on the storefront
- PlugnPay gateway account username

No Remote Client Password, cURL outbound API call, or IP whitelist is
required for this hosted module.

## Configuration

| Setting | Configuration key | Notes |
|---|---|---|
| Gateway Account | `PLUGNPAY_SS2_LOGIN` | PlugnPay username (`pt_gateway_account`) |
| Currency Supported | `PLUGNPAY_SS2_CURRENCY` | USD, CAD, GBP, EUR, AUD, NZD |
| Enable Database Storage | `PLUGNPAY_SS2_STORE_DATA` | Writes `{prefix}plugnpay_ss2` |
| Debug Logging | `PLUGNPAY_SS2_DEBUGGING` | `Off` or `Log File` |

Enablement, display order, currency, country, customer group, and carrier
restrictions use standard thirty bees module and Payment Preferences controls.

Checkout always sends `pb_post_auth=no`. Successful orders use the standard
Preparation/Pending state until settled in PlugnPay Admin.

## Checkout flow

1. Customer selects **PlugnPay Smart Screens v2** on the payment step.
2. Customer is sent to the module redirect controller, which auto-POSTs to
   `https://pay1.plugnpay.com/pay/`.
3. Customer completes payment on PlugnPay Smart Screens (authorization only).
4. PlugnPay POSTs back to the module validation controller (`pb_success_url`).
5. The module validates amount, gateway account, cart ID, secure key, and token.
6. On success, the order is created as Preparation/Pending with AUTH and
   orderID in the order message.
7. On decline or error, the customer returns to payment with a safe message.

### Key fields submitted to `/pay/`

| Field | Purpose |
|---|---|
| `pt_gateway_account` | Merchant gateway account |
| `pt_transaction_amount` | Order total (converted to gateway currency if needed) |
| `pt_currency` / `pt_currency_code` | Gateway currency |
| `pb_post_auth` | Always `no` (authorization-only) |
| `pt_account_code_1` | Cart ID |
| `pb_success_url` | Module validation URL with cart ID, secure key, and token |
| `pb_transition_type` | `post` |
| `pt_client_identifier` | `ThirtyBees_SS2` |
| `pt_custom_name_1` / `pt_custom_value_1` | Custom pair: `tbcartid` = cart ID |
| `pt_custom_name_2` / `pt_custom_value_2` | Custom pair: `tbtoken` = checkout token |

## Logging

When Debug Logging is `Log File`, logs are written under the store `log`
directory:

```text
plugnpay_ss2_YYYYMMDD.log
```

When database storage is enabled, install creates `{prefix}plugnpay_ss2` and
stores sanitized submit/response snapshots. Uninstall drops the table.

Never logged: full card number, CVV, or publisher-password.

## Troubleshooting

| Symptom | What to check |
|---|---|
| Payment option missing | Module enabled, configured, HTTPS, currency/country/carrier restrictions |
| Return but no order | Validation logs, cart ID / token mismatch, duplicate cart order |
| Amount mismatch | Cart total changed between redirect and return, or currency conversion issue |
| Gateway account mismatch | Returned `pt_gateway_account` ≠ configured Gateway Account |
| Decline / fraud | Expected; customer is sent back to checkout payment |
| Need capture / void / refund | Use PlugnPay Merchant Admin |

## Manual test checklist

- [ ] Module installs and uninstalls without core changes.
- [ ] Database table is created when Store Data is enabled.
- [ ] Empty Gateway Account shows a configuration warning.
- [ ] Payment option appears only over HTTPS.
- [ ] Redirect auto-submits to `https://pay1.plugnpay.com/pay/`.
- [ ] Submit uses `pb_post_auth=no`.
- [ ] Approved payment creates a Preparation/Pending order with AUTH + orderID.
- [ ] Declined card shows a safe message and restores checkout.
- [ ] Amount, account, cart ID, and token mismatches are rejected.
- [ ] Duplicate cart submission redirects to existing order confirmation.
- [ ] Debug log and DB storage redact PAN/CVV/password.
- [ ] Currency conversion works when store currency ≠ gateway currency.
- [ ] No capture, void, refund, or test-mode controls appear in the module.

## Uninstall

Uninstall the module from Modules and Services. Module configuration and the
`plugnpay_ss2` table are removed; historical order records remain.

## Support

Provided AS IS. See the
[PlugnPay documentation](https://docs.plugnpay.com/) and
[thirty bees documentation](https://docs.thirtybees.com/).

For onsite checkout on thirty bees 1.7.x, see the
[PlugnPay Remote API module](../plugnpayapi/README.md).
