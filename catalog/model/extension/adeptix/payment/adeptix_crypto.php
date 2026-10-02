<?php

namespace Opencart\Catalog\Model\Extension\Adeptix\Payment;

class AdeptixCrypto extends \Opencart\System\Engine\Model
{
    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/adeptix/payment/adeptix_crypto');

        if (!$this->config->get('payment_adeptix_crypto_status')) {
            return [];
        }

        return [
            'code' => 'adeptix_crypto',
            'name' => $this->language->get('heading_title'),
            'option' => [
                'adeptix_crypto' => [
                    'code' => 'adeptix_crypto.adeptix_crypto',
                    'name' => $this->language->get('heading_title'),
                ],
            ],
            'sort_order' => $this->config->get('payment_adeptix_crypto_sort_order'),
        ];
    }
}
