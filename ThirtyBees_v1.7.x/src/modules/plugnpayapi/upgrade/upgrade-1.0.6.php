<?php
/**
 * Match Authorize.net AIM display behavior and restore checkout restriction rows.
 *
 * @param Plugnpayapi $module
 *
 * @return bool
 */
function upgrade_module_1_0_6($module)
{
    return $module->ensurePaymentRestrictions();
}
