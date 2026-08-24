<?php
/**
 * PlugnPay Smart Screens v2 payment module for thirty bees 1.7.x.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PnPSs2Logger.php';

class Plugnpayss2 extends PaymentModule
{
    const CONFIG_LOGIN = 'PLUGNPAY_SS2_LOGIN';
    const CONFIG_CURRENCY = 'PLUGNPAY_SS2_CURRENCY';
    const CONFIG_STORE_DATA = 'PLUGNPAY_SS2_STORE_DATA';
    const CONFIG_DEBUGGING = 'PLUGNPAY_SS2_DEBUGGING';

    const GATEWAY_URL = 'https://pay1.plugnpay.com/pay/';

    const COOKIE_EXPECTED_AMOUNT = 'plugnpay_ss2_expected_amount';
    const COOKIE_EXPECTED_ACCOUNT = 'plugnpay_ss2_expected_account';
    const COOKIE_EXPECTED_TOKEN = 'plugnpay_ss2_expected_token';
    const COOKIE_CART_ID = 'plugnpay_ss2_cart_id';
    const COOKIE_LAST_ROW_ID = 'plugnpay_ss2_last_row_id';

    /** @var string[] */
    private static $configurationKeys = [
        self::CONFIG_LOGIN,
        self::CONFIG_CURRENCY,
        self::CONFIG_STORE_DATA,
        self::CONFIG_DEBUGGING,
    ];

    /** @var string[] */
    private static $supportedCurrencies = ['USD', 'CAD', 'GBP', 'EUR', 'AUD', 'NZD'];

    public function __construct()
    {
        $this->name = 'plugnpayss2';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'PlugnPay Technologies';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->controllers = ['redirect', 'validation'];
        $this->tb_min_version = '1.7.0';
        $this->tb_versions_compliancy = '>= 1.7.0 < 1.8.0';

        parent::__construct();

        $this->displayName = $this->l('PlugnPay Smart Screens v2');
        $this->description = $this->l('Accept credit cards via PlugnPay Smart Screens v2 hosted checkout (authorization-only).');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall PlugnPay Smart Screens v2?');

        if (!$this->isConfigured()) {
            $this->warning = $this->l('Gateway Account must be configured.');
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('payment')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('header')
            && $this->installConfiguration()
            && $this->installDb();
    }

    public function uninstall()
    {
        foreach (self::$configurationKeys as $key) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallDb() && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitPlugnpayss2Module')) {
            $output .= $this->saveConfiguration();
        }

        return $output . $this->renderInformation() . $this->renderForm();
    }

    /**
     * @param array $params
     *
     * @return string|false
     */
    public function hookPayment($params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
        if (!$this->canDisplayForCart($cart)) {
            return false;
        }

        $this->context->smarty->assign([
            'plugnpayss2_redirect_url' => $this->context->link->getModuleLink($this->name, 'redirect', [], true),
            'plugnpayss2_error' => (string) Tools::getValue('plugnpayss2_error'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookHeader()
    {
        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/plugnpayss2.css');
        }
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookOrderConfirmation($params)
    {
        if (empty($params['objOrder']) || $params['objOrder']->module !== $this->name) {
            return '';
        }

        $order = $params['objOrder'];
        $this->context->smarty->assign([
            'plugnpayss2_status' => (int) $order->getCurrentState() === (int) Configuration::get('PS_OS_ERROR')
                ? 'failed'
                : 'ok',
            'plugnpayss2_order_reference' => (string) $order->reference,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/order_confirmation.tpl');
    }

    public function isConfigured()
    {
        return trim((string) Configuration::get(self::CONFIG_LOGIN)) !== '';
    }

    public function isSecureRequest()
    {
        return (bool) Configuration::get('PS_SSL_ENABLED')
            && (
                (method_exists('Tools', 'usingSecureMode') && Tools::usingSecureMode())
                || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            );
    }

    public function checkCurrency(Cart $cart)
    {
        $currency = new Currency((int) $cart->id_currency);
        $allowedCurrencies = $this->getCurrency((int) $cart->id_currency);
        if (!Validate::isLoadedObject($currency) || !is_array($allowedCurrencies)) {
            return false;
        }

        foreach ($allowedCurrencies as $allowedCurrency) {
            if ((int) $currency->id === (int) $allowedCurrency['id_currency']) {
                return true;
            }
        }

        return false;
    }

    public function getGatewayCurrencyIso()
    {
        $currency = strtoupper(trim((string) Configuration::get(self::CONFIG_CURRENCY)));

        return in_array($currency, self::$supportedCurrencies, true) ? $currency : 'USD';
    }

    public function getSuccessOrderStateId()
    {
        return (int) Configuration::get('PS_OS_PREPARATION');
    }

    public function getLogger()
    {
        return new PnPSs2Logger(
            defined('_PS_ROOT_DIR_') ? _PS_ROOT_DIR_ . '/log' : sys_get_temp_dir(),
            Configuration::get(self::CONFIG_DEBUGGING) === 'Log File'
        );
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function getConvertedCartAmount(Cart $cart)
    {
        $cartCurrency = new Currency((int) $cart->id_currency);
        $gatewayCurrencyIso = $this->getGatewayCurrencyIso();
        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);

        if (Validate::isLoadedObject($cartCurrency)
            && strtoupper((string) $cartCurrency->iso_code) !== $gatewayCurrencyIso
        ) {
            $gatewayCurrency = Currency::getCurrencyInstance(
                (int) Currency::getIdByIsoCode($gatewayCurrencyIso)
            );
            if (Validate::isLoadedObject($gatewayCurrency)) {
                $amount = (float) Tools::convertPriceFull($amount, $cartCurrency, $gatewayCurrency);
            }
        }

        return [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $gatewayCurrencyIso,
        ];
    }

    /**
     * @return array{fields: array<string, string>, token: string, amount: string, currency: string}
     */
    public function buildHostedFields(Cart $cart, Customer $customer, Address $invoiceAddress)
    {
        $converted = $this->getConvertedCartAmount($cart);
        $gatewayCurrencyIso = $converted['currency'];
        $amountFormatted = $converted['amount'];
        $billingState = $this->getStateCode((int) $invoiceAddress->id_state);
        $billingCountry = $this->getCountryCode((int) $invoiceAddress->id_country);
        $token = hash(
            'sha256',
            (int) $cart->id . '|' . (string) $customer->secure_key . '|' . $amountFormatted . '|' . microtime(true)
        );

        $successUrl = $this->context->link->getModuleLink(
            $this->name,
            'validation',
            [
                'id_cart' => (int) $cart->id,
                'key' => (string) $customer->secure_key,
                'pnp_token' => $token,
            ],
            true
        );

        $fields = [
            'pt_gateway_account' => trim((string) Configuration::get(self::CONFIG_LOGIN)),
            'pt_transaction_amount' => $amountFormatted,
            'pt_currency' => $gatewayCurrencyIso,
            'pt_currency_code' => $gatewayCurrencyIso,
            'pb_post_auth' => 'no',
            'pt_account_code_1' => (string) $cart->id,
            'pt_billing_company' => (string) $invoiceAddress->company,
            'pt_payment_name' => trim($customer->firstname . ' ' . $customer->lastname),
            'pt_billing_address_1' => (string) $invoiceAddress->address1,
            'pt_billing_city' => (string) $invoiceAddress->city,
            'pt_billing_state' => $billingState,
            'pt_billing_postal_code' => (string) $invoiceAddress->postcode,
            'pt_billing_country' => $billingCountry,
            'pt_billing_phone_number' => (string) ($invoiceAddress->phone ?: $invoiceAddress->phone_mobile),
            'pt_billing_email_address' => (string) $customer->email,
            'pt_client_identifier' => 'ThirtyBees_SS2',
            'pt_ip_address' => (string) Tools::getRemoteAddr(),
            'pb_transition_type' => 'post',
            'pb_success_url' => $successUrl,
            'pd_collect_shipping_information' => 'no',
            'pd_display_items' => 'no',
            'pt_custom_name_1' => 'tbcartid',
            'pt_custom_value_1' => (string) $cart->id,
            'pt_custom_name_2' => 'tbtoken',
            'pt_custom_value_2' => $token,
        ];

        return [
            'fields' => $fields,
            'token' => $token,
            'amount' => $amountFormatted,
            'currency' => $gatewayCurrencyIso,
        ];
    }

    /**
     * @param array<string, string> $submitFields
     */
    public function storeExpectedReturn(Cart $cart, $amount, $token, array $submitFields)
    {
        $cookie = $this->context->cookie;
        $cookie->{self::COOKIE_EXPECTED_AMOUNT} = (string) $amount;
        $cookie->{self::COOKIE_EXPECTED_ACCOUNT} = isset($submitFields['pt_gateway_account'])
            ? $submitFields['pt_gateway_account']
            : '';
        $cookie->{self::COOKIE_EXPECTED_TOKEN} = (string) $token;
        $cookie->{self::COOKIE_CART_ID} = (string) $cart->id;
        $cookie->write();

        $this->getLogger()->log('Submit-Data', $submitFields);
        $this->storeTransactionRow('submitted', [], $submitFields, (int) $cart->id_customer, (string) $token);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function extractCustomValue(array $response, $name)
    {
        $name = strtolower(trim((string) $name));
        for ($index = 1; $index <= 10; ++$index) {
            $nameKey = 'pt_custom_name_' . $index;
            $valueKey = 'pt_custom_value_' . $index;
            if (!isset($response[$nameKey])) {
                continue;
            }
            if (strtolower(trim((string) $response[$nameKey])) === $name) {
                return trim((string) (isset($response[$valueKey]) ? $response[$valueKey] : ''));
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $authorize
     * @param array<string, mixed> $submitFields
     */
    public function storeTransactionRow(
        $responseCode,
        array $authorize,
        array $submitFields = [],
        $customerId = 0,
        $sessionToken = ''
    ) {
        if (Configuration::get(self::CONFIG_STORE_DATA) !== 'True') {
            return;
        }

        $table = _DB_PREFIX_ . 'plugnpay_ss2';
        if (!$this->tableExists($table)) {
            return;
        }

        $logger = $this->getLogger();
        $sent = $logger->sanitize($submitFields);
        $received = $logger->sanitize($authorize);

        if ($responseCode !== 'submitted' && $sessionToken !== '') {
            $existingId = (int) Db::getInstance()->getValue(
                'SELECT `id` FROM `' . bqSQL($table) . '`'
                . ' WHERE `session_id` = \'' . pSQL($sessionToken) . '\''
                . ' AND `response_code` = \'submitted\''
                . ' ORDER BY `id` DESC'
            );
            if ($existingId > 0) {
                $update = [
                    'response_code' => pSQL(Tools::substr((string) $responseCode, 0, 32)),
                    'response_text' => pSQL(Tools::substr((string) (isset($authorize['pi_error_message']) ? $authorize['pi_error_message'] : ''), 0, 255)),
                    'authorization_type' => pSQL(Tools::substr((string) (isset($authorize['pt_card_type']) ? $authorize['pt_card_type'] : ''), 0, 25)),
                    'transaction_id' => pSQL(Tools::substr((string) (isset($authorize['pt_order_id']) ? $authorize['pt_order_id'] : ''), 0, 255)),
                    'received' => pSQL(print_r($received, true), true),
                    'time' => pSQL(date('F j, Y, g:i a')),
                ];
                if ((int) $customerId > 0) {
                    $update['customer_id'] = pSQL((string) $customerId);
                }
                Db::getInstance()->update('plugnpay_ss2', $update, 'id = ' . (int) $existingId);
                $this->context->cookie->{self::COOKIE_LAST_ROW_ID} = (string) $existingId;
                $this->context->cookie->write();

                return;
            }
        }

        $inserted = Db::getInstance()->insert('plugnpay_ss2', [
            'customer_id' => pSQL((string) $customerId),
            'order_id' => '',
            'response_code' => pSQL(Tools::substr((string) $responseCode, 0, 32)),
            'response_text' => pSQL(Tools::substr((string) (isset($authorize['pi_error_message']) ? $authorize['pi_error_message'] : ''), 0, 255)),
            'authorization_type' => pSQL(Tools::substr((string) (isset($authorize['pt_card_type']) ? $authorize['pt_card_type'] : ''), 0, 25)),
            'transaction_id' => pSQL(Tools::substr((string) (isset($authorize['pt_order_id']) ? $authorize['pt_order_id'] : ''), 0, 255)),
            'sent' => pSQL(print_r($sent, true), true),
            'received' => pSQL(print_r($received, true), true),
            'time' => pSQL(date('F j, Y, g:i a')),
            'session_id' => pSQL(Tools::substr((string) $sessionToken, 0, 255)),
        ]);

        if ($inserted) {
            $this->context->cookie->{self::COOKIE_LAST_ROW_ID} = (string) Db::getInstance()->Insert_ID();
            $this->context->cookie->write();
        }
    }

    public function updateStoredOrderId($orderId, $transactionId = '')
    {
        if ((int) $orderId < 1 || Configuration::get(self::CONFIG_STORE_DATA) !== 'True') {
            return;
        }

        $table = _DB_PREFIX_ . 'plugnpay_ss2';
        if (!$this->tableExists($table)) {
            return;
        }

        $rowId = (int) (isset($this->context->cookie->{self::COOKIE_LAST_ROW_ID})
            ? $this->context->cookie->{self::COOKIE_LAST_ROW_ID}
            : 0);
        if ($rowId > 0) {
            Db::getInstance()->update('plugnpay_ss2', [
                'order_id' => pSQL((string) $orderId),
            ], 'id = ' . (int) $rowId);
            unset($this->context->cookie->{self::COOKIE_LAST_ROW_ID});
            $this->context->cookie->write();

            return;
        }

        if ($transactionId !== '') {
            Db::getInstance()->execute(
                'UPDATE `' . bqSQL($table) . '` SET `order_id` = \'' . pSQL((string) $orderId) . '\''
                . ' WHERE `transaction_id` = \'' . pSQL($transactionId) . '\''
                . ' AND (`order_id` = \'\' OR `order_id` = \'0\') LIMIT 1'
            );
        }
    }

    public function clearExpectedReturnCookies()
    {
        $cookie = $this->context->cookie;
        unset(
            $cookie->{self::COOKIE_EXPECTED_AMOUNT},
            $cookie->{self::COOKIE_EXPECTED_ACCOUNT},
            $cookie->{self::COOKIE_EXPECTED_TOKEN},
            $cookie->{self::COOKIE_CART_ID}
        );
        $cookie->write();
    }

    private function canDisplayForCart($cart)
    {
        return $this->active
            && $this->isConfigured()
            && $this->isSecureRequest()
            && Validate::isLoadedObject($cart)
            && $this->checkCurrency($cart)
            && !(bool) $cart->OrderExists();
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
            && Configuration::updateValue(self::CONFIG_CURRENCY, 'USD')
            && Configuration::updateValue(self::CONFIG_STORE_DATA, 'True')
            && Configuration::updateValue(self::CONFIG_DEBUGGING, 'Off');
    }

    private function installDb()
    {
        $engine = defined('_MYSQL_ENGINE_') ? (string) _MYSQL_ENGINE_ : 'InnoDB';
        if (!in_array($engine, ['InnoDB', 'MyISAM'], true)) {
            $engine = 'InnoDB';
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'plugnpay_ss2` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `customer_id` varchar(30) NOT NULL DEFAULT \'\',
          `order_id` varchar(30) NOT NULL DEFAULT \'\',
          `response_code` varchar(32) NOT NULL DEFAULT \'\',
          `response_text` varchar(255) NOT NULL DEFAULT \'\',
          `authorization_type` varchar(25) NOT NULL DEFAULT \'\',
          `transaction_id` varchar(255) NOT NULL DEFAULT \'\',
          `sent` mediumtext,
          `received` mediumtext,
          `time` varchar(255) NOT NULL DEFAULT \'\',
          `session_id` varchar(255) NOT NULL DEFAULT \'\',
          PRIMARY KEY (`id`),
          KEY `idx_customer_id` (`customer_id`),
          KEY `idx_session_id` (`session_id`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        return (bool) Db::getInstance()->execute($sql);
    }

    private function uninstallDb()
    {
        return (bool) Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'plugnpay_ss2`');
    }

    private function tableExists($table)
    {
        $result = Db::getInstance()->executeS('SHOW TABLES LIKE \'' . pSQL($table) . '\'');

        return is_array($result) && count($result) > 0;
    }

    private function saveConfiguration()
    {
        $login = trim((string) Tools::getValue(self::CONFIG_LOGIN));
        $currency = strtoupper(trim((string) Tools::getValue(self::CONFIG_CURRENCY)));
        $storeData = (string) Tools::getValue(self::CONFIG_STORE_DATA);
        $debugging = (string) Tools::getValue(self::CONFIG_DEBUGGING);

        if ($login === '') {
            return $this->displayError($this->l('Gateway Account is required.'));
        }
        if (!in_array($currency, self::$supportedCurrencies, true)) {
            $currency = 'USD';
        }
        if (!in_array($storeData, ['True', 'False'], true)) {
            $storeData = 'True';
        }
        if (!in_array($debugging, ['Off', 'Log File'], true)) {
            $debugging = 'Off';
        }

        Configuration::updateValue(self::CONFIG_LOGIN, $login);
        Configuration::updateValue(self::CONFIG_CURRENCY, $currency);
        Configuration::updateValue(self::CONFIG_STORE_DATA, $storeData);
        Configuration::updateValue(self::CONFIG_DEBUGGING, $debugging);

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    private function renderInformation()
    {
        return '<div class="alert alert-info">'
            . '<strong>' . $this->l('Hosted checkout:') . '</strong> '
            . $this->l('Customers are redirected to PlugnPay Smart Screens v2. Card data is not collected on your server. Transactions are authorization-only; settle in PlugnPay Merchant Admin.')
            . '</div>';
    }

    private function renderForm()
    {
        $currencyOptions = [];
        foreach (self::$supportedCurrencies as $currency) {
            $currencyOptions[] = ['id' => $currency, 'name' => $currency];
        }

        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $this->l('PlugnPay Smart Screens v2 Settings'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Gateway Account'),
                        'name' => self::CONFIG_LOGIN,
                        'required' => true,
                        'desc' => $this->l('PlugnPay gateway account username (pt_gateway_account). No Remote Client Password is required.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Currency Supported'),
                        'name' => self::CONFIG_CURRENCY,
                        'desc' => $this->l('Gateway account currency. Orders in other currencies are converted using store exchange rates before submission.'),
                        'options' => [
                            'query' => $currencyOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    $this->selectField(
                        $this->l('Enable Database Storage'),
                        self::CONFIG_STORE_DATA,
                        ['True', 'False'],
                        $this->l('Save sanitized submit/response snapshots to the plugnpay_ss2 table.')
                    ),
                    $this->selectField(
                        $this->l('Debug Logging'),
                        self::CONFIG_DEBUGGING,
                        ['Off', 'Log File'],
                        $this->l('Logs are sanitized and never contain full PAN, CVV, or passwords.')
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
        $helper->submit_action = 'submitPlugnpayss2Module';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                self::CONFIG_LOGIN => Configuration::get(self::CONFIG_LOGIN),
                self::CONFIG_CURRENCY => Configuration::get(self::CONFIG_CURRENCY),
                self::CONFIG_STORE_DATA => Configuration::get(self::CONFIG_STORE_DATA),
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
