<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedBigInteger('user_id')->nullable(); // منشئ المجموعة (أدمن عادة)
            $table->timestamps();
        });

        Schema::create('watchlist_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('watchlist_id');
            $table->unsignedBigInteger('stock_id');
            $table->unsignedInteger('position')->default(0);
            $table->unique(['watchlist_id', 'stock_id']);
            $table->foreign('watchlist_id')->references('id')->on('watchlists')->onDelete('cascade');
            $table->foreign('stock_id')->references('id')->on('stocks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_stocks');
        Schema::dropIfExists('watchlists');
    }
};