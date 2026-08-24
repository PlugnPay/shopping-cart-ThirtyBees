<?php
/**
 * Register checkout payment hooks and default currency/country/carrier restrictions.
 *
 * @param Plugnpayapi $module
 *
 * @return bool
 */
function upgrade_module_1_0_3($module)
{
    return $module->registerHook('displayPayment')
        && $module->registerHook('paymentReturn')
        && $module->registerHook('displayHeader')
        && $module->addCheckboxCurrencyRestrictionsForModule()
        && $module->addCheckboxCountryRestrictionsForModule()
        && $module->addCheckboxCarrierRestrictionsForModule();
}
