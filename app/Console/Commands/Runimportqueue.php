<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImportItemJob;
use App\Models\Import;
use App\Models\ImportItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunImportQueue extends Command
{
    protected $signature = 'imports:process';

    protected $description = 'یک سیکل کامل پردازش صف ایمپورت: دیسپچ pending، اجرای صف، retry آیتم‌های failed';

    public function handle(): int
    {
        $this->dispatchPending();

        // صف واقعی رو یک بار خالی کن (queue connection = database)
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--max-time' => 300,
        ]);

        $this->retryFailedItems();
        $this->refreshImportStatuses();

        return self::SUCCESS;
    }

    private function dispatchPending(): void
    {
        ImportItem::where('status', 'pending')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    ProcessImportItemJob::dispatch($item);
                }
            });
    }

    private function retryFailedItems(): void
    {
        ImportItem::where('status', 'failed')
            ->where('attempts', '<', ImportItem::MAX_ATTEMPTS)
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $item->update(['status' => 'pending']);
                    ProcessImportItemJob::dispatch($item);
                }
            });
    }

    private function refreshImportStatuses(): void
    {
        Import::where('status', 'processing')->each(
            fn (Import $import) => $import->refreshStatus()
        );
    }
}
