<?php
/**
 * Sanitized debug logger for PlugnPay Remote API requests.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 */

class PnPLogger
{
    /** @var string[] */
    private static $sensitiveKeys = [
        'card-number',
        'card_number',
        'card-cvv',
        'card_cvv',
        'publisher-password',
        'publisher_password',
        'cc_number',
        'cc_cvv',
    ];

    /** @var string */
    private $logDirectory;

    /** @var bool */
    private $enabled;

    public function __construct($logDirectory, $enabled = false)
    {
        $this->logDirectory = rtrim((string) $logDirectory, '/\\');
        $this->enabled = (bool) $enabled;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function log($message, array $context = [])
    {
        if (!$this->enabled) {
            return;
        }

        $line = date('Y-m-d H:i:s') . ' ' . (string) $message;
        if ($context) {
            $line .= "\n" . print_r($this->sanitize($context), true);
        }
        $line .= "\n----------------------------------------\n";

        $file = $this->logDirectory . '/plugnpay_api_' . date('Ymd') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public function sanitize(array $data)
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $keyString = (string) $key;
            if ($this->isSensitiveKey($keyString)) {
                if (stripos($keyString, 'number') !== false && is_string($value) && strlen($value) >= 4) {
                    $sanitized[$key] = str_repeat('X', strlen($value) - 4) . substr($value, -4);
                } else {
                    $sanitized[$key] = '***REDACTED***';
                }
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey($key)
    {
        $normalized = strtolower(str_replace('_', '-', (string) $key));
        foreach (self::$sensitiveKeys as $sensitiveKey) {
            if ($normalized === strtolower(str_replace('_', '-', $sensitiveKey))) {
                return true;
            }
        }

        return false;
    }
}
