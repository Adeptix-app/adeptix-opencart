<?php
/**
 * Adeptix payment extension - shared admin settings controller for both registered payment
 * methods (adeptix_merchant, adeptix_crypto). One settings screen, two independently
 * enable/sort/order-status-configurable methods, sharing the API key and webhook secret.
 */

namespace Opencart\Admin\Controller\Extension\Adeptix\Payment;

class Adeptix extends \Opencart\System\Engine\Controller
{
    private const SETTINGS = [
        'payment_adeptix_api_key', 'payment_adeptix_webhook_secret', 'payment_adeptix_provider',
        'payment_adeptix_crypto_chain', 'payment_adeptix_crypto_token',
        'payment_adeptix_merchant_status', 'payment_adeptix_merchant_sort_order',
        'payment_adeptix_merchant_order_status_id', 'payment_adeptix_merchant_paid_status_id',
        'payment_adeptix_crypto_status', 'payment_adeptix_crypto_sort_order',
        'payment_adeptix_crypto_order_status_id', 'payment_adeptix_crypto_paid_status_id',
    ];

    public function index(): void
    {
        $this->load->language('extension/adeptix/payment/adeptix');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment'),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/adeptix/payment/adeptix', 'user_token=' . $this->session->data['user_token']),
            ],
        ];

        $data['save'] = $this->url->link('extension/adeptix/payment/adeptix.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        foreach (self::SETTINGS as $key) {
            $data[$key] = $this->config->get($key);
        }

        // The extension has no single fixed webhook URL of its own here - each registered method
        // (adeptix_merchant / adeptix_crypto) exposes its own webhook() action, since OpenCart
        // routes an unauthenticated request through the SAME controller class as the storefront
        // payment option itself (see catalog/controller/.../adeptix_merchant.php).
        $data['merchant_webhook_url'] = $this->url->link('extension/adeptix/payment/adeptix_merchant.webhook', '', true);
        $data['crypto_webhook_url'] = $this->url->link('extension/adeptix/payment/adeptix_crypto.webhook', '', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/adeptix/payment/adeptix', $data));
    }

    /** One row per order paid via the crypto option - deposit address/amount/expiry, looked up by the storefront confirmation page and the crypto webhook. */
    public function install(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "adeptix_crypto_order` (
                `order_id` INT(11) UNSIGNED NOT NULL PRIMARY KEY,
                `payment_request_id` VARCHAR(64) NOT NULL,
                `pay_to_address` VARCHAR(128) NOT NULL,
                `amount` VARCHAR(32) NOT NULL,
                `chain` VARCHAR(16) NOT NULL,
                `token` VARCHAR(8) NOT NULL,
                `expires_at` VARCHAR(40) NOT NULL,
                UNIQUE KEY `payment_request_id` (`payment_request_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }

    public function uninstall(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "adeptix_crypto_order`");
    }

    public function save(): void
    {
        $this->load->language('extension/adeptix/payment/adeptix');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/adeptix/payment/adeptix')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_adeptix', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
