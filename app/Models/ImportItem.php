<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    use HasFactory;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'import_id',
        'raw_data',
        'ai_data',
        'woo_product_id',
        'status',
        'current_step',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'ai_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markStepDone(string $nextStep): void
    {
        $this->update(['current_step' => $nextStep]);
    }

    public function markSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'last_error' => null,
        ]);
    }

    public function markFailed(\Throwable $e): void
    {
        $attempts = $this->attempts + 1;

        $this->update([
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'permanently_failed' : 'failed',
            'attempts' => $attempts,
            'last_error' => substr($e->getMessage(), 0, 2000),
        ]);
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < self::MAX_ATTEMPTS;
    }
}
