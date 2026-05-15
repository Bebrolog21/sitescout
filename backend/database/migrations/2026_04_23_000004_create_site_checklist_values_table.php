<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_checklist_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('value');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_checklist_values');
    }
};
