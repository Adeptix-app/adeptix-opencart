<?php

namespace Opencart\Catalog\Controller\Extension\Adeptix\Payment;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Adeptix\AdeptixClient;
use Adeptix\Exceptions\AdeptixApiException;
use Adeptix\Webhooks;

class AdeptixMerchant extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/adeptix/payment/adeptix_merchant');
        $data['language'] = $this->config->get('config_language');
        return $this->load->view('extension/adeptix/payment/adeptix_merchant', $data);
    }

    /** Called via AJAX from the checkout confirm button - creates the hosted checkout and hands back its URL to redirect to. */
    public function confirm(): void
    {
        $this->load->language('extension/adeptix/payment/adeptix_merchant');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }
        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] !== 'adeptix_merchant.adeptix_merchant') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            $this->load->model('checkout/order');
            $orderId = (int) $this->session->data['order_id'];
            $order = $this->model_checkout_order->getOrder($orderId);

            $client = new AdeptixClient((string) $this->config->get('payment_adeptix_api_key'));

            try {
                $payment = $client->payments()->create([
                    'amount' => number_format((float) $order['total'], 2, '.', ''),
                    'currency' => $order['currency_code'],
                    'email' => $order['email'],
                    'provider' => (string) $this->config->get('payment_adeptix_provider'),
                    'order_ref' => (string) $orderId,
                ]);
            } catch (AdeptixApiException $e) {
                $json['error'] = $e->getMessage();
            }

            if (!$json) {
                $this->model_checkout_order->addHistory($orderId, (int) $this->config->get('payment_adeptix_merchant_order_status_id'));
                $json['redirect'] = $payment['payment_url'];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Server-to-server webhook receiver - route=extension/adeptix/payment/adeptix_merchant.webhook
     * (registered as this exact URL in the Adeptix dashboard's Settings page). See
     * https://docs.adeptix.app/webhooks
     */
    public function webhook(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_ADEPTIX_SIGNATURE'] ?? null;
        $secret = (string) $this->config->get('payment_adeptix_webhook_secret');

        if (!Webhooks::verifySignature($rawBody, $signature, $secret)) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->setOutput('');
            return;
        }

        $event = json_decode($rawBody, true);
        // empty($event['test_mode']): a live store has no notion of "test mode" of its own, so a
        // webhook carrying it is never meant for it - without this check, a sandbox API key's test
        // payment could mark a real order paid if its order_ref happened to match one.
        if (is_array($event) && ($event['event'] ?? null) === 'payment.paid' && isset($event['order_ref']) && empty($event['test_mode'])) {
            $this->load->model('checkout/order');
            $orderId = (int) $event['order_ref'];
            $order = $this->model_checkout_order->getOrder($orderId);
            if ($order) {
                $this->model_checkout_order->addHistory($orderId, (int) $this->config->get('payment_adeptix_merchant_paid_status_id'));
            }
        }

        $this->response->addHeader('HTTP/1.1 200 OK');
        $this->response->setOutput('');
    }
}
