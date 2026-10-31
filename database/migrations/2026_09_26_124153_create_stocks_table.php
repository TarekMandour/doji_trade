<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {

        $table->id();

        // Provider identifiers
        $table->uuid('asset_id')->unique();
     
        $table->string('symbol', 20)->unique();

        $table->string('isin', 20)->nullable()->unique()
            ->comment('International Securities Identification Number (ISIN)');

        // Basic information
        $table->string('name');

        $table->string('arabic_name')->nullable();

        $table->text('description')->nullable();

        // Market classification
        $table->string('market', 30)->default('egypt');

        $table->string('exchange', 30)->nullable();

        $table->string('asset_class', 30)->default('STOCK');

        $table->string('industry')->nullable();

        // External identifiers
        $table->string('reuters_symbol')->nullable();

        // Media
        $table->string('logo')->nullable();

        // Trading status
        $table->boolean('is_tradable')->default(true)
            ->comment('Whether the stock is currently tradable');

        $table->boolean('is_visible')->default(true)
            ->comment('Whether the stock should be visible in the application');

        $table->boolean('is_otc')->default(false)
            ->comment('Whether the stock is traded over the counter (OTC)');

        $table->boolean('is_right')->default(false)
            ->comment('Whether this instrument represents subscription rights');

        $table->boolean('is_ipo')->default(false)
            ->comment('Whether the stock is related to an IPO');

        $table->boolean('is_same_day')->default(false);

        // Sharia compliance
        $table->boolean('is_sharia_compliant')->default(false);

        // Index membership
        $table->boolean('is_egx30')->default(false);

        $table->boolean('is_egx70')->default(false);

        $table->boolean('is_egx100')->default(false);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
