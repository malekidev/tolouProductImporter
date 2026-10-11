<?php

namespace App\Services;

use App\Models\ImportItem;
use Automattic\WooCommerce\Client;

use RuntimeException;

class WooCommerceProductService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(
            "https://toloutoys.com",
            "ck_10124ef174e51934e02afaab55d7b410bd04545c",
            "cs_6416d26e24f82241aec4116f5f0d98ec3bc2926e",
            ['version' => 'wc/v3', 'timeout' => 30]
        );
    }

    /**
     * محصول رو در ووکامرس می‌سازه.
     * قبلش با SKU چک می‌کنه که محصول تکراری نسازه (idempotency در برابر retry).
     */
    public function createProduct(ImportItem $item): int
    {
        $data = $item->ai_data;

        $attributes = collect([
            $this->buildAttribute(11, ['تکی', 'کارتنی'], variation: true),
            $this->buildAttribute(7, $this->toAttrValue($data['age_range'] ?? '')),
            $this->buildAttribute(8, $this->toAttrValue($data['gender'] ?? '')),
            $this->buildAttribute(10, $this->toAttrValue($data['player_count'] ?? '')),
            $this->buildAttribute(6, $this->toAttrValue($data['design_color'] ?? '')),
            $this->buildAttribute(9, $this->toAttrValue($data['educational_goals'] ?? '')),
            $this->buildAttribute(12, $this->toAttrValue($data['features'] ?? '')),
            $this->buildAttribute(4, $this->toAttrValue($data['box_contents'] ?? '')),
            $this->buildAttribute(13, $this->toAttrValue($data['battery_type'] ?: 'ندارد')),
            $this->buildAttribute(1, $this->toAttrValue($data['body_material'] ?? '')),
            $this->buildAttribute(15, $this->toAttrValue($data['package_dimensions'] ?? '')),
            $this->buildAttribute(2, $this->toAttrValue($data['packaging_type'] ?? '')),
            $this->buildAttribute(17, $data['sku'] ? "کد محصول: {$data['sku']}" : ''),
        ])->filter()->values()->all(); // null هارو حذف کن (فیلدهای خالی)

        $payload = [
            'name' => $data['product_name'],
            'type' => 'variable',
            'description' => $data['short_description'],
            'short_description' => $data['short_description'],
            'sku' => $data['sku'],
            'price' => (string) $data['price'],
            'manage_stock' => false,
            'stock_status' => 'instock',
            'attributes' => $attributes,
            'brands' => [
                ['id' => 2135],
            ],
            'meta_data' => [
                [
                    'key' => '_carton_quantity',
                    'value' => (string) ($data['carton_quantity'] ?? ''),
                ],
            ],
        ];

        try {
            $response = $this->client->post('products', $payload);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            throw new RuntimeException(
                'WooCommerce error: '.$e->getMessage().' | Response: '.$e->getResponse()->getBody()
            );
        }

        if (empty($response->id)) {
            throw new RuntimeException('WooCommerce did not return a product id');
        }

        return (int) $response->id;
    }

    /**
     * واریشن‌ها رو برای محصول variable می‌سازه.
     * قبل از ساخت، واریشن‌های موجود رو می‌گیره تا در retry دوباره تکراری نسازه.
     */
    public function createVariations(ImportItem $item): void
    {
        $data = $item->ai_data;

        $existingVariations = collect(
            $this->client->get(
                "products/{$item->woo_product_id}/variations",
                ['per_page' => 100]
            )
        );

        $existingOptions = $existingVariations
            ->flatMap(fn($variation) => collect($variation->attributes)->pluck('option'))
            ->all();
        if (empty($data['price'])){
            $data['price'] = 0;
        }
        if (empty($data['carton_quantity'])){
            $data['carton_quantity'] = 1;
        }
        $variations = [
            [
                'option' => 'تکی',
                'price' => $data['price'],
            ],
            [
                'option' => 'کارتنی',
                'price' => $data['price'] * $data['carton_quantity'],
            ],
        ];

        foreach ($variations as $variation) {

            if (in_array($variation['option'], $existingOptions, true)) {
                continue;
            }

            $this->client->post(
                "products/{$item->woo_product_id}/variations",
                [
                    'regular_price' => (string)$variation['price'],
                    "stock_quantity" => 9999999999,
                    'attributes' => [
                        [
                            'id' => 11,
                            'option' => $variation['option'],
                        ],
                    ],
                    "meta_data" => [
                        [
                            "key" => "_carton_quantity",
                            "value" => $data['carton_quantity'],
                        ],
                    ],
                ]
            );
        }
    }

    private function findBySku(string $sku): ?array
    {
        $results = $this->client->get('products', ['sku' => $sku]);

        return isset($results[0]) ? (array)$results[0] : null;
    }
    /**
     * مقدار خام از ai_data رو برای buildAttribute آماده می‌کنه.
     * اگه آرایه باشه همون‌طور برمی‌گردونه، در غیر این صورت کست به string می‌کنه
     * (تا خطای "Array to string conversion" پیش نیاد).
     */
    private function toAttrValue(mixed $value): string|array
    {
        return is_array($value) ? $value : (string) ($value ?? '');
    }
    private function buildAttribute(int $id, string|array $rawValue, bool $variation = false): ?array
    {
        // ورودی می‌تونه رشته باشه (که با '،' جدا می‌کنیم) یا از قبل آرایه
        $values = is_array($rawValue) ? $rawValue : explode('،', $rawValue);

        $options = collect($values)
            ->map(fn ($v) => trim((string) $v))   // <-- کست به string حتما لازمه (برای int/float مثل player_count)
            ->filter(fn ($v) => $v !== '')         // مقادیر خالی حذف بشن
            ->values()
            ->all();

        if (empty($options)) {
            return null; // اگه هیچی نمونده، اصلا این attribute رو نساز
        }

        return [
            'id' => $id,
            'options' => $options,
            'variation' => $variation,
            'visible' => true,
        ];
    }



}
