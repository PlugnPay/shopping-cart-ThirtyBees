# PlugnPay modules for thirty bees 1.7.x

This package provides PlugnPay payment integrations for thirty bees 1.7.0.

## Modules

| Module | Type | Install guide |
|---|---|---|
| `plugnpayapi` | Remote API (onsite card form) | [INSTALL.txt](INSTALL.txt) |
| `plugnpayss2` | Smart Screens v2 (hosted redirect) | [INSTALL_SS2.txt](INSTALL_SS2.txt) |

## Package contents

```text
ThirtyBees_v1.7.x/
  INSTALL.txt
  INSTALL_SS2.txt
  README.md
  thirtybees_1.7.0_api_module.zip
  thirtybees_1.7.0_ss2_module.zip
  src/modules/plugnpayapi/
  src/modules/plugnpayss2/
```

Upload the zip for the module you need through Modules and Services, or copy
the module directory so the installed store path is:

```text
{thirty_bees_root}/modules/plugnpayapi/
{thirty_bees_root}/modules/plugnpayss2/
```

See the module README files for configuration, security, and manual test
references:

- [Remote API module README](src/modules/plugnpayapi/README.md)
- [Smart Screens v2 module README](src/modules/plugnpayss2/README.md)
