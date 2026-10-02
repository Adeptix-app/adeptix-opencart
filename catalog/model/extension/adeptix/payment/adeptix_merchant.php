<?php

namespace Opencart\Catalog\Model\Extension\Adeptix\Payment;

class AdeptixMerchant extends \Opencart\System\Engine\Model
{
    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/adeptix/payment/adeptix_merchant');

        if (!$this->config->get('payment_adeptix_merchant_status')) {
            return [];
        }

        return [
            'code' => 'adeptix_merchant',
            'name' => $this->language->get('heading_title'),
            'option' => [
                'adeptix_merchant' => [
                    'code' => 'adeptix_merchant.adeptix_merchant',
                    'name' => $this->language->get('heading_title'),
                ],
            ],
            'sort_order' => $this->config->get('payment_adeptix_merchant_sort_order'),
        ];
    }
}
