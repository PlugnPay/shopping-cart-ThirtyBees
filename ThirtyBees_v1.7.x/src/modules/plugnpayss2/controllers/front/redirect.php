<?php
/**
 * Redirect front controller for PlugnPay Smart Screens v2.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

class Plugnpayss2RedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;

    public function initContent()
    {
        parent::initContent();

        /** @var Plugnpayss2 $module */
        $module = $this->module;

        if (!$module->active || !$module->isConfigured() || !$module->isSecureRequest()) {
            $this->redirectToPayment($module->l('This payment method is not available.'));
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || (int) $cart->id_customer <= 0 || $cart->OrderExists()) {
            $this->redirectToPayment($module->l('This cart cannot be processed.'));
        }

        if (!$module->checkCurrency($cart)) {
            $this->redirectToPayment($module->l('This payment method is unavailable for the selected currency.'));
        }

        $customer = new Customer((int) $cart->id_customer);
        $invoiceAddress = new Address((int) $cart->id_address_invoice);
        if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($invoiceAddress)) {
            $this->redirectToPayment($module->l('Unable to load customer or billing address.'));
        }

        $built = $module->buildHostedFields($cart, $customer, $invoiceAddress);
        if ((float) $built['amount'] <= 0) {
            $this->redirectToPayment($module->l('Invalid order amount.'));
        }

        $module->storeExpectedReturn($cart, $built['amount'], $built['token'], $built['fields']);

        $this->context->smarty->assign([
            'plugnpayss2_gateway_url' => Plugnpayss2::GATEWAY_URL,
            'plugnpayss2_hosted_fields' => $built['fields'],
        ]);

        $this->display_header = false;
        $this->display_footer = false;
        $this->setTemplate('module:plugnpayss2/views/templates/front/redirect.tpl');
    }

    private function redirectToPayment($message)
    {
        Tools::redirect(
            $this->context->link->getPageLink(
                'order',
                true,
                null,
                http_build_query([
                    'step' => 3,
                    'plugnpayss2_error' => Tools::substr(strip_tags((string) $message), 0, 300),
                ])
            )
        );
        exit;
    }
}
