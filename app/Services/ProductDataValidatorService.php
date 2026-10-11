<?php

namespace App\Services;

use App\Models\ImportItem;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ProductDataValidatorService
{
    /**
     * ai_data تولید شده رو قبل از ارسال به ووکامرس اعتبارسنجی می‌کنه.
     * اگه معتبر نبود، Exception پرتاب می‌کنه تا جاب اون رو failed کنه.
     */
    public function validateItem(ImportItem $item): void
    {
        $data = $item->ai_data ?? [];

        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'sku' => 'required|string|max:100',
            'regular_price' => 'required|numeric|min:0',
            'attributes' => 'array',
            'attributes.*.name' => 'required_with:attributes|string',
            'attributes.*.options' => 'required_with:attributes|array|min:1',
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'Validation failed: '.$validator->errors()->first()
            );
        }
    }
}
