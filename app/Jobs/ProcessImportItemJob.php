<?php

namespace App\Jobs;

use App\Models\ImportItem;
use App\Services\AiProductGeneratorService;
use App\Services\ProductDataValidatorService;
use App\Services\WooCommerceProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessImportItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public ImportItem $item)
    {
    }

    public function handle(
        AiProductGeneratorService $ai,
        ProductDataValidatorService $validator,
        WooCommerceProductService $woo
    ): void {
        $this->item->markProcessing();

        try {
            if ($this->item->current_step === 'ai') {
                $aiData = $ai->generate($this->item);
                $this->item->update(['ai_data' => $aiData]);
                $this->item->markStepDone('create_product');
            }


            if ($this->item->current_step === 'create_product' && ! $this->item->woo_product_id) {
                $productId = $woo->createProduct($this->item);
                $this->item->update(['woo_product_id' => $productId]);
                $this->item->markStepDone('create_variations');
            }

            if ($this->item->current_step === 'create_variations') {
                $woo->createVariations($this->item);
            }

            $this->item->markSuccess();
        } catch (Throwable $e) {
            $this->item->markFailed($e);
        }

        $this->item->import->refreshStatus();
    }
}
