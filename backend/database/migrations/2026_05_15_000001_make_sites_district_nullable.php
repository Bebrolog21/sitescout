<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // У части российских адресов района нет (DaData/Nominatim не отдают).
        // Снимаем NOT NULL, чтобы можно было сохранить такие площадки.
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('district', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('district', 120)->nullable(false)->change();
        });
    }
};
