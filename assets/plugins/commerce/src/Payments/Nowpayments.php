<?php

namespace Commerce\Payments;

class Nowpayments extends Payment
{
    protected $debug;
    protected $test;

    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('lava');
        $this->debug = $this->getSetting('debug') == '1';
        $test = $this->getSetting('test');
        $this->test = $test == 1;
    }

    public function getMarkup()
    {
        $settings = ['secret_key', 'api_key'];
        foreach ($settings as $setting) {
            if (empty($setting)) {
                return '<span class="error" style="color: red;">' . $this->lang['nowpayments.error_empty_params'] . '</span>';
            }
        }

        return '';
    }

    public function getPaymentLink()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $payment   = $this->createPayment($order['id'], $order['amount']);
        $data = [
            'price_amount' => $payment['amount'],
            'price_currency' => $this->test ? 'usd' : $order['currency'],
            'order_id' => $order['id'] . '-' . $payment['hash'],
            'ipn_callback_url' => MODX_SITE_URL . 'commerce/nowpayments/payment-process?paymentHash=' . $payment['hash'],
            'cancel_url' => MODX_SITE_URL . 'commerce/nowpayments/payment-failed',
            'success_url' => MODX_SITE_URL . 'commerce/nowpayments/payment-success?paymentHash=' . $payment['hash'],
            'order_description' => $this->lang['nowpayments.order_description'] . ' ' . $order['id'],
            'is_fee_paid_by_user' => $this->getSetting('fee_paid_by_user') == 1
        ];
        try {
            $response = $this->request('invoice', $data);

            return $response['invoice_url'];
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->modx->logEvent(0, 3,
                    'Request failed: <pre>' . print_r($data, true) . '</pre><pre>' . print_r($e->getMessage() . ' ' . $e->getCode(), true) . '</pre>', 'Commerce Nowpayments Payment');
            }
        }

        return false;
    }

    public function handleCallback()
    {
        $input = file_get_contents('php://input');
        $response = json_decode($input, true);
        ksort($response);
        $callbackSignature = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        if ($this->debug) {
            $this->modx->logEvent(0, 3, 'Callback start <pre>' . $input . '</pre><pre>' . print_r($response, true) . '</pre><br>' . $callbackSignature, 'Commerce Nowpayments Payment Callback');
        }
        if (isset($response['payment_status']) && in_array($response['payment_status'], ['partially_paid', 'finished'])) {
            try {
                $precision = ini_get("serialize_precision");
                ini_set("serialize_precision", 14);
                $signature = hash_hmac('sha512', json_encode($response, JSON_UNESCAPED_UNICODE), $this->getSetting('secret_key'));
                ini_set("serialize_precision", $precision);
                if ($signature !== $callbackSignature) throw new \Exception();
            } catch (\Exception $e) {
                if ($this->debug) {
                    $this->modx->logEvent(0, 3, 'Wrong request signature ' . $callbackSignature . '<br>' . $signature, 'Commerce Nowpayments Payment Callback');

                    return false;
                }
            }
            $paymentHash = $this->getRequestPaymentHash();
            $processor = $this->modx->commerce->loadProcessor();
            try {
                $payment = $processor->loadPaymentByHash($paymentHash);

                if (!$payment) {
                    throw new Exception('Payment "' . htmlentities(print_r($paymentHash, true)) . '" . not found!');
                }
                if ($response['payment_status'] == 'partially_paid') {
                    $amount = $response['actually_paid_at_fiat'];
                    $id = $payment['id'];
                    $this->modx->db->update(['amount' => (float)$amount], $this->modx->getFullTableName('commerce_order_payments'), "`id` = '" . intval($id) . "'");
                    $status = $this->getSetting('partial_payment_status', null);
                } else {
                    $amount = $payment['amount'];
                    $status = null;
                }
                
                return $processor->processPayment($payment['id'], $amount, $status);
            } catch (Exception $e) {
                if ($this->debug) {
                    $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Nowpayments Payment Callback');

                    return false;
                }
            }
        }

        return false;
    }

    public function getRequestPaymentHash()
    {
        if (!empty($_REQUEST['paymentHash']) && is_scalar($_REQUEST['paymentHash'])) {
            return $_REQUEST['paymentHash'];
        }

        return null;
    }

    protected function request($method, array $data) {
        $curl = curl_init();
        $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $key = $this->getSetting('api_key');
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://' . ($this->test ? 'api-sandbox' : 'api') . '.nowpayments.io/v1/' . $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $key
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (isset($response['status']) && $response['status'] == false) {
            throw new \Exception($response['message'], $response['statusCode']);
        }

        return $response;
    }
}
