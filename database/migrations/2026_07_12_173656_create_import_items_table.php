<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();

            $table->json('raw_data');           // ردیف خام CSV
            $table->json('ai_data')->nullable(); // خروجی هوش مصنوعی
            $table->unsignedBigInteger('woo_product_id')->nullable();

            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'permanently_failed'])
                ->default('pending');

            $table->enum('current_step', ['ai', 'validate', 'create_product', 'create_variations'])
                ->default('ai');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'attempts']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};
