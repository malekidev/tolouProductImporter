<?php

namespace App\Services;

use App\Models\ImportItem;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiProductGeneratorService
{
    /**
     * از روی داده خام CSV، عنوان/توضیحات/ویژگی‌های محصول رو با AI تولید می‌کنه.
     * خروجی رو به صورت آرایه برمی‌گردونه که در ai_data ذخیره می‌شه.
     */
    public function generateData(ImportItem $item): array
    {
        $raw = $item->raw_data;

        $prompt = $this->buildPrompt($raw);

        $response = Http::withToken("sk-6Jkb5bFwEroq1IjK0FWMeJRDryinKypegGsR8qVs66qwz7Na")
            ->withHeaders([
                'content-type' => 'application/json',
            ])
            ->timeout(60)
            ->post('https://api.gapgpt.app/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'tools' => [['type' => 'web_search']],
                'tool_choice' => 'required',
                "messages" => [
                    [
                        'role' => 'system',
                        'content' => 'تو یک متخصص تحقیق محصول در حوزه اسباب‌بازی ایران هستی. فقط از سایت‌های ایرانی استفاده کن. سایت‌های خارجی ممنوع هستند.'
                    ],
                    [
                        'role' => 'system',
                        'content' => "هیچ دیتایی رو از خودت پر نکن و از اون مطمئن باش"
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI request failed: ' . $response->body());
        }
        //dd($response->body());
        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new RuntimeException('Gateway response had no message content: ' . $response->body());
        }

        $data = $this->extractJson($content);

        if ($data === null) {
            throw new RuntimeException('Gateway response was not valid JSON: ' . $content);
        }


        return $data;
    }
    public function generate(ImportItem $item): array
    {
        $raw = $item->raw_data;

        $prompt = $this->buildFinalPrompt($this->generateData($item),$raw);

        $response = Http::withToken("sk-6Jkb5bFwEroq1IjK0FWMeJRDryinKypegGsR8qVs66qwz7Na")
            ->withHeaders([
                'content-type' => 'application/json',
            ])
            ->timeout(60)
            ->post('https://api.gapgpt.app/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                "messages" => [
                    [
                        'role' => 'system',
                        'content' => 'تو یک متخصص ساختاردهی محصول ووکامرس هستی.'
                    ],
                    [
                        'role' => 'system',
                        'content' => "اطلاعات تحقیق شده محصول را دریافت می‌کنی و باید آن را به JSON قابل استفاده برای WooCommerce تبدیل کنی."
                    ],
                    [
                        'role' => 'system',
                        'content' => 'اطلاعات اصلی محصول رو هم بهت میدم هر چیزی که داخل اون بود رو از تحقیقات استفاده نکن و مستقیم از خودش استفاده کن'
                    ],
                    [
                        'role' => 'system',
                        'content' => "قیمت و تعداد در کارتن رو عددی وارد کن"
                    ],
                    [
                        'role' => 'system',
                        'content' => "جنسیت از بین سه مقدار : پسرانه ، دخترانه ، پسرانه/دخترانه انتخاب بشه"
                    ],
                    [
                        'role' => 'system',
                        'content' => "رده سنی از بین چهار مقدار : خردسال ، کودک ، نوجوان ، همه رده ها انتخاب بشه"
                    ],

                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI request failed: ' . $response->body());
        }
        //dd($response->body());
        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new RuntimeException('Gateway response had no message content: ' . $response->body());
        }

        $data = $this->extractJson($content);

        if ($data === null) {
            throw new RuntimeException('Gateway response was not valid JSON: ' . $content);
        }


        return $data;
    }

    private function buildPrompt(array $raw): string
    {
        $json = json_encode($raw, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT

منابع اولویت‌دار:

1. سایت‌های تخصصی اسباب‌بازی ایرانی:
- https://www.shahrkoodak.com
- https://www.toytoy.ir
- https://www.bazibin.com
- https://www.bazisell.com
- https://www.toy-world.ir
- https://www.koodakplus.com
- https://www.toyland.ir
- https://www.mannakala.com
- https://www.tabantoys.com
- https://www.iranianbaby.ir

2. فروشگاه‌های ایرانی عمومی در صورت نبود اطلاعات:
- دیجی‌کالا
- ترب
- ایمالز

3. سایت رسمی برند در صورت وجود.

برای محصول زیر تحقیق کن:

{$json}

اطلاعات زیر را استخراج کن:

- نام کامل محصول
- برند
- دسته‌بندی اسباب‌بازی
- رده سنی
- جنسیت مناسب
- تعداد بازیکن
- رنگ و طرح
- جنس بدنه
- وزن
- ابعاد بسته‌بندی
- نوع بسته‌بندی
- محتویات داخل جعبه
- نیاز به باتری
- نوع باتری
- ویژگی‌های محصول
- اهداف آموزشی
- توضیحات کوتاه
- توضیحات کامل
- لینک منابعی که اطلاعات از آن‌ها استخراج شده است

- خروجی فقط JSON معتبر باشد.
- هیچ Markdown یا توضیح اضافه نده.

ساختار خروجی :

{
 "sources": [],
 "product_info": {}
}


PROMPT;
    }
    private function buildFinalPrompt(array $data,$raw): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $product = json_encode($raw, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT




قوانین:
- فقط از اطلاعات داده شده استفاده کن.
- هیچ اطلاعات جدیدی اضافه نکن.
- اگر اطلاعاتی موجود نبود مقدار آن را "" قرار بده.
- خروجی فقط JSON معتبر باشد.
- هیچ Markdown یا توضیح اضافه نده.

ساختار خروجی:

{
  "product_name": "",
  "brand": "",
  "sku": "",
  "parent_sku": "",
  "price": "",
  "carton_quantity": "",
  "features": ",
  "box_contents": ",
  "battery_type": "",
  "weight": "",
  "body_material": "",
  "package_dimensions": "",
  "other_specs": ",
  "short_description": "",
  "age_range": "",
  "gender": "",
  "player_count": "",
  "design_color": "",
  "packaging_type": "",
  "educational_goals": ",
  "category": ""
}

اطلاعات تحقیق شده:

{$json}

اطلاعات اصلی محصول :

{$product}

PROMPT;
    }

    private function extractJson(string $text): ?array
    {
        $clean = preg_replace('/^```json|```$/m', '', trim($text));
        $data = json_decode($clean, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $data = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return null;
    }

}
