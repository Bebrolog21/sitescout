<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->unsignedBigInteger('visited_by_user_id');
            $table->text('summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
