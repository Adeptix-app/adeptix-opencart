<?php

namespace Opencart\Catalog\Controller\Extension\Adeptix\Payment;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Adeptix\AdeptixClient;
use Adeptix\Exceptions\AdeptixApiException;
use Adeptix\Webhooks;

class AdeptixCrypto extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/adeptix/payment/adeptix_crypto');
        $data['language'] = $this->config->get('config_language');
        return $this->load->view('extension/adeptix/payment/adeptix_crypto', $data);
    }

    /** Called via AJAX from the checkout confirm button - creates the deposit request and returns its details to display in place. */
    public function confirm(): void
    {
        $this->load->language('extension/adeptix/payment/adeptix_crypto');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }
        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] !== 'adeptix_crypto.adeptix_crypto') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            $this->load->model('checkout/order');
            $orderId = (int) $this->session->data['order_id'];
            $order = $this->model_checkout_order->getOrder($orderId);

            $client = new AdeptixClient((string) $this->config->get('payment_adeptix_api_key'));

            try {
                $request = $client->crypto()->createPaymentRequest([
                    'chain' => (string) $this->config->get('payment_adeptix_crypto_chain'),
                    'token' => (string) $this->config->get('payment_adeptix_crypto_token'),
                    // Store totals are in the shop's own fiat currency, not the crypto asset - a
                    // real store needs its own fiat->crypto conversion upstream of this call.
                    'amount' => number_format((float) $order['total'], 2, '.', ''),
                    'order_ref' => (string) $orderId,
                    'customer_email' => $order['email'],
                ]);
            } catch (AdeptixApiException $e) {
                $json['error'] = $e->getMessage();
            }

            if (!$json) {
                $this->model_checkout_order->addHistory($orderId, (int) $this->config->get('payment_adeptix_crypto_order_status_id'));

                $this->db->query(
                    "INSERT INTO `" . DB_PREFIX . "adeptix_crypto_order` SET "
                    . "`order_id` = '" . (int) $orderId . "', "
                    . "`payment_request_id` = '" . $this->db->escape($request['payment_request_id']) . "', "
                    . "`pay_to_address` = '" . $this->db->escape($request['pay_to_address']) . "', "
                    . "`amount` = '" . $this->db->escape($request['amount']) . "', "
                    . "`chain` = '" . $this->db->escape($request['chain']) . "', "
                    . "`token` = '" . $this->db->escape($request['token']) . "', "
                    . "`expires_at` = '" . $this->db->escape($request['expires_at']) . "'"
                );

                // No redirect - this is an "Offline"-style flow (see the PrestaShop module in this
                // same repo for the same distinction in its own docs' terms). The order already
                // exists (OpenCart's own checkout/confirm step creates it before this runs), so we
                // just hand the deposit details back for the storefront JS to display in place.
                $json['pay_to_address'] = $request['pay_to_address'];
                $json['amount'] = $request['amount'];
                $json['chain'] = $request['chain'];
                $json['token'] = $request['token'];
                $json['expires_at'] = $request['expires_at'];
                $json['success_url'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Server-to-server webhook receiver - route=extension/adeptix/payment/adeptix_crypto.webhook
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
        // See adeptix_merchant.php's webhook() for why test_mode is checked here too.
        if (is_array($event) && ($event['event'] ?? null) === 'crypto_payment.matched' && isset($event['order_ref']) && empty($event['test_mode'])) {
            $this->load->model('checkout/order');
            $orderId = (int) $event['order_ref'];
            $order = $this->model_checkout_order->getOrder($orderId);
            if ($order) {
                $this->model_checkout_order->addHistory($orderId, (int) $this->config->get('payment_adeptix_crypto_paid_status_id'));
            }
        }

        $this->response->addHeader('HTTP/1.1 200 OK');
        $this->response->setOutput('');
    }
}
