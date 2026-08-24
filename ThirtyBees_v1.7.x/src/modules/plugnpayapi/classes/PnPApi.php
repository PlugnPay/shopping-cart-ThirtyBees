<?php
/**
 * PlugnPay Remote API client.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license AFL-3.0
 * @see https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/
 */

class PnPApi
{
    const ENDPOINT = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

    /** @var string */
    private $publisherName;

    /** @var string */
    private $publisherPassword;

    /** @var PnPLogger|null */
    private $logger;

    /** @var string */
    private $lastRawResponse = '';

    /** @var int */
    private $communicationErrorNumber = 0;

    /** @var string */
    private $communicationError = '';

    public function __construct($publisherName, $publisherPassword, ?PnPLogger $logger = null)
    {
        $this->publisherName = (string) $publisherName;
        $this->publisherPassword = (string) $publisherPassword;
        $this->logger = $logger;
    }

    public function getLastRawResponse()
    {
        return $this->lastRawResponse;
    }

    public function getCommunicationErrorNumber()
    {
        return $this->communicationErrorNumber;
    }

    public function authorize(array $fields)
    {
        return $this->request(array_merge([
            'mode' => 'auth',
            'paymethod' => 'credit',
            'client' => 'thirtybees_api',
        ], $fields));
    }

    public function isApproved(array $response)
    {
        $finalStatus = strtolower((string) (isset($response['FinalStatus']) ? $response['FinalStatus'] : ''));
        $success = strtolower((string) (isset($response['success']) ? $response['success'] : ''));

        return $finalStatus === 'success' || $success === 'yes';
    }

    public function request(array $fields)
    {
        if (!is_callable('curl_init')) {
            return $this->communicationFailure(-1, 'The PHP cURL extension is unavailable.');
        }

        $payload = array_merge([
            'publisher-name' => $this->publisherName,
            'publisher-password' => $this->publisherPassword,
        ], $fields);

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                unset($payload[$key]);
            }
        }

        if ($this->logger) {
            $this->logger->log('Request to PlugnPay', [
                'endpoint' => self::ENDPOINT,
                'fields' => $payload,
            ]);
        }

        $handle = curl_init(self::ENDPOINT);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $rawResponse = curl_exec($handle);
        $this->communicationErrorNumber = (int) curl_errno($handle);
        $this->communicationError = (string) curl_error($handle);
        $communicationInfo = curl_getinfo($handle);
        curl_close($handle);

        $this->lastRawResponse = is_string($rawResponse) ? $rawResponse : '';
        if ($this->lastRawResponse === '' || $this->communicationErrorNumber !== 0) {
            if ($this->logger) {
                $this->logger->log('Communication failure', [
                    'errno' => $this->communicationErrorNumber,
                    'error' => $this->communicationError,
                    'info' => $communicationInfo,
                ]);
            }

            return $this->communicationFailure(
                $this->communicationErrorNumber,
                $this->communicationError !== ''
                    ? $this->communicationError
                    : 'Empty response from PlugnPay.'
            );
        }

        $response = $this->parseResponse($this->lastRawResponse);
        if ($this->logger) {
            $this->logger->log('Response from PlugnPay', [
                'FinalStatus' => isset($response['FinalStatus']) ? $response['FinalStatus'] : '',
                'success' => isset($response['success']) ? $response['success'] : '',
                'orderID' => isset($response['orderID']) ? $response['orderID'] : '',
                'auth-code' => isset($response['auth-code']) ? $response['auth-code'] : '',
                'resp-code' => isset($response['resp-code']) ? $response['resp-code'] : '',
                'MErrMsg' => isset($response['MErrMsg']) ? $response['MErrMsg'] : '',
                'avs-code' => isset($response['avs-code']) ? $response['avs-code'] : '',
                'cvvresp' => isset($response['cvvresp']) ? $response['cvvresp'] : '',
            ]);
        }

        return $response;
    }

    private function communicationFailure($errorNumber, $message)
    {
        $this->communicationErrorNumber = (int) $errorNumber;
        $this->communicationError = (string) $message;

        return [
            'FinalStatus' => 'problem',
            'success' => 'no',
            'MErrMsg' => (string) $message,
        ];
    }

    private function parseResponse($rawResponse)
    {
        $parsed = [];
        parse_str((string) $rawResponse, $parsed);

        $response = [];
        foreach ($parsed as $key => $value) {
            $response[(string) $key] = is_array($value)
                ? implode(',', $value)
                : (string) $value;
        }

        return $response;
    }
}
