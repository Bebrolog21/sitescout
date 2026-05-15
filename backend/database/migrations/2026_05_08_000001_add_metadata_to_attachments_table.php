<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('size')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropColumn(['original_name', 'mime_type', 'size']);
        });
    }
};
