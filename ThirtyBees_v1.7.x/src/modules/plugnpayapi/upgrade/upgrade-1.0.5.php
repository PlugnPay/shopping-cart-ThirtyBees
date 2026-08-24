<?php
/**
 * Align hooks with thirty bees 1.7 / Authorize.net AIM and restore restriction rows.
 *
 * @param Plugnpayapi $module
 *
 * @return bool
 */
function upgrade_module_1_0_5($module)
{
    $module->unregisterHook('displayPaymentEU');

    $ok = $module->registerHook('payment')
        && $module->registerHook('displayPayment')
        && $module->registerHook('paymentReturn')
        && $module->registerHook('header')
        && $module->registerHook('displayHeader');

    if (method_exists($module, 'addCheckboxCurrencyRestrictionsForModule')) {
        $ok = $module->addCheckboxCurrencyRestrictionsForModule() && $ok;
    }
    if (method_exists($module, 'addCheckboxCountryRestrictionsForModule')) {
        $ok = $module->addCheckboxCountryRestrictionsForModule() && $ok;
    }
    if (method_exists($module, 'addCheckboxCarrierRestrictionsForModule')) {
        $ok = $module->addCheckboxCarrierRestrictionsForModule() && $ok;
    }

    return $ok;
}
