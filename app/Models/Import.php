<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    /**
     * بررسی و به‌روزرسانی وضعیت کلی ایمپورت بر اساس وضعیت آیتم‌هاش.
     * وقتی همه‌ی آیتم‌ها به success یا permanently_failed رسیده باشن، تمام‌شده حساب می‌شه.
     */
    public function refreshStatus(): void
    {
        $unfinishedExists = $this->items()
            ->whereNotIn('status', ['success', 'permanently_failed'])
            ->exists();

        $processed = $this->items()->where('status', 'success')->count();
        $this->processed_rows = $processed;

        if (! $unfinishedExists) {
            $hasFailures = $this->items()->where('status', 'permanently_failed')->exists();
            $this->status = $hasFailures ? 'completed' : 'completed';
            // اگه می‌خوای وضعیت جدا برای "تکمیل با خطا" داشته باشی
            // می‌تونی یه enum جدید مثل completed_with_errors اضافه کنی.
        } else {
            $this->status = 'processing';
        }

        $this->save();
    }
}
