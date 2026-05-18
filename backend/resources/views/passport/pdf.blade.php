@php
    // Локализация перечислений (повторяем подписи фронта).
    $statusLabels = [
        'new' => 'Новая', 'screening' => 'Скрининг', 'inspection' => 'Осмотр',
        'scoring' => 'Скоринг', 'negotiation' => 'Согласование',
        'approved' => 'Согласована', 'rejected' => 'Отклонена',
        'launched' => 'Запущена', 'archived' => 'В архиве',
    ];
    $siteTypeLabels = [
        'parking' => 'Парковка', 'yard' => 'Двор',
        'tech_zone' => 'Техзона', 'other' => 'Другое',
    ];
    $ownerLabels = [
        'management_company' => 'Управляющая компания',
        'developer' => 'Застройщик',
        'private' => 'Частный владелец',
        'municipality' => 'Муниципалитет',
    ];
    $riskTypeLabels = [
        'legal' => 'Юридический', 'security' => 'Безопасность',
        'neighbors' => 'Соседи', 'engineering' => 'Инженерия',
        'competition' => 'Конкуренция', 'finance' => 'Финансы', 'other' => 'Прочее',
    ];
    $severityLabels = ['low' => 'Низкая', 'medium' => 'Средняя', 'high' => 'Высокая', 'critical' => 'Критическая'];
    $severityColors = ['low' => '#6BCB77', 'medium' => '#FFD93D', 'high' => '#FF8C42', 'critical' => '#FF4757'];
    $probabilityLabels = ['low' => 'Низкая', 'medium' => 'Средняя', 'high' => 'Высокая'];

    $rub = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ');
    $pct = fn ($v) => $v === null ? '—' : ((int) $v).' %';

    $isRecommended = $decision === 'recommended';
    $finRes = $finance['results'] ?? [];
    $finAss = $finance['assumptions'] ?? [];

    // Разбиваем CAPEX/OPEX из assumptions, если поля есть.
    $capexFields = $finAss['capex'] ?? [];
    $opexFields = $finAss['opex'] ?? [];
    $capexLabels = [
        'container' => 'Покупка контейнера', 'delivery' => 'Доставка',
        'retrofit' => 'Переоборудование', 'site_prep' => 'Подготовка площадки',
        'security' => 'Замки/освещение/камера', 'other' => 'Прочее',
    ];
    $opexLabels = [
        'land_rent' => 'Аренда/УК', 'security' => 'Охрана/камера',
        'maintenance' => 'Обслуживание', 'marketing' => 'Маркетинг', 'other' => 'Прочее',
    ];
@endphp
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Паспорт площадки #{{ $site['id'] }}</title>
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1F2937; line-height: 1.35; }
        h1 { font-size: 18pt; margin: 0 0 4px; color: #0F172A; }
        h2 { font-size: 12pt; margin: 14px 0 6px; color: #0F172A;
             border-bottom: 1px solid #E5E7EB; padding-bottom: 3px; }
        h3 { font-size: 10pt; margin: 8px 0 4px; color: #374151; }
        .muted { color: #6B7280; font-size: 9pt; }
        .header-row { width: 100%; border-collapse: collapse; }
        .header-row td { vertical-align: top; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 8.5pt; font-weight: bold; }
        .b-rec { background: #DCFCE7; color: #166534; }
        .b-not { background: #FEE2E2; color: #991B1B; }
        .b-status { background: #E0E7FF; color: #3730A3; }

        table.kpi { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.kpi td { width: 25%; border: 1px solid #E5E7EB; padding: 10px; text-align: center; }
        table.kpi .label { font-size: 8.5pt; color: #6B7280; text-transform: uppercase; letter-spacing: 0.4px; }
        table.kpi .value { font-size: 16pt; font-weight: bold; color: #111827; margin-top: 4px; display: block; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data td, table.data th { border: 1px solid #E5E7EB; padding: 5px 7px; vertical-align: top; }
        table.data th { background: #F3F4F6; text-align: left; font-size: 9pt; color: #374151; }
        table.data td.num { text-align: right; white-space: nowrap; }

        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { border: 1px solid #E5E7EB; padding: 5px 7px; }
        table.kv td.k { background: #F9FAFB; width: 38%; color: #6B7280; }

        .scorebar { background: #E5E7EB; height: 8px; border-radius: 4px; position: relative; }
        .scorebar > span { display: block; height: 8px; border-radius: 4px; background: #3B82F6; }

        .decision-box { border: 2px solid; padding: 12px 14px; margin-top: 6px; border-radius: 4px; }
        .decision-box.yes { border-color: #16A34A; background: #F0FDF4; }
        .decision-box.no  { border-color: #DC2626; background: #FEF2F2; }
        .decision-box h2 { border: 0; padding: 0; margin: 0 0 6px; }

        .checklist-cat { background: #F9FAFB; font-weight: bold; }

        .footer-mark { text-align: center; color: #9CA3AF; font-size: 8pt; margin-top: 14px; }
    </style>
</head>
<body>

{{-- ── Шапка ───────────────────────────────────────────────── --}}
<table class="header-row">
    <tr>
        <td>
            <div class="muted">Паспорт площадки · #{{ $site['id'] }} · {{ $generated_at->format('d.m.Y H:i') }}</div>
            <h1>{{ $site['title'] }}</h1>
            <div class="muted">
                {{ $site['city'] }}@if(!empty($site['district'])), {{ $site['district'] }}@endif
                @if(!empty($site['address'])) · {{ $site['address'] }}@endif
            </div>
        </td>
        <td style="width: 180px; text-align: right;">
            <span class="badge b-status">{{ $statusLabels[$site['status']] ?? $site['status'] }}</span><br>
            <span class="badge {{ $isRecommended ? 'b-rec' : 'b-not' }}" style="margin-top:6px;">
                {{ $isRecommended ? '✓ Рекомендуется' : '✗ Не рекомендуется' }}
            </span>
        </td>
    </tr>
</table>

{{-- ── KPI ─────────────────────────────────────────────────── --}}
<table class="kpi">
    <tr>
        <td>
            <div class="label">Балл оценки</div>
            <span class="value">{{ $site_score }}%</span>
        </td>
        <td>
            <div class="label">Риск-скор</div>
            <span class="value">{{ $risk_score }}%</span>
        </td>
        <td>
            <div class="label">Окупаемость</div>
            <span class="value">{{ isset($finRes['payback_months']) && $finRes['payback_months'] !== null ? $finRes['payback_months'].' мес' : 'н/д' }}</span>
        </td>
        <td>
            <div class="label">Выручка / мес</div>
            <span class="value" style="font-size: 12pt;">{{ $rub($finRes['monthly_revenue'] ?? null) }}</span>
        </td>
    </tr>
</table>

{{-- ── Описание ─────────────────────────────────────────────── --}}
<h2>Описание площадки</h2>
<table class="kv">
    <tr><td class="k">Тип площадки</td><td>{{ $siteTypeLabels[$site['site_type']] ?? $site['site_type'] }}</td></tr>
    <tr><td class="k">Тип владельца</td><td>{{ $ownerLabels[$site['owner_type']] ?? ($site['owner_type'] ?? '—') }}</td></tr>
    <tr><td class="k">Площадь</td><td>{{ $site['area_m2'] }} м²</td></tr>
    @if(!empty($site['lat']) && !empty($site['lng']))
        <tr><td class="k">Координаты</td><td>{{ number_format((float) $site['lat'], 4, '.', '') }}, {{ number_format((float) $site['lng'], 4, '.', '') }}</td></tr>
    @endif
    @if(!empty($site['contact_name']) || !empty($site['contact_phone']))
        <tr><td class="k">Контакт</td><td>{{ trim(($site['contact_name'] ?? '').' '.($site['contact_phone'] ? '· '.$site['contact_phone'] : '')) }}</td></tr>
    @endif
</table>

{{-- ── Скоринг по категориям ───────────────────────────────── --}}
@if(!empty($category_scores))
    <h2>Скоринг по категориям</h2>
    <table class="data">
        <thead>
            <tr><th style="width:40%;">Категория</th><th style="width:15%;">Балл</th><th>Прогресс</th></tr>
        </thead>
        <tbody>
            @foreach($category_scores as $cat)
                @php $pctVal = max(0, min(100, (int) $cat['score'])); @endphp
                <tr>
                    <td>{{ $cat['label'] }}</td>
                    <td class="num"><b>{{ $cat['score'] }} %</b></td>
                    <td>
                        <div class="scorebar"><span style="width: {{ $pctVal }}%; background: hsl({{ round($pctVal * 1.2) }}, 65%, 50%);"></span></div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ── Чеклист (полный) ─────────────────────────────────────── --}}
@if(!empty($checklist))
    <h2>Чеклист оценки</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:54%;">Пункт</th>
                <th style="width:10%;">Оценка</th>
                <th style="width:10%;">Вес</th>
                <th>Комментарий</th>
            </tr>
        </thead>
        <tbody>
            @php $prevCat = null; @endphp
            @foreach($checklist as $row)
                @if($row['category'] !== $prevCat)
                    <tr class="checklist-cat"><td colspan="4">{{ $row['category_label'] }}</td></tr>
                    @php $prevCat = $row['category']; @endphp
                @endif
                <tr>
                    <td>{{ $row['title'] }}</td>
                    <td class="num">{{ $row['value'] }} / 5</td>
                    <td class="num">{{ $row['weight'] }}</td>
                    <td>{{ $row['comment'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ── Риски ────────────────────────────────────────────────── --}}
@if(!empty($risks))
    <h2>Риски</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:18%;">Тип</th>
                <th style="width:14%;">Severity</th>
                <th style="width:14%;">Вероятность</th>
                <th>Описание / митигация</th>
            </tr>
        </thead>
        <tbody>
            @foreach($risks as $risk)
                @php $sev = $risk['severity'] ?? 'low'; @endphp
                <tr>
                    <td>{{ $riskTypeLabels[$risk['type'] ?? 'other'] ?? $risk['type'] }}</td>
                    <td>
                        <span class="badge" style="background: {{ $severityColors[$sev] ?? '#E5E7EB' }}; color: #111827;">
                            {{ $severityLabels[$sev] ?? $sev }}
                        </span>
                    </td>
                    <td>{{ $probabilityLabels[$risk['probability'] ?? 'low'] ?? $risk['probability'] }}</td>
                    <td>
                        {{ $risk['description'] ?? '' }}
                        @if(!empty($risk['mitigation']))<br><span class="muted">Митигация: {{ $risk['mitigation'] }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ── Финансовая модель ──────────────────────────────────── --}}
<h2>Финансовая модель</h2>

@if(empty($finAss) && empty($finRes))
    <p class="muted">Финансовая модель ещё не рассчитана.</p>
@else
    <h3>Входные параметры</h3>
    <table class="kv">
        <tr><td class="k">Контейнеров</td><td>{{ $num($finAss['containers_count'] ?? null) }}</td></tr>
        <tr><td class="k">Тип контейнера</td><td>{{ $finAss['container_type'] ?? '—' }}</td></tr>
        <tr><td class="k">Кладовок на контейнер</td><td>{{ $num($finAss['units_per_container'] ?? null) }}</td></tr>
        <tr><td class="k">Плановая загрузка</td><td>{{ isset($finAss['occupancy']) ? round($finAss['occupancy'] * (($finAss['occupancy'] <= 1) ? 100 : 1)).' %' : '—' }}</td></tr>
        @if(!empty($finAss['avg_price']))
            <tr><td class="k">Средняя цена / мес</td><td>{{ $rub($finAss['avg_price']) }}</td></tr>
        @endif
    </table>

    @if(!empty($capexFields))
        <h3>CAPEX</h3>
        <table class="data">
            <thead><tr><th>Статья</th><th style="width:30%;">Сумма</th></tr></thead>
            <tbody>
                @php $capexTotal = 0; @endphp
                @foreach($capexFields as $code => $amount)
                    @php $capexTotal += (float) $amount; @endphp
                    <tr><td>{{ $capexLabels[$code] ?? $code }}</td><td class="num">{{ $rub($amount) }}</td></tr>
                @endforeach
                <tr><td><b>Итого CAPEX</b></td><td class="num"><b>{{ $rub($capexTotal) }}</b></td></tr>
            </tbody>
        </table>
    @endif

    @if(!empty($opexFields))
        <h3>OPEX (в месяц)</h3>
        <table class="data">
            <thead><tr><th>Статья</th><th style="width:30%;">Сумма</th></tr></thead>
            <tbody>
                @php $opexTotal = 0; @endphp
                @foreach($opexFields as $code => $amount)
                    @php $opexTotal += (float) $amount; @endphp
                    <tr><td>{{ $opexLabels[$code] ?? $code }}</td><td class="num">{{ $rub($amount) }}</td></tr>
                @endforeach
                <tr><td><b>Итого OPEX / мес</b></td><td class="num"><b>{{ $rub($opexTotal) }}</b></td></tr>
            </tbody>
        </table>
    @endif

    <h3>Итоги</h3>
    <table class="kv">
        <tr><td class="k">Выручка / мес</td><td>{{ $rub($finRes['monthly_revenue'] ?? null) }}</td></tr>
        <tr><td class="k">Валовая прибыль / мес</td><td>{{ $rub($finRes['monthly_gross_profit'] ?? null) }}</td></tr>
        <tr>
            <td class="k">Окупаемость</td>
            <td>
                @if(isset($finRes['payback_months']) && $finRes['payback_months'] !== null)
                    {{ $finRes['payback_months'] }} мес
                @else
                    <span style="color:#DC2626;"><b>Не окупается</b></span>
                @endif
            </td>
        </tr>
        <tr><td class="k">ROI 12 мес</td><td>{{ $pct($finRes['roi_12m'] ?? null) }}</td></tr>
        <tr><td class="k">Точка безубыточности (occupancy)</td><td>{{ $pct($finRes['breakeven_occupancy'] ?? null) }}</td></tr>
    </table>
@endif

{{-- ── Решение ──────────────────────────────────────────────── --}}
<div class="decision-box {{ $isRecommended ? 'yes' : 'no' }}">
    <h2>Решение: {{ $isRecommended ? 'Рекомендуется' : 'Не рекомендуется' }}</h2>
    <div class="muted" style="margin-bottom: 6px;">Правило: балл ≥ 70, риск-скор ≤ 50, окупаемость ≤ 18 мес.</div>
    <table class="kv">
        <tr>
            <td class="k">Балл оценки ≥ 70</td>
            <td>{{ $decision_rules['score_ok'] ? '✓' : '✗' }} текущий: {{ $site_score }}%</td>
        </tr>
        <tr>
            <td class="k">Риск-скор ≤ 50</td>
            <td>{{ $decision_rules['risk_ok'] ? '✓' : '✗' }} текущий: {{ $risk_score }}%</td>
        </tr>
        <tr>
            <td class="k">Окупаемость ≤ 18 мес</td>
            <td>
                {{ $decision_rules['payback_ok'] ? '✓' : '✗' }}
                @if(isset($finRes['payback_months']) && $finRes['payback_months'] !== null)
                    текущая: {{ $finRes['payback_months'] }} мес
                @else
                    текущая: не рассчитана
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="footer-mark">
    Сгенерировано SiteScout · {{ $generated_at->format('d.m.Y H:i') }}
</div>

</body>
</html>
