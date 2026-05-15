<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('assumptions_json');
            $table->json('results_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_models');
    }
};
