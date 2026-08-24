<?php
/**
 * Register the canonical thirty bees checkout hooks.
 *
 * @param Plugnpayapi $module
 *
 * @return bool
 */
function upgrade_module_1_0_1($module)
{
    return $module->registerHook('displayPayment')
        && $module->registerHook('paymentReturn')
        && $module->registerHook('displayHeader');
}
