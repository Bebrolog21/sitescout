<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('residential_complex_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('city');
            $table->string('district');
            $table->string('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('site_type');
            $table->decimal('area_m2', 10, 2);
            $table->string('status')->default('new');
            $table->string('owner_type');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('site_score')->default(0);
            $table->unsignedInteger('risk_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
