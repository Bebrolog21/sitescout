<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Доводит схему БД до уровня ТЗ:
 *   • FK на site_visits.visited_by_user_id (nullable + nullOnDelete)
 *   • CHECK-ограничения на enum-поля и числовые диапазоны (PostgreSQL).
 *
 * CHECK-ограничения добавляются только на PostgreSQL — SQLite (используется
 * в Pest in-memory тестах) не поддерживает `ALTER TABLE ADD CONSTRAINT CHECK`.
 * Для тестового контура валидация enum-значений делается на уровне FormRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ── 1. Очистка «битых» строк перед FK / CHECK ────────────────
        DB::table('site_visits')
            ->whereNotIn('visited_by_user_id', DB::table('users')->pluck('id'))
            ->delete();

        DB::table('site_checklist_values')->where('value', '>', 5)->update(['value' => 5]);
        DB::table('checklist_items')->where('weight', '<', 1)->update(['weight' => 1]);
        DB::table('checklist_items')->where('weight', '>', 10)->update(['weight' => 10]);
        DB::table('sites')->where('area_m2', '<=', 0)->update(['area_m2' => 1]);

        // Старый сидер записывал risks.type = 'regulatory', чего нет в ТЗ.
        // Семантически 'regulatory' ближе всего к 'legal'.
        DB::table('risks')->where('type', 'regulatory')->update(['type' => 'legal']);

        // Гарантия enum-доменов перед CHECK: всё, что не входит в список, → значение по умолчанию.
        DB::table('users')->whereNotIn('role', ['admin', 'analyst', 'manager'])->update(['role' => 'analyst']);
        DB::table('sites')->whereNotIn('status', ['new', 'screening', 'inspection', 'scoring', 'negotiation', 'approved', 'rejected', 'launched', 'archived'])->update(['status' => 'new']);
        DB::table('sites')->whereNotIn('site_type', ['yard', 'parking', 'tech_zone', 'other'])->update(['site_type' => 'other']);
        DB::table('sites')->whereNotIn('owner_type', ['management_company', 'developer', 'municipality', 'private'])->update(['owner_type' => 'private']);
        DB::table('checklist_items')->whereNotIn('category', ['access', 'security', 'visibility', 'legal', 'engineering', 'neighbors', 'sales'])->update(['category' => 'access']);
        DB::table('risks')->whereNotIn('type', ['legal', 'security', 'neighbors', 'engineering', 'competition', 'finance', 'other'])->update(['type' => 'other']);
        DB::table('risks')->whereNotIn('severity', ['low', 'medium', 'high', 'critical'])->update(['severity' => 'low']);
        DB::table('risks')->whereNotIn('probability', ['low', 'medium', 'high'])->update(['probability' => 'low']);
        DB::table('attachments')->whereNotIn('entity_type', ['site', 'visit', 'risk'])->update(['entity_type' => 'site']);
        DB::table('attachments')->whereNotIn('kind', ['photo', 'scheme', 'document', 'other'])->update(['kind' => 'other']);

        // ── 2. FK на site_visits.visited_by_user_id ──────────────────
        // nullable + nullOnDelete: визит сохраняется в истории, если пользователь удалён,
        // по аналогии с sites.created_by.
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->unsignedBigInteger('visited_by_user_id')->nullable()->change();
        });

        Schema::table('site_visits', function (Blueprint $table): void {
            $table->foreign('visited_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // ── 3. CHECK-ограничения (только PostgreSQL) ─────────────────
        if ($driver === 'pgsql') {
            foreach ($this->checkStatements() as $sql) {
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            foreach ($this->checkDropStatements() as $sql) {
                DB::statement($sql);
            }
        }

        Schema::table('site_visits', function (Blueprint $table): void {
            $table->dropForeign(['visited_by_user_id']);
            $table->unsignedBigInteger('visited_by_user_id')->nullable(false)->change();
        });
    }

    /** @return array<string, string> */
    private function checkStatements(): array
    {
        return [
            'sites_area_m2_positive'            => "ALTER TABLE sites ADD CONSTRAINT sites_area_m2_positive CHECK (area_m2 > 0)",
            'sites_status_check'                => "ALTER TABLE sites ADD CONSTRAINT sites_status_check CHECK (status IN ('new','screening','inspection','scoring','negotiation','approved','rejected','launched','archived'))",
            'sites_type_check'                  => "ALTER TABLE sites ADD CONSTRAINT sites_type_check CHECK (site_type IN ('yard','parking','tech_zone','other'))",
            'sites_owner_type_check'            => "ALTER TABLE sites ADD CONSTRAINT sites_owner_type_check CHECK (owner_type IN ('management_company','developer','municipality','private'))",
            'sites_score_range'                 => "ALTER TABLE sites ADD CONSTRAINT sites_score_range CHECK (site_score BETWEEN 0 AND 100 AND risk_score BETWEEN 0 AND 100)",

            'users_role_check'                  => "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','analyst','manager'))",

            'checklist_items_weight_range'      => "ALTER TABLE checklist_items ADD CONSTRAINT checklist_items_weight_range CHECK (weight BETWEEN 1 AND 10)",
            'checklist_items_category_check'    => "ALTER TABLE checklist_items ADD CONSTRAINT checklist_items_category_check CHECK (category IN ('access','security','visibility','legal','engineering','neighbors','sales'))",

            'site_checklist_values_value_range' => "ALTER TABLE site_checklist_values ADD CONSTRAINT site_checklist_values_value_range CHECK (value BETWEEN 0 AND 5)",

            'risks_type_check'                  => "ALTER TABLE risks ADD CONSTRAINT risks_type_check CHECK (type IN ('legal','security','neighbors','engineering','competition','finance','other'))",
            'risks_severity_check'              => "ALTER TABLE risks ADD CONSTRAINT risks_severity_check CHECK (severity IN ('low','medium','high','critical'))",
            'risks_probability_check'           => "ALTER TABLE risks ADD CONSTRAINT risks_probability_check CHECK (probability IN ('low','medium','high'))",

            'attachments_entity_type_check'     => "ALTER TABLE attachments ADD CONSTRAINT attachments_entity_type_check CHECK (entity_type IN ('site','visit','risk'))",
            'attachments_kind_check'            => "ALTER TABLE attachments ADD CONSTRAINT attachments_kind_check CHECK (kind IN ('photo','scheme','document','other'))",
        ];
    }

    /** @return list<string> */
    private function checkDropStatements(): array
    {
        $map = [
            'sites'                 => ['sites_area_m2_positive', 'sites_status_check', 'sites_type_check', 'sites_owner_type_check', 'sites_score_range'],
            'users'                 => ['users_role_check'],
            'checklist_items'       => ['checklist_items_weight_range', 'checklist_items_category_check'],
            'site_checklist_values' => ['site_checklist_values_value_range'],
            'risks'                 => ['risks_type_check', 'risks_severity_check', 'risks_probability_check'],
            'attachments'           => ['attachments_entity_type_check', 'attachments_kind_check'],
        ];

        $stmts = [];
        foreach ($map as $table => $names) {
            foreach ($names as $name) {
                $stmts[] = "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}";
            }
        }
        return $stmts;
    }
};
