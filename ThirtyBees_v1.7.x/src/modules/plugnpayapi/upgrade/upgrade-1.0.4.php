<?php
/**
 * Remove displayPaymentEU; it returns an array and PHP 8 fatals when thirty bees concatenates hook output.
 *
 * @param Plugnpayapi $module
 *
 * @return bool
 */
function upgrade_module_1_0_4($module)
{
    $module->unregisterHook('displayPaymentEU');

    return $module->registerHook('displayPayment')
        && $module->registerHook('paymentReturn')
        && $module->registerHook('displayHeader');
}
