<?php
/**
 * Checkout processor for PlugnPay Remote API.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

class PlugnpayapiValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        parent::postProcess();

        /** @var Plugnpayapi $module */
        $module = $this->module;
        $cart = $this->context->cart;
        $customer = $this->context->customer;

        if (!$module->active || !$module->isConfigured() || !$module->isSecureRequest()) {
            $this->redirectToPayment($this->module->l('This payment method is not available.'));
        }
        if (!Validate::isLoadedObject($cart) || !Validate::isLoadedObject($customer)) {
            $this->redirectToPayment($this->module->l('Your cart or customer session is no longer valid.'));
        }
        if ((int) $cart->id_customer !== (int) $customer->id || $cart->OrderExists()) {
            $this->redirectToPayment($this->module->l('This cart cannot be processed.'));
        }
        if (!$module->checkCurrency($cart)) {
            $this->redirectToPayment($this->module->l('This payment method is unavailable for the selected currency.'));
        }

        $submittedToken = (string) Tools::getValue('plugnpayapi_token');
        if (!hash_equals($module->getCheckoutToken($cart, $customer), $submittedToken)) {
            $this->redirectToPayment($this->module->l('The checkout security token is invalid. Please try again.'));
        }

        $invoiceAddress = new Address((int) $cart->id_address_invoice);
        $deliveryAddress = new Address((int) $cart->id_address_delivery);
        if (!Validate::isLoadedObject($invoiceAddress)) {
            $this->redirectToPayment($this->module->l('The billing address is invalid.'));
        }
        if (!Validate::isLoadedObject($deliveryAddress)) {
            $deliveryAddress = $invoiceAddress;
        }

        $cardNumber = preg_replace('/\D/', '', (string) Tools::getValue('plugnpayapi_card_number'));
        $cardOwner = trim((string) Tools::getValue('plugnpayapi_card_owner'));
        $expiryMonth = str_pad((string) (int) Tools::getValue('plugnpayapi_expiry_month'), 2, '0', STR_PAD_LEFT);
        $expiryYear = str_pad((string) (int) Tools::getValue('plugnpayapi_expiry_year'), 2, '0', STR_PAD_LEFT);
        $cardCvv = preg_replace('/\D/', '', (string) Tools::getValue('plugnpayapi_cvv'));

        $validationError = $this->validateCardData(
            $cardNumber,
            $cardOwner,
            $expiryMonth,
            $expiryYear,
            $cardCvv,
            Configuration::get(Plugnpayapi::CONFIG_USE_CVV) === 'True'
        );
        if ($validationError !== '') {
            $this->redirectToPayment($validationError);
        }

        $fields = $module->buildAuthorizeFields(
            $cart,
            $customer,
            $invoiceAddress,
            $deliveryAddress,
            $cardNumber,
            $expiryMonth . '/' . $expiryYear,
            $cardCvv,
            $cardOwner
        );

        $api = $module->getApiClient();
        $response = $api->authorize($fields);

        if ($api->getCommunicationErrorNumber() !== 0 || $api->getLastRawResponse() === '') {
            $this->redirectToPayment(
                $this->module->l('The payment gateway could not be reached. Please try again.')
            );
        }

        if (!$api->isApproved($response)) {
            $gatewayMessage = trim(strip_tags((string) (
                isset($response['MErrMsg']) ? $response['MErrMsg'] : ''
            )));
            $finalStatus = strtolower((string) (
                isset($response['FinalStatus']) ? $response['FinalStatus'] : ''
            ));
            $message = $finalStatus === 'fraud'
                ? $this->module->l('Your transaction was rejected. Please contact the merchant for assistance.')
                : $this->module->l('Your card was declined.');
            if ($gatewayMessage !== '') {
                $message .= ' ' . Tools::substr($gatewayMessage, 0, 250);
            }
            $this->redirectToPayment($message);
        }

        $expectedAmount = number_format((float) $cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $responseAmount = isset($response['card-amount'])
            ? $response['card-amount']
            : (isset($response['card_amount']) ? $response['card_amount'] : null);
        if ($responseAmount !== null
            && (float) $expectedAmount > 0
            && abs((float) $responseAmount - (float) $expectedAmount) > 0.009
        ) {
            Logger::addLog(
                'PlugnPay approved amount did not match cart ' . (int) $cart->id,
                3
            );
            $this->stopAfterApproval($this->responseValue($response, ['orderID', 'orderid']));
        }

        $transactionId = $this->responseValue($response, ['orderID', 'orderid']);
        $authCode = $this->responseValue($response, ['auth-code', 'auth_code']);
        $avsCode = $this->responseValue($response, ['avs-code', 'avs_code']);
        $cvvResponse = $this->responseValue($response, ['cvvresp']);
        $paymentMessage = 'PlugnPay Remote API'
            . ' AUTH: ' . $authCode
            . ' orderID: ' . $transactionId;
        if ($module->getAuthType() === 'authonly') {
            $paymentMessage .= ' (auth-only; settle in PlugnPay Admin)';
        }
        if ($avsCode !== '') {
            $paymentMessage .= ' AVS: ' . $avsCode;
        }
        if ($cvvResponse !== '') {
            $paymentMessage .= ' CVV: ' . $cvvResponse;
        }

        try {
            $module->validateOrder(
                (int) $cart->id,
                $module->getSuccessOrderStateId(),
                (float) $expectedAmount,
                $module->displayName,
                $paymentMessage,
                ['transaction_id' => $transactionId],
                (int) $cart->id_currency,
                false,
                (string) $customer->secure_key
            );
        } catch (Throwable $exception) {
            Logger::addLog(
                'PlugnPay approved cart ' . (int) $cart->id
                . ' but order creation raised an exception. Gateway orderID: ' . $transactionId
                . '. Error: ' . $exception->getMessage(),
                4
            );
            $this->stopAfterApproval($transactionId);
        }

        if (!(int) $module->currentOrder) {
            Logger::addLog(
                'PlugnPay approved cart but order creation failed for cart ' . (int) $cart->id
                . '. Gateway orderID: ' . $transactionId,
                4
            );
            $this->stopAfterApproval($transactionId);
        }

        $this->storeTransactionId((int) $module->currentOrder, $transactionId);

        $confirmationUrl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            http_build_query([
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $module->id,
                'id_order' => (int) $module->currentOrder,
                'key' => (string) $customer->secure_key,
            ])
        );
        Tools::redirect($confirmationUrl);
    }

    private function validateCardData($number, $owner, $month, $year, $cvv, $requireCvv)
    {
        if (Tools::strlen($owner) < 2) {
            return $this->module->l('Enter the cardholder name.');
        }
        if (Tools::strlen($number) < 12 || Tools::strlen($number) > 19 || !$this->passesLuhn($number)) {
            return $this->module->l('Enter a valid card number.');
        }

        $monthNumber = (int) $month;
        $yearNumber = 2000 + (int) $year;
        if ($monthNumber < 1 || $monthNumber > 12
            || $yearNumber < (int) date('Y')
            || ($yearNumber === (int) date('Y') && $monthNumber < (int) date('n'))
        ) {
            return $this->module->l('Enter a valid card expiration date.');
        }
        if ($requireCvv && !preg_match('/^\d{3,4}$/', $cvv)) {
            return $this->module->l('Enter a valid CVV.');
        }

        return '';
    }

    private function passesLuhn($number)
    {
        $sum = 0;
        $alternate = false;
        for ($position = strlen($number) - 1; $position >= 0; --$position) {
            $digit = (int) $number[$position];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }

    private function responseValue(array $response, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($response[$key])) {
                return Tools::substr(strip_tags((string) $response[$key]), 0, 100);
            }
        }

        return '';
    }

    private function storeTransactionId($orderId, $transactionId)
    {
        if ($transactionId === '') {
            return;
        }

        $order = new Order((int) $orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        foreach ($order->getOrderPayments() as $payment) {
            $payment->transaction_id = Tools::substr($transactionId, 0, 254);
            $payment->update();
            break;
        }
    }

    private function redirectToPayment($message)
    {
        $url = $this->context->link->getPageLink(
            'order',
            true,
            null,
            http_build_query([
                'step' => 3,
                'plugnpayapi_error' => Tools::substr(strip_tags((string) $message), 0, 300),
            ])
        );
        Tools::redirect($url);
        exit;
    }

    private function stopAfterApproval($transactionId)
    {
        header('HTTP/1.1 500 Internal Server Error');
        $message = $this->module->l(
            'Your payment was approved, but the order could not be created. Do not submit payment again. Contact the merchant.'
        );
        if ($transactionId !== '') {
            $message .= ' ' . $this->module->l('Gateway reference:') . ' '
                . Tools::safeOutput($transactionId);
        }
        die(Tools::displayError($message));
    }
}
