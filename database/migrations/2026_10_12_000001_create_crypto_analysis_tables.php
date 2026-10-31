<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->string('base_asset', 32);
            $table->string('quote_asset', 32)->default('USDT');
            $table->string('status', 24)->default('TRADING');
            $table->unsignedInteger('base_asset_precision')->nullable();
            $table->unsignedInteger('quote_asset_precision')->nullable();
            $table->json('filters_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'quote_asset']);
        });

        Schema::create('crypto_watchlists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crypto_watchlist_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_watchlist_id')->constrained('crypto_watchlists')->cascadeOnDelete();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['crypto_watchlist_id', 'crypto_asset_id'], 'crypto_watchlist_asset_unique');
        });

        Schema::create('crypto_scan_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('crypto_watchlist_id')->nullable()->constrained('crypto_watchlists')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->json('settings_json')->nullable();
            $table->string('metadata_path', 500)->nullable();
            $table->string('results_path', 500)->nullable();
            $table->unsignedInteger('total_assets')->default(0);
            $table->unsignedInteger('processed_assets')->default(0);
            $table->unsignedInteger('accepted_assets')->default(0);
            $table->unsignedInteger('rejected_assets')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('crypto_scan_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_scan_run_id')->constrained('crypto_scan_runs')->cascadeOnDelete();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->string('state', 24);
            $table->string('signal_type', 48)->nullable();
            $table->decimal('entry_price', 28, 12)->nullable();
            $table->decimal('stop_loss', 28, 12)->nullable();
            $table->decimal('target_1', 28, 12)->nullable();
            $table->decimal('target_2', 28, 12)->nullable();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->text('reason')->nullable();
            $table->string('result_json_path', 500)->nullable();
            $table->timestamps();
            $table->unique(['crypto_scan_run_id', 'crypto_asset_id'], 'crypto_scan_asset_unique');
            $table->index(['state', 'signal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_scan_results');
        Schema::dropIfExists('crypto_scan_runs');
        Schema::dropIfExists('crypto_watchlist_assets');
        Schema::dropIfExists('crypto_watchlists');
        Schema::dropIfExists('crypto_assets');
    }
};
