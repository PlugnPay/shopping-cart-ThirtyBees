<?php
/**
 * PlugnPay Remote API payment module for thirty bees 1.7.x.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PnPLogger.php';
require_once __DIR__ . '/classes/PnPApi.php';

class Plugnpayapi extends PaymentModule
{
    const CONFIG_LOGIN = 'PLUGNPAY_API_LOGIN';
    const CONFIG_PASSWORD = 'PLUGNPAY_API_PASSWORD';
    const CONFIG_PUBEMAIL = 'PLUGNPAY_API_PUBEMAIL';
    const CONFIG_AUTHTYPE = 'PLUGNPAY_API_AUTHTYPE';
    const CONFIG_EMAILCUST = 'PLUGNPAY_API_EMAILCUST';
    const CONFIG_USE_CVV = 'PLUGNPAY_API_USE_CVV';
    const CONFIG_ORDER_STATUS_ID = 'PLUGNPAY_API_ORDER_STATUS_ID';
    const CONFIG_DEBUGGING = 'PLUGNPAY_API_DEBUGGING';

    /** @var string[] */
    private static $configurationKeys = [
        self::CONFIG_LOGIN,
        self::CONFIG_PASSWORD,
        self::CONFIG_PUBEMAIL,
        self::CONFIG_AUTHTYPE,
        self::CONFIG_EMAILCUST,
        self::CONFIG_USE_CVV,
        self::CONFIG_ORDER_STATUS_ID,
        self::CONFIG_DEBUGGING,
    ];

    public function __construct()
    {
        $this->name = 'plugnpayapi';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.7';
        $this->author = 'PlugnPay Technologies';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->controllers = ['validation'];
        $this->tb_min_version = '1.7.0';
        $this->tb_versions_compliancy = '>= 1.7.0 < 1.8.0';

        parent::__construct();

        $this->displayName = $this->l('PlugnPay Remote API');
        $this->description = $this->l('Accept onsite credit card payments through the PlugnPay Remote API.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall PlugnPay Remote API?');

        if (!is_callable('curl_exec')) {
            $this->warning = $this->l('The PHP cURL extension is required.');
        } elseif (!$this->isConfigured()) {
            $this->warning = $this->l('Publisher Name and Remote Client Password must be configured.');
        }
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        return $this->registerHook('payment')
            && $this->registerHook('displayPayment')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('header')
            && $this->registerHook('displayHeader')
            && $this->installConfiguration()
            && $this->ensurePaymentRestrictions();
    }

    public function uninstall()
    {
        foreach (self::$configurationKeys as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $this->ensurePaymentRestrictions();

        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/plugnpayapi.css');
        }

        $output = '';
        if (Tools::isSubmit('submitPlugnpayapiModule')) {
            $output .= $this->saveConfiguration();
        }

        return $output . $this->renderInformation() . $this->renderForm();
    }

    /**
     * Classic checkout hook used by thirty bees Authorize.net AIM.
     * Always returns HTML. Do not return false or an array (PHP 8 TypeError).
     *
     * @param array $params
     *
     * @return string
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return '';
        }

        try {
            $currencyId = isset($this->context->cookie->id_currency)
                ? (int) $this->context->cookie->id_currency
                : 0;
            $currency = Currency::getCurrencyInstance($currencyId);
            if (!Validate::isLoadedObject($currency) && isset($this->context->currency)) {
                $currency = $this->context->currency;
            }
            if (!Validate::isLoadedObject($currency)) {
                return $this->getPaymentFallbackHtml();
            }

            $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
            $customer = $this->resolveCheckoutCustomer($cart);
            $cardOwner = '';
            if (Validate::isLoadedObject($customer)) {
                $cardOwner = trim($customer->firstname . ' ' . $customer->lastname);
            }

            $months = [];
            for ($month = 1; $month <= 12; ++$month) {
                $months[] = sprintf('%02d', $month);
            }

            $years = [];
            $currentYear = (int) date('Y');
            for ($offset = 0; $offset <= 15; ++$offset) {
                $years[] = substr((string) ($currentYear + $offset), -2);
            }

            $checkoutToken = '';
            if ($cart instanceof Cart && Validate::isLoadedObject($cart)) {
                $checkoutToken = $this->getCheckoutToken($cart);
            }

            $vars = [
                'plugnpayapi_action' => $this->getValidationUrl(),
                'plugnpayapi_card_owner' => $cardOwner,
                'plugnpayapi_checkout_token' => $checkoutToken,
                'plugnpayapi_error' => (string) Tools::getValue('plugnpayapi_error'),
                'plugnpayapi_months' => $months,
                'plugnpayapi_years' => $years,
                'plugnpayapi_use_cvv' => Configuration::get(self::CONFIG_USE_CVV) === 'True' ? 1 : 0,
                'plugnpayapi_secure' => $this->isSecureRequest() ? 1 : 0,
            ];

            // AIM assigns on context Smarty; bankwire uses the module Smarty_Data object.
            $this->context->smarty->assign($vars);
            $this->smarty->assign($vars);

            $html = $this->display(__FILE__, 'views/templates/hook/payment.tpl');

            return is_string($html) && $html !== ''
                ? $html
                : $this->getPaymentFallbackHtml();
        } catch (Throwable $exception) {
            $this->logShopMessage(
                'PlugnPay Remote API payment hook failed: ' . $exception->getMessage(),
                3
            );

            return $this->getPaymentFallbackHtml();
        }
    }

    /**
     * Canonical thirty bees 1.7 hook name. Same HTML as hookPayment().
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPayment($params)
    {
        return $this->hookPayment($params);
    }

    /**
     * @return string
     */
    public function hookDisplayHeader()
    {
        try {
            $this->ensureCurrentCheckoutRestrictions();
            if (isset($this->context->controller)) {
                $this->context->controller->addCSS($this->_path . 'views/css/plugnpayapi.css');
            }
        } catch (Throwable $exception) {
            $this->logShopMessage(
                'PlugnPay Remote API header hook failed: ' . $exception->getMessage(),
                3
            );
        }

        return '';
    }

    /**
     * @return string
     */
    public function hookHeader()
    {
        return $this->hookDisplayHeader();
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        if (empty($params['objOrder']) || $params['objOrder']->module !== $this->name) {
            return '';
        }

        try {
            $order = $params['objOrder'];
            $this->smarty->assign([
                'plugnpayapi_status' => (int) $order->getCurrentState() === (int) Configuration::get('PS_OS_ERROR')
                    ? 'failed'
                    : 'ok',
                'plugnpayapi_order_reference' => (string) $order->reference,
            ]);

            return $this->renderHookTemplate('order_confirmation.tpl');
        } catch (Throwable $exception) {
            $this->logShopMessage(
                'PlugnPay Remote API payment return failed: ' . $exception->getMessage(),
                3
            );

            return '';
        }
    }

    /**
     * Backward-compatible alias for stores that still invoke orderConfirmation.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookOrderConfirmation($params)
    {
        return $this->hookPaymentReturn($params);
    }

    public function isConfigured()
    {
        return trim((string) Configuration::get(self::CONFIG_LOGIN)) !== ''
            && (string) Configuration::get(self::CONFIG_PASSWORD) !== '';
    }

    public function isSecureRequest()
    {
        return $this->requestUsesHttps();
    }

    public function checkCurrency($cart)
    {
        if (!Validate::isLoadedObject($cart)) {
            return false;
        }

        $currency = new Currency((int) $cart->id_currency);
        $allowedCurrencies = $this->getCurrency((int) $cart->id_currency);
        if (!Validate::isLoadedObject($currency)) {
            return false;
        }
        if (!is_array($allowedCurrencies) || !$allowedCurrencies) {
            return true;
        }

        foreach ($allowedCurrencies as $allowedCurrency) {
            if ((int) $currency->id === (int) $allowedCurrency['id_currency']) {
                return true;
            }
        }

        return false;
    }

    public function getCheckoutToken(Cart $cart)
    {
        return hash_hmac('sha256', $this->name . ':' . (int) $cart->id, (string) $cart->secure_key);
    }

    /**
     * @param Cart $cart
     *
     * @return Customer
     */
    public function resolveCheckoutCustomer($cart)
    {
        $customer = $this->context->customer;
        if (Validate::isLoadedObject($customer)) {
            return $customer;
        }

        if (Validate::isLoadedObject($cart) && (int) $cart->id_customer > 0) {
            return new Customer((int) $cart->id_customer);
        }

        return new Customer();
    }

    public function getAuthType()
    {
        return Configuration::get(self::CONFIG_AUTHTYPE) === 'authpostauth'
            ? 'authpostauth'
            : 'authonly';
    }

    public function getSuccessOrderStateId()
    {
        if ($this->getAuthType() === 'authonly') {
            $stateId = (int) Configuration::get('PS_OS_PREPARATION');
        } else {
            $stateId = (int) Configuration::get(self::CONFIG_ORDER_STATUS_ID);
        }

        if ($stateId <= 0) {
            $stateId = (int) Configuration::get('PS_OS_PAYMENT');
        }
        if ($stateId <= 0) {
            $stateId = (int) Configuration::get('PS_OS_PREPARATION');
        }

        return $stateId;
    }

    public function getApiClient()
    {
        $logger = new PnPLogger(
            defined('_PS_ROOT_DIR_') ? _PS_ROOT_DIR_ . '/log' : sys_get_temp_dir(),
            Configuration::get(self::CONFIG_DEBUGGING) === 'Log File'
        );

        return new PnPApi(
            (string) Configuration::get(self::CONFIG_LOGIN),
            (string) Configuration::get(self::CONFIG_PASSWORD),
            $logger
        );
    }

    /**
     * @return array<string, string>
     */
    public function buildAuthorizeFields(
        Cart $cart,
        Customer $customer,
        Address $invoiceAddress,
        Address $deliveryAddress,
        $cardNumber,
        $cardExp,
        $cardCvv,
        $cardName
    ) {
        $amount = number_format((float) $cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $billingState = $this->getStateCode((int) $invoiceAddress->id_state);
        $deliveryState = $this->getStateCode((int) $deliveryAddress->id_state);
        $billingCountry = $this->getCountryCode((int) $invoiceAddress->id_country);
        $deliveryCountry = $this->getCountryCode((int) $deliveryAddress->id_country);

        $fields = [
            'mode' => 'auth',
            'paymethod' => 'credit',
            'authtype' => $this->getAuthType(),
            'easycart' => '1',
            'shipinfo' => '1',
            'orderID' => (string) $cart->id,
            'acct_code' => (string) $cart->id,
            'card-amount' => $amount,
            'card-number' => preg_replace('/\D/', '', (string) $cardNumber),
            'card-exp' => (string) $cardExp,
            'card-name' => trim((string) $cardName),
            'card-company' => (string) $invoiceAddress->company,
            'card-address1' => (string) $invoiceAddress->address1,
            'card-address2' => (string) $invoiceAddress->address2,
            'card-city' => (string) $invoiceAddress->city,
            'card-state' => $billingState,
            'card-zip' => (string) $invoiceAddress->postcode,
            'card-country' => $billingCountry,
            'phone' => (string) ($invoiceAddress->phone ?: $invoiceAddress->phone_mobile),
            'email' => (string) $customer->email,
            'shipname' => trim($deliveryAddress->firstname . ' ' . $deliveryAddress->lastname),
            'address1' => (string) $deliveryAddress->address1,
            'address2' => (string) $deliveryAddress->address2,
            'city' => (string) $deliveryAddress->city,
            'state' => $deliveryState,
            'zip' => (string) $deliveryAddress->postcode,
            'country' => $deliveryCountry,
            'shipping' => number_format((float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 2, '.', ''),
            'tax' => number_format(
                (float) $cart->getOrderTotal(true, Cart::BOTH)
                - (float) $cart->getOrderTotal(false, Cart::BOTH),
                2,
                '.',
                ''
            ),
            'ipaddress' => (string) Tools::getRemoteAddr(),
            'dontsndmail' => Configuration::get(self::CONFIG_EMAILCUST) === 'yes' ? 'yes' : 'no',
        ];

        $publisherEmail = trim((string) Configuration::get(self::CONFIG_PUBEMAIL));
        if ($publisherEmail !== '') {
            $fields['publisher-email'] = $publisherEmail;
            $fields['notify-email'] = $publisherEmail;
        }

        if (Configuration::get(self::CONFIG_USE_CVV) === 'True' && $cardCvv !== '') {
            $fields['card-cvv'] = (string) $cardCvv;
        }

        if ((float) $amount <= 0) {
            $fields['mode'] = 'checkcard';
            unset($fields['card-amount']);
        }

        $products = $cart->getProducts();
        if (is_array($products)) {
            $itemNumber = 1;
            foreach ($products as $product) {
                $fields['item' . $itemNumber] = (string) (isset($product['reference']) ? $product['reference'] : '');
                $fields['cost' . $itemNumber] = number_format(
                    (float) (isset($product['price_wt']) ? $product['price_wt'] : $product['price']),
                    2,
                    '.',
                    ''
                );
                $fields['quantity' . $itemNumber] = (string) (
                    isset($product['cart_quantity']) ? $product['cart_quantity'] : $product['quantity']
                );
                $fields['description' . $itemNumber] = Tools::substr(
                    strip_tags((string) $product['name']),
                    0,
                    255
                );
                ++$itemNumber;
            }
        }

        return $fields;
    }

    /**
     * Restore currency/country/carrier/group rows so displayPayment is not filtered out.
     *
     * @return bool
     */
    public function ensurePaymentRestrictions()
    {
        $ok = true;

        try {
            $this->unregisterHook('displayPaymentEU');
        } catch (Throwable $ignored) {
        }

        $ok = $this->registerHook('payment')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('header')
            && $ok;

        if (method_exists($this, 'addCheckboxCurrencyRestrictionsForModule')) {
            $ok = $this->addCheckboxCurrencyRestrictionsForModule() && $ok;
        }
        if (method_exists($this, 'addCheckboxCountryRestrictionsForModule')) {
            $ok = $this->addCheckboxCountryRestrictionsForModule() && $ok;
        }
        if (method_exists($this, 'addCheckboxCarrierRestrictionsForModule')) {
            $ok = $this->addCheckboxCarrierRestrictionsForModule() && $ok;
        }

        try {
            $hasGroup = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'module_group` WHERE `id_module` = ' . (int) $this->id
            );
            if ($hasGroup === 0 && method_exists('Group', 'addRestrictionsForModule')) {
                $ok = Group::addRestrictionsForModule((int) $this->id, Shop::getShops(true, null, true)) && $ok;
            }
        } catch (Throwable $ignored) {
        }

        return $ok;
    }

    /**
     * Header runs before displayPayment. Insert the current cart's restriction
     * rows so thirty bees does not hide this module from HOOK_PAYMENT.
     */
    private function ensureCurrentCheckoutRestrictions()
    {
        $moduleId = (int) $this->id;
        $shopId = isset($this->context->shop) ? (int) $this->context->shop->id : 0;
        if ($moduleId <= 0 || $shopId <= 0) {
            return;
        }

        $db = Db::getInstance();

        if (isset($this->context->currency) && Validate::isLoadedObject($this->context->currency)) {
            $db->insert(
                'module_currency',
                [
                    'id_module' => $moduleId,
                    'id_shop' => $shopId,
                    'id_currency' => (int) $this->context->currency->id,
                ],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }

        if (isset($this->context->country) && Validate::isLoadedObject($this->context->country)) {
            $db->insert(
                'module_country',
                [
                    'id_module' => $moduleId,
                    'id_shop' => $shopId,
                    'id_country' => (int) $this->context->country->id,
                ],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }

        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)
            && (int) $this->context->cart->id_carrier > 0
        ) {
            $carrier = new Carrier((int) $this->context->cart->id_carrier);
            if (Validate::isLoadedObject($carrier) && (int) $carrier->id_reference > 0) {
                $db->insert(
                    'module_carrier',
                    [
                        'id_module' => $moduleId,
                        'id_shop' => $shopId,
                        'id_reference' => (int) $carrier->id_reference,
                    ],
                    false,
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }

        if (isset($this->context->customer) && method_exists($this->context->customer, 'getGroups')) {
            foreach ($this->context->customer->getGroups() as $groupId) {
                $db->insert(
                    'module_group',
                    [
                        'id_module' => $moduleId,
                        'id_shop' => $shopId,
                        'id_group' => (int) $groupId,
                    ],
                    false,
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }
    }

    /**
     * Relative module front-controller URL, same idea as AIM posting to {$module_dir}validation.php.
     *
     * @return string
     */
    private function getValidationUrl()
    {
        try {
            if (isset($this->context->link)) {
                $ssl = (bool) Configuration::get('PS_SSL_ENABLED');

                return $this->context->link->getModuleLink($this->name, 'validation', [], $ssl);
            }
        } catch (Throwable $ignored) {
        }

        return __PS_BASE_URI__ . 'index.php?fc=module&module=' . $this->name . '&controller=validation';
    }

    /**
     * Keep HOOK_PAYMENT non-empty if the Smarty template cannot be rendered.
     *
     * @return string
     */
    private function getPaymentFallbackHtml()
    {
        return '<div class="plugnpayapi-wrapper"><p class="plugnpayapi-title">'
            . htmlspecialchars($this->l('Secured card payment'), ENT_QUOTES, 'UTF-8')
            . '</p></div>';
    }

    /**
     * @param string $template
     *
     * @return string
     */
    private function renderHookTemplate($template)
    {
        try {
            $html = $this->display(__FILE__, $template);

            return is_string($html) ? $html : '';
        } catch (Throwable $exception) {
            $this->logShopMessage(
                'PlugnPay Remote API template ' . $template . ' failed: ' . $exception->getMessage(),
                3
            );

            return '';
        }
    }

    /**
     * @param string $message
     * @param int $severity
     */
    private function logShopMessage($message, $severity = 3)
    {
        try {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog((string) $message, (int) $severity);
            }
        } catch (Throwable $ignored) {
        }
    }

    private function canDisplayForCart($cart)
    {
        return $this->active
            && $this->isConfigured()
            && Validate::isLoadedObject($cart)
            && $this->checkCurrency($cart);
    }

    private function requestUsesHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        return method_exists('Tools', 'usingSecureMode') && Tools::usingSecureMode();
    }

    private function getStateCode($stateId)
    {
        if ($stateId <= 0) {
            return '';
        }

        $state = new State($stateId);

        return Validate::isLoadedObject($state) ? (string) $state->iso_code : '';
    }

    private function getCountryCode($countryId)
    {
        if ($countryId <= 0) {
            return '';
        }

        $country = new Country($countryId);

        return Validate::isLoadedObject($country) ? (string) $country->iso_code : '';
    }

    private function installConfiguration()
    {
        return Configuration::updateValue(self::CONFIG_LOGIN, '')
            && Configuration::updateValue(self::CONFIG_PASSWORD, '')
            && Configuration::updateValue(self::CONFIG_PUBEMAIL, '')
            && Configuration::updateValue(self::CONFIG_AUTHTYPE, 'authonly')
            && Configuration::updateValue(self::CONFIG_EMAILCUST, 'yes')
            && Configuration::updateValue(self::CONFIG_USE_CVV, 'True')
            && Configuration::updateValue(
                self::CONFIG_ORDER_STATUS_ID,
                (int) Configuration::get('PS_OS_PAYMENT')
            )
            && Configuration::updateValue(self::CONFIG_DEBUGGING, 'Off');
    }

    private function saveConfiguration()
    {
        $login = trim((string) Tools::getValue(self::CONFIG_LOGIN));
        $password = (string) Tools::getValue(self::CONFIG_PASSWORD);
        $publisherEmail = trim((string) Tools::getValue(self::CONFIG_PUBEMAIL));

        if ($login === '') {
            return $this->displayError($this->l('Publisher Name is required.'));
        }
        if ($password === '' && Configuration::get(self::CONFIG_PASSWORD) === '') {
            return $this->displayError($this->l('Remote Client Password is required.'));
        }
        if ($publisherEmail !== '' && !Validate::isEmail($publisherEmail)) {
            return $this->displayError($this->l('Publisher Email is invalid.'));
        }

        $authType = (string) Tools::getValue(self::CONFIG_AUTHTYPE);
        $emailCustomer = (string) Tools::getValue(self::CONFIG_EMAILCUST);
        $useCvv = (string) Tools::getValue(self::CONFIG_USE_CVV);
        $debugging = (string) Tools::getValue(self::CONFIG_DEBUGGING);
        $orderStatusId = (int) Tools::getValue(self::CONFIG_ORDER_STATUS_ID);

        if (!in_array($authType, ['authonly', 'authpostauth'], true)) {
            $authType = 'authonly';
        }
        if (!in_array($emailCustomer, ['yes', 'no'], true)) {
            $emailCustomer = 'yes';
        }
        if (!in_array($useCvv, ['True', 'False'], true)) {
            $useCvv = 'True';
        }
        if (!in_array($debugging, ['Off', 'Log File'], true)) {
            $debugging = 'Off';
        }

        Configuration::updateValue(self::CONFIG_LOGIN, $login);
        if ($password !== '') {
            Configuration::updateValue(self::CONFIG_PASSWORD, $password);
        }
        Configuration::updateValue(self::CONFIG_PUBEMAIL, $publisherEmail);
        Configuration::updateValue(self::CONFIG_AUTHTYPE, $authType);
        Configuration::updateValue(self::CONFIG_EMAILCUST, $emailCustomer);
        Configuration::updateValue(self::CONFIG_USE_CVV, $useCvv);
        Configuration::updateValue(self::CONFIG_ORDER_STATUS_ID, $orderStatusId);
        Configuration::updateValue(self::CONFIG_DEBUGGING, $debugging);

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    private function renderInformation()
    {
        $logoUrl = $this->_path . 'views/img/plugnpay_logo.png';

        return '<div class="panel plugnpayapi-admin-brand">'
            . '<div class="plugnpayapi-admin-logo">'
            . '<img src="' . Tools::safeOutput($logoUrl) . '" alt="PlugnPay" />'
            . '</div>'
            . '<p>' . $this->l('Accept credit cards via PlugnPay Remote API. Card data is collected on your storefront and posted from your server to pnpremote.cgi.') . '</p>'
            . '<ul>'
            . '<li>' . $this->l('Requires store HTTPS (production only). Before enabling SSL on all pages, set the shop SSL URL to https:// in Preferences > SEO & URLs.') . '</li>'
            . '<li>' . $this->l('Requires PHP cURL with SSL.') . '</li>'
            . '<li>' . $this->l('Use your PlugnPay publisher-name and Remote Client Password (not your admin login password).') . '</li>'
            . '<li>' . $this->l('Capture, void, and refund are done in PlugnPay Merchant Admin — not from thirty bees.') . '</li>'
            . '</ul>'
            . '<p>'
            . '<a class="btn btn-default" rel="noreferrer noopener" target="_blank" href="https://pay1.plugnpay.com/admin/">'
            . $this->l('PlugnPay Merchant Admin')
            . '</a> '
            . '<a class="btn btn-default" rel="noreferrer noopener" target="_blank" href="https://docs.plugnpay.com/">'
            . $this->l('API Documentation')
            . '</a>'
            . '</p>'
            . '</div>'
            . '<div class="alert alert-warning">'
            . '<strong>' . $this->l('PCI notice:') . '</strong> '
            . $this->l('This module collects cardholder data on your server. Use HTTPS and maintain the appropriate PCI DSS compliance. For hosted (lower PCI) checkout, use PlugnPay Smart Screens v2.')
            . '</div>';
    }

    private function renderForm()
    {
        $orderStates = OrderState::getOrderStates((int) $this->context->language->id);
        $orderStatusOptions = [];
        foreach ($orderStates as $orderState) {
            $orderStatusOptions[] = [
                'id' => (int) $orderState['id_order_state'],
                'name' => $orderState['name'],
            ];
        }

        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $this->l('PlugnPay Remote API Settings'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Publisher Name'),
                        'name' => self::CONFIG_LOGIN,
                        'required' => true,
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('Remote Client Password'),
                        'name' => self::CONFIG_PASSWORD,
                        'desc' => $this->l('Leave blank to keep the currently saved password.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Publisher Email'),
                        'name' => self::CONFIG_PUBEMAIL,
                    ],
                    $this->selectField(
                        $this->l('Authorization Type'),
                        self::CONFIG_AUTHTYPE,
                        ['authonly', 'authpostauth'],
                        $this->l('authonly requires settlement in PlugnPay Admin; authpostauth performs a sale.')
                    ),
                    $this->selectField(
                        $this->l('Prevent Gateway Customer Email'),
                        self::CONFIG_EMAILCUST,
                        ['yes', 'no']
                    ),
                    $this->selectField(
                        $this->l('Request CVV Number'),
                        self::CONFIG_USE_CVV,
                        ['True', 'False']
                    ),
                    [
                        'type' => 'select',
                        'label' => $this->l('Completed Order Status'),
                        'name' => self::CONFIG_ORDER_STATUS_ID,
                        'desc' => $this->l('Used for authpostauth. authonly uses Preparation/Pending.'),
                        'options' => [
                            'query' => $orderStatusOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    $this->selectField(
                        $this->l('Debug Logging'),
                        self::CONFIG_DEBUGGING,
                        ['Off', 'Log File'],
                        $this->l('Logs are sanitized and never contain full PAN, CVV, or the password.')
                    ),
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPlugnpayapiModule';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                self::CONFIG_LOGIN => Configuration::get(self::CONFIG_LOGIN),
                self::CONFIG_PASSWORD => '',
                self::CONFIG_PUBEMAIL => Configuration::get(self::CONFIG_PUBEMAIL),
                self::CONFIG_AUTHTYPE => Configuration::get(self::CONFIG_AUTHTYPE),
                self::CONFIG_EMAILCUST => Configuration::get(self::CONFIG_EMAILCUST),
                self::CONFIG_USE_CVV => Configuration::get(self::CONFIG_USE_CVV),
                self::CONFIG_ORDER_STATUS_ID => Configuration::get(self::CONFIG_ORDER_STATUS_ID),
                self::CONFIG_DEBUGGING => Configuration::get(self::CONFIG_DEBUGGING),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    private function selectField($label, $name, array $values, $description = '')
    {
        $options = [];
        foreach ($values as $value) {
            $options[] = ['id' => $value, 'name' => $value];
        }

        return [
            'type' => 'select',
            'label' => $label,
            'name' => $name,
            'desc' => $description,
            'options' => ['query' => $options, 'id' => 'id', 'name' => 'name'],
        ];
    }
}
