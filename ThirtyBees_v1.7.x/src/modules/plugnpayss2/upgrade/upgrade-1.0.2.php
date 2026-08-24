<?php
/**
 * Checkout payment-method button for hosted Smart Screens v2.
 *
 * @param Plugnpayss2 $module
 *
 * @return bool
 */
function upgrade_module_1_0_2($module)
{
    return $module->registerHook('payment')
        && $module->registerHook('displayPayment')
        && $module->registerHook('displayHeader')
        && $module->registerHook('header');
}
