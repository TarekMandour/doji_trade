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
        Schema::create('analysis', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->enum('type', ['analysis', 'intraday'])->default('analysis');
            $table->foreignId('watchlist_id')
                ->constrained('watchlists')
                ->cascadeOnDelete();

            $table->date('analysis_date');

            $table->string('file_path');

            $table->unsignedInteger('stocks_count')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis');
    }
};
