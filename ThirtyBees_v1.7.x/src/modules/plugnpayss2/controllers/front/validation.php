<?php
/**
 * Return handler for PlugnPay Smart Screens v2.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

class Plugnpayss2ValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        parent::postProcess();

        /** @var Plugnpayss2 $module */
        $module = $this->module;
        $logger = $module->getLogger();

        if (!$module->active || !$module->isSecureRequest()) {
            $this->redirectToPayment($module->l('This payment method is not available.'));
        }

        $authorize = $_POST;
        unset($authorize['btn_submit_x'], $authorize['btn_submit_y']);
        $logger->log('Response-Data', $authorize);

        $status = strtolower(trim((string) (isset($authorize['pi_response_status']) ? $authorize['pi_response_status'] : '')));
        if ($status === '') {
            $this->redirectToPayment(
                $module->l('There has been an error processing your credit card. Please try again.')
            );
        }

        $cart = $this->resolveCart($authorize, $module);
        if (!Validate::isLoadedObject($cart) || (int) $cart->id_customer <= 0) {
            $logger->log('Cart resolve failed', [
                'id_cart_get' => Tools::getValue('id_cart'),
                'tbcartid' => $module->extractCustomValue($authorize, 'tbcartid'),
            ]);
            $this->redirectToPayment(
                $module->l('Your payment session could not be verified. Please try again or contact us for assistance.')
            );
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->redirectToPayment(
                $module->l('Your payment session could not be verified. Please try again or contact us for assistance.')
            );
        }

        $this->context->cart = $cart;
        $this->context->customer = $customer;
        $this->context->currency = new Currency((int) $cart->id_currency);

        $secureKey = (string) Tools::getValue('key');
        if ($secureKey === '' || !hash_equals((string) $customer->secure_key, $secureKey)) {
            $logger->log('Secure key mismatch', ['returned' => $secureKey]);
            $this->redirectToPayment(
                $module->l('Your payment session could not be verified. Please try again or contact us for assistance.')
            );
        }

        $expectedToken = (string) (isset($this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_TOKEN})
            ? $this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_TOKEN}
            : '');
        $returnedToken = $module->extractCustomValue($authorize, 'tbtoken');
        if ($returnedToken === '') {
            $returnedToken = trim((string) Tools::getValue('pnp_token'));
        }
        if ($expectedToken !== '' && ($returnedToken === '' || !hash_equals($expectedToken, $returnedToken))) {
            $logger->log('Token mismatch', [
                'expected' => $expectedToken,
                'returned' => $returnedToken,
            ]);
            $this->redirectToPayment(
                $module->l('Your payment session could not be verified. Please try again or contact us for assistance.')
            );
        }

        if ($status === 'success') {
            $this->processSuccess($module, $cart, $customer, $authorize, $returnedToken);

            return;
        }

        $gatewayMessage = trim(strip_tags((string) (isset($authorize['pi_error_message']) ? $authorize['pi_error_message'] : '')));
        $module->storeTransactionRow(
            $status !== '' ? $status : 'error',
            $authorize,
            [],
            (int) $customer->id,
            $returnedToken
        );

        if ($status === 'badcard' || $status === 'fraud') {
            $message = $module->l('Your credit card was declined. Please try another card or contact your bank for more info.');
            if ($gatewayMessage !== '') {
                $message = Tools::substr($gatewayMessage, 0, 250) . ' ' . $message;
            }
            $this->redirectToPayment($message);
        }

        $this->redirectToPayment(
            $gatewayMessage !== ''
                ? Tools::substr($gatewayMessage, 0, 300)
                : $module->l('There has been an error processing your credit card. Please try again.')
        );
    }

    /**
     * @param array<string, mixed> $authorize
     */
    private function processSuccess(Plugnpayss2 $module, Cart $cart, Customer $customer, array $authorize, $token)
    {
        $logger = $module->getLogger();
        $returnedAmount = number_format((float) (isset($authorize['pt_transaction_amount']) ? $authorize['pt_transaction_amount'] : 0), 2, '.', '');
        $expectedAmount = (string) (isset($this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_AMOUNT})
            ? $this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_AMOUNT}
            : '');

        $converted = $module->getConvertedCartAmount($cart);
        $cartAmount = $converted['amount'];

        if (!isset($authorize['pt_transaction_amount']) || $authorize['pt_transaction_amount'] === '') {
            $logger->log('Missing returned amount', ['cart' => $cartAmount]);
            $this->stopAfterApproval($this->responseValue($authorize, ['pt_order_id']));
        }

        if ($expectedAmount !== '' && $returnedAmount !== $expectedAmount) {
            $logger->log('Amount mismatch', [
                'expected' => $expectedAmount,
                'returned' => $returnedAmount,
                'cart' => $cartAmount,
            ]);
            $this->stopAfterApproval($this->responseValue($authorize, ['pt_order_id']));
        }

        if ($expectedAmount === '' && $returnedAmount !== $cartAmount) {
            $logger->log('Amount mismatch (no cookie)', [
                'returned' => $returnedAmount,
                'cart' => $cartAmount,
            ]);
            $this->stopAfterApproval($this->responseValue($authorize, ['pt_order_id']));
        }

        $returnedAccount = trim((string) (isset($authorize['pt_gateway_account']) ? $authorize['pt_gateway_account'] : ''));
        $expectedAccount = (string) (isset($this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_ACCOUNT})
            ? $this->context->cookie->{Plugnpayss2::COOKIE_EXPECTED_ACCOUNT}
            : '');
        if ($expectedAccount === '') {
            $expectedAccount = trim((string) Configuration::get(Plugnpayss2::CONFIG_LOGIN));
        }
        if ($expectedAccount !== '' && $returnedAccount !== '' && strcasecmp($returnedAccount, $expectedAccount) !== 0) {
            $logger->log('Account mismatch', [
                'expected' => $expectedAccount,
                'returned' => $returnedAccount,
            ]);
            $this->stopAfterApproval($this->responseValue($authorize, ['pt_order_id']));
        }

        if ($cart->OrderExists()) {
            $existingOrderId = (int) Order::getOrderByCartId((int) $cart->id);
            if ($existingOrderId > 0) {
                $module->clearExpectedReturnCookies();
                Tools::redirect($this->context->link->getPageLink(
                    'order-confirmation',
                    true,
                    null,
                    http_build_query([
                        'id_cart' => (int) $cart->id,
                        'id_module' => (int) $module->id,
                        'id_order' => $existingOrderId,
                        'key' => (string) $customer->secure_key,
                    ])
                ));
            }
        }

        $authCode = $this->responseValue($authorize, ['pt_authorization_code']);
        $transactionId = $this->responseValue($authorize, ['pt_order_id']);
        $module->storeTransactionRow('success', $authorize, [], (int) $customer->id, (string) $token);

        $paymentMessage = 'Credit Card authorization via PlugnPay Smart Screens v2. AUTH: ' . $authCode
            . ' orderID: ' . $transactionId
            . ' (auth-only; settle in PlugnPay Admin)';
        if ($converted['currency'] !== '') {
            $paymentMessage .= ' (' . $returnedAmount . ' ' . $converted['currency'] . ')';
        }

        try {
            $module->validateOrder(
                (int) $cart->id,
                $module->getSuccessOrderStateId(),
                (float) $returnedAmount,
                $module->displayName,
                $paymentMessage,
                ['transaction_id' => $transactionId],
                (int) $cart->id_currency,
                false,
                (string) $customer->secure_key
            );
        } catch (Throwable $exception) {
            Logger::addLog(
                'PlugnPay SS2 approved cart ' . (int) $cart->id
                . ' but order creation raised an exception. Gateway orderID: ' . $transactionId
                . '. Error: ' . $exception->getMessage(),
                4
            );
            $this->stopAfterApproval($transactionId);
        }

        if (!(int) $module->currentOrder) {
            Logger::addLog(
                'PlugnPay SS2 approved cart but order creation failed for cart ' . (int) $cart->id
                . '. Gateway orderID: ' . $transactionId,
                4
            );
            $this->stopAfterApproval($transactionId);
        }

        $module->updateStoredOrderId((int) $module->currentOrder, $transactionId);
        $module->clearExpectedReturnCookies();

        Tools::redirect($this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            http_build_query([
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $module->id,
                'id_order' => (int) $module->currentOrder,
                'key' => (string) $customer->secure_key,
            ])
        ));
    }

    /**
     * @param array<string, mixed> $authorize
     */
    private function resolveCart(array $authorize, Plugnpayss2 $module)
    {
        $idCart = (int) Tools::getValue('id_cart');
        if ($idCart <= 0) {
            $idCart = (int) $module->extractCustomValue($authorize, 'tbcartid');
        }
        if ($idCart <= 0) {
            $idCart = (int) (isset($this->context->cookie->{Plugnpayss2::COOKIE_CART_ID})
                ? $this->context->cookie->{Plugnpayss2::COOKIE_CART_ID}
                : 0);
        }
        if ($idCart <= 0 && Validate::isLoadedObject($this->context->cart)) {
            $idCart = (int) $this->context->cart->id;
        }

        return new Cart($idCart);
    }

    /**
     * @param array<string, mixed> $response
     * @param string[] $keys
     */
    private function responseValue(array $response, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($response[$key])) {
                return Tools::substr(strip_tags((string) $response[$key]), 0, 100);
            }
        }

        return '';
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

    private function stopAfterApproval($transactionId)
    {
        header('HTTP/1.1 500 Internal Server Error');
        $message = $this->module->l(
            'Your payment was approved, but the order could not be created. Do not submit payment again. Contact the merchant.'
        );
        if ($transactionId !== '') {
            $message .= ' ' . $this->module->l('Gateway reference:') . ' ' . Tools::safeOutput($transactionId);
        }
        die(Tools::displayError($message));
    }
}
