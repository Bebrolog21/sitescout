<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\ChecklistItem;
use App\Models\ResidentialComplex;
use App\Models\Site;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\RiskService;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;

/**
 * Демо-данные для SiteScout — 3 ЖК, 10 площадок, 12 пунктов чеклиста,
 * 20 рисков, 30 фото-вложений, 10 финмоделей (часть «хороших», часть «плохих»).
 * Все площадки — в Омске или Новосибирске.
 */
class DemoDataSeeder extends Seeder
{
    public function run(
        ScoringService $scoring,
        RiskService $risk,
        FinanceService $finance
    ): void {
        // Идемпотентность: ResidentialComplex — маркер демо-данных.
        // Если он уже есть — считаем, что демо-набор уже залит, и выходим.
        if (ResidentialComplex::query()->exists()) {
            $this->command?->info('Демо-данные уже есть, пропускаем DemoDataSeeder.');
            return;
        }

        $authorId = User::query()->where('role', 'admin')->value('id')
                 ?? User::query()->value('id');

        $complexes = $this->seedComplexes();
        $this->seedChecklist();
        $sites = $this->seedSites($complexes, $authorId);

        $this->seedFinance($sites, $finance);
        $this->seedRisks($sites);
        $this->seedChecklistValues($sites);
        $this->seedAttachments($sites);
        $this->seedVisits($sites, $authorId);

        foreach ($sites as $site) {
            $scoring->recalculateSiteScore($site->fresh('checklistValues.checklistItem'));
            $risk->recalculateSiteRiskScore($site->fresh('risks'));
        }
    }

    /** @return array<int, ResidentialComplex> */
    private function seedComplexes(): array
    {
        return [
            ResidentialComplex::create([
                'name'           => 'ЖК «Серебряный берег»',
                'city'           => 'Омск',
                'district'       => 'Кировский',
                'address'        => 'ул. Дианова, 8',
                'lat'            => 54.9853,
                'lng'            => 73.2789,
                'developer_name' => 'СК «Стройбетон»',
                'notes'          => 'Сданы 4 очереди из 6, активное заселение, паркинги переполнены.',
            ]),
            ResidentialComplex::create([
                'name'           => 'ЖК «Парковый»',
                'city'           => 'Омск',
                'district'       => 'Советский',
                'address'        => 'ул. Андрианова, 12',
                'lat'            => 55.0341,
                'lng'            => 73.3520,
                'developer_name' => 'ПСК «Полёт»',
                'notes'          => 'Большие закрытые дворы, спрос на хранение сезонной утвари.',
            ]),
            ResidentialComplex::create([
                'name'           => 'ЖК «Радуга»',
                'city'           => 'Новосибирск',
                'district'       => 'Заельцовский',
                'address'        => 'ул. Дуси Ковальчук, 270',
                'lat'            => 55.0541,
                'lng'            => 82.9217,
                'developer_name' => 'ГК «Энергомонтаж»',
                'notes'          => 'Дома комфорт-класса, инфраструктура развитая, конкуренции мало.',
            ]),
        ];
    }

    /**
     * 12 пунктов: 4 категории × 3 пункта.
     * Категории: access (Доступ), legal (Юр. вопросы), engineering (Инженерия), sales (Спрос).
     */
    private function seedChecklist(): void
    {
        $now = now();
        ChecklistItem::insert([
            ['code' => 'ACC_01', 'title' => 'Доступ для грузовика',                 'category' => 'access',      'weight' => 8,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ACC_02', 'title' => 'Парковка для клиентов',                 'category' => 'access',      'weight' => 6,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ACC_03', 'title' => 'Удобный въезд и разворот',              'category' => 'access',      'weight' => 7,  'created_at' => $now, 'updated_at' => $now],

            ['code' => 'LEG_01', 'title' => 'Согласие собственника',                 'category' => 'legal',       'weight' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LEG_02', 'title' => 'Нет правовых обременений',              'category' => 'legal',       'weight' => 9,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LEG_03', 'title' => 'Подходящее зонирование',                'category' => 'legal',       'weight' => 8,  'created_at' => $now, 'updated_at' => $now],

            ['code' => 'ENG_01', 'title' => 'Подключение к электросети',             'category' => 'engineering', 'weight' => 8,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ENG_02', 'title' => 'Состояние грунта и покрытия',           'category' => 'engineering', 'weight' => 7,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ENG_03', 'title' => 'Дренаж и водоотведение',                'category' => 'engineering', 'weight' => 6,  'created_at' => $now, 'updated_at' => $now],

            ['code' => 'SAL_01', 'title' => 'Плотность жилой застройки рядом',       'category' => 'sales',       'weight' => 8,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SAL_02', 'title' => 'Пешеходный/автомобильный трафик',       'category' => 'sales',       'weight' => 6,  'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SAL_03', 'title' => 'Слабая конкуренция в радиусе 1 км',     'category' => 'sales',       'weight' => 7,  'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /** @return array<int, Site> */
    private function seedSites(array $complexes, ?int $authorId): array
    {
        [$serebryanyy, $parkovyy, $raduga] = $complexes;

        $rows = [
            // ── Серебряный берег (Омск, Кировский) ────────────────────────────
            [
                'complex' => $serebryanyy,
                'title'   => 'Двор у первой очереди',
                'city'    => 'Омск', 'district' => 'Кировский',
                'address' => 'ул. Дианова, 8к1',
                'lat'     => 54.9854, 'lng' => 73.2784,
                'site_type'    => 'yard', 'area_m2' => 180,
                'status'       => 'approved',
                'owner_type'   => 'management_company',
                'contact_name' => 'Сергей Котов', 'contact_phone' => '+7 (913) 401-23-45',
            ],
            [
                'complex' => $serebryanyy,
                'title'   => 'Гостевая парковка у 2-й очереди',
                'city'    => 'Омск', 'district' => 'Кировский',
                'address' => 'ул. Дианова, 12',
                'lat'     => 54.9871, 'lng' => 73.2812,
                'site_type'    => 'parking', 'area_m2' => 220,
                'status'       => 'scoring',
                'owner_type'   => 'developer',
                'contact_name' => 'Ирина Петрова', 'contact_phone' => '+7 (913) 645-21-09',
            ],
            [
                'complex' => $serebryanyy,
                'title'   => 'Техзона у котельной',
                'city'    => 'Омск', 'district' => 'Кировский',
                'address' => 'ул. Лукашевича, 14А',
                'lat'     => 54.9802, 'lng' => 73.2698,
                'site_type'    => 'tech_zone', 'area_m2' => 240,
                'status'       => 'rejected',
                'owner_type'   => 'municipality',
                'contact_name' => 'Олег Краснов', 'contact_phone' => '+7 (904) 765-43-21',
            ],

            // ── Парковый (Омск, Советский) ────────────────────────────────────
            [
                'complex' => $parkovyy,
                'title'   => 'Закрытый двор корпуса 3',
                'city'    => 'Омск', 'district' => 'Советский',
                'address' => 'ул. Андрианова, 12к3',
                'lat'     => 55.0334, 'lng' => 73.3518,
                'site_type'    => 'yard', 'area_m2' => 160,
                'status'       => 'launched',
                'owner_type'   => 'management_company',
                'contact_name' => 'Анна Дёмина', 'contact_phone' => '+7 (904) 320-15-77',
            ],
            [
                'complex' => $parkovyy,
                'title'   => 'Парковка у школы № 47',
                'city'    => 'Омск', 'district' => 'Советский',
                'address' => 'пр. Мира, 32',
                'lat'     => 55.0285, 'lng' => 73.3399,
                'site_type'    => 'parking', 'area_m2' => 200,
                'status'       => 'negotiation',
                'owner_type'   => 'private',
                'contact_name' => 'Дмитрий Леонов', 'contact_phone' => '+7 (913) 612-08-44',
            ],
            [
                'complex' => $parkovyy,
                'title'   => 'Пустырь у 4-й очереди',
                'city'    => 'Омск', 'district' => 'Советский',
                'address' => 'ул. Заозёрная, 24',
                'lat'     => 55.0419, 'lng' => 73.3691,
                'site_type'    => 'yard', 'area_m2' => 130,
                'status'       => 'inspection',
                'owner_type'   => 'developer',
                'contact_name' => null, 'contact_phone' => null,
            ],

            // ── Радуга (Новосибирск, Заельцовский) ────────────────────────────
            [
                'complex' => $raduga,
                'title'   => 'Парковка у первой очереди',
                'city'    => 'Новосибирск', 'district' => 'Заельцовский',
                'address' => 'ул. Дуси Ковальчук, 270',
                'lat'     => 55.0552, 'lng' => 82.9221,
                'site_type'    => 'parking', 'area_m2' => 250,
                'status'       => 'approved',
                'owner_type'   => 'management_company',
                'contact_name' => 'Виктор Иванов', 'contact_phone' => '+7 (383) 286-31-12',
            ],
            [
                'complex' => $raduga,
                'title'   => 'Техзона у трансформаторной',
                'city'    => 'Новосибирск', 'district' => 'Заельцовский',
                'address' => 'ул. Северная, 17',
                'lat'     => 55.0589, 'lng' => 82.9301,
                'site_type'    => 'tech_zone', 'area_m2' => 140,
                'status'       => 'screening',
                'owner_type'   => 'municipality',
                'contact_name' => 'Артём Соколов', 'contact_phone' => '+7 (913) 945-22-66',
            ],

            // ── Отдельные площадки без ЖК ─────────────────────────────────────
            [
                'complex' => null,
                'title'   => 'Парковка ТЦ «Каскад»',
                'city'    => 'Омск', 'district' => 'Центральный',
                'address' => 'ул. Карла Маркса, 5',
                'lat'     => 54.9881, 'lng' => 73.3711,
                'site_type'    => 'parking', 'area_m2' => 280,
                'status'       => 'new',
                'owner_type'   => 'private',
                'contact_name' => 'Мария Власова', 'contact_phone' => '+7 (923) 999-10-15',
            ],
            [
                'complex' => null,
                'title'   => 'Двор у бизнес-центра',
                'city'    => 'Новосибирск', 'district' => 'Центральный',
                'address' => 'Красный проспект, 86',
                'lat'     => 55.0413, 'lng' => 82.9183,
                'site_type'    => 'yard', 'area_m2' => 110,
                'status'       => 'screening',
                'owner_type'   => 'private',
                'contact_name' => 'Павел Орлов', 'contact_phone' => '+7 (923) 712-34-56',
            ],
        ];

        $sites = [];
        foreach ($rows as $row) {
            $complex = $row['complex'];
            unset($row['complex']);
            $row['residential_complex_id'] = $complex?->id;
            $row['created_by'] = $authorId;
            $sites[] = Site::create($row);
        }

        return $sites;
    }

    /**
     * 10 финансовых моделей — 5 «хороших» (высокая занятость, разумный CAPEX)
     * и 5 «плохих» (низкая занятость / высокий CAPEX).
     */
    private function seedFinance(array $sites, FinanceService $finance): void
    {
        $price = ['2m2' => 4500, '3m2' => 6500, '5m2' => 9000];

        $profiles = [
            // good — payback < 18 мес, ROI здоровый
            ['containers_count' => 6, 'units_per_container' => 8, 'occupancy_percent' => 78, 'capex_total' => 2_100_000, 'opex_total' => 110_000],
            ['containers_count' => 5, 'units_per_container' => 8, 'occupancy_percent' => 82, 'capex_total' => 1_900_000, 'opex_total' => 95_000],
            ['containers_count' => 7, 'units_per_container' => 8, 'occupancy_percent' => 75, 'capex_total' => 2_400_000, 'opex_total' => 125_000],
            ['containers_count' => 6, 'units_per_container' => 8, 'occupancy_percent' => 80, 'capex_total' => 2_200_000, 'opex_total' => 115_000],
            ['containers_count' => 4, 'units_per_container' => 8, 'occupancy_percent' => 76, 'capex_total' => 1_600_000, 'opex_total' => 90_000],

            // bad — низкая загрузка или раздутый CAPEX
            ['containers_count' => 3, 'units_per_container' => 8, 'occupancy_percent' => 38, 'capex_total' => 2_800_000, 'opex_total' => 145_000],
            ['containers_count' => 5, 'units_per_container' => 8, 'occupancy_percent' => 42, 'capex_total' => 3_400_000, 'opex_total' => 170_000],
            ['containers_count' => 4, 'units_per_container' => 8, 'occupancy_percent' => 45, 'capex_total' => 3_100_000, 'opex_total' => 165_000],
            ['containers_count' => 6, 'units_per_container' => 8, 'occupancy_percent' => 35, 'capex_total' => 3_600_000, 'opex_total' => 190_000],
            ['containers_count' => 4, 'units_per_container' => 8, 'occupancy_percent' => 50, 'capex_total' => 3_000_000, 'opex_total' => 155_000],
        ];

        foreach ($sites as $i => $site) {
            $assumptions = $profiles[$i] + ['price_per_unit' => $price];
            $site->finance()->create([
                'assumptions_json' => $assumptions,
                'results_json'     => $finance->calculate($assumptions),
            ]);
        }
    }

    /** 20 рисков — по 2 на каждую из 10 площадок, разной серьёзности. */
    private function seedRisks(array $sites): void
    {
        // [type, severity, probability, description, mitigation|null]
        $pairs = [
            // Site 0 — approved, риски низкие и закрыты
            [
                ['legal',       'low',      'low',     'УК запросила доп. согласование собрания собственников.',           'Заявка в УК подана 12.04.2026, ответ через неделю.'],
                ['engineering', 'low',      'medium',  'Электросеть рядом, нужна отдельная подстанция для подключения.',   'Заявка в РЭС подана, ответ 25.05.2026.'],
            ],
            // Site 1 — scoring
            [
                ['neighbors', 'medium', 'medium',  'Возможны жалобы жильцов на шум при ночном завозе.', 'Завоз согласован только с 09:00 до 19:00.'],
                ['legal',     'low',    'low',     'УК пока не подписала договор аренды.',              'Договор на согласовании у юристов УК.'],
            ],
            // Site 2 — rejected: критический риск без митигации (приводит к блокировке approve)
            [
                ['legal',     'critical', 'high',  'Аренда земли у муниципалитета истекает 06.2026, продление под вопросом.', null],
                ['neighbors', 'medium',   'medium','Жильцы 2-го этажа выражали недовольство шумом на собрании.',              null],
            ],
            // Site 3 — launched, всё закрыто
            [
                ['engineering', 'low', 'low', 'Дренаж требует прочистки перед запуском.',                       'Подрядчик нанят, работы 10.05.2026.'],
                ['legal',       'low', 'low', 'Нужна табличка с режимом работы и контактами по 44-ФЗ.',          'Табличка изготовлена и установлена.'],
            ],
            // Site 4 — negotiation
            [
                ['legal',       'medium', 'medium', 'Зонирование коммерческое, но требуется согласование с архитектурой.', null],
                ['engineering', 'medium', 'medium', 'Уклон участка более 5%, нужно выравнивание.',                         'Смета подрядчика: 180 000 руб., работы в плане.'],
            ],
            // Site 5 — inspection
            [
                ['engineering', 'low', 'medium', 'Состояние асфальта местами требует подсыпки.',                              null],
                ['legal',       'low', 'medium', 'Кадастровый паспорт обновлён в 2018 г., может потребоваться актуализация.', null],
            ],
            // Site 6 — approved
            [
                ['neighbors',   'medium', 'low',    'Близко к школе, нужно согласовать график завоза.', 'Школа согласовала график 19:00–21:00.'],
                ['engineering', 'low',    'low',    'Зимой подъезд может быть затруднён без расчистки.', 'Договор с УК на расчистку приложен.'],
            ],
            // Site 7 — screening: высокий риск (легальный, без митигации)
            [
                ['legal',       'high',     'medium', 'Право собственности на земельный участок оспаривается в суде.', null],
                ['legal',       'critical', 'high',   'Зона санитарной охраны водоисточника — запрет на размещение.',   null],
            ],
            // Site 8 — new
            [
                ['neighbors',   'low',  'low',    'Жилой дом в 30 метрах — потенциальные жалобы.',     null],
                ['neighbors',   'medium','medium','Близко к детской площадке — возможны претензии.',   null],
            ],
            // Site 9 — screening
            [
                ['legal',       'medium','high',  'Историческая зона города — могут запретить контейнеры.', 'Юрист готовит запрос в Управление архитектуры.'],
                ['engineering', 'low',   'medium','Нет ливневой канализации, нужна локальная система.',     null],
            ],
        ];

        foreach ($sites as $i => $site) {
            foreach ($pairs[$i] as [$type, $severity, $probability, $desc, $mitigation]) {
                $site->risks()->create([
                    'type'        => $type,
                    'severity'    => $severity,
                    'probability' => $probability,
                    'description' => $desc,
                    'mitigation'  => $mitigation,
                ]);
            }
        }
    }

    /**
     * Заполняет чеклист на каждой площадке по заранее заданному «профилю»,
     * чтобы видеть spread от плохой к хорошей в категориях.
     */
    private function seedChecklistValues(array $sites): void
    {
        $items = ChecklistItem::query()->orderBy('id')->get();

        // 12 значений на площадку по порядку: ACC_01..03, LEG_01..03, ENG_01..03, SAL_01..03
        $profiles = [
            [5, 4, 5, 5, 5, 4, 5, 4, 4, 5, 4, 4],   // 0 approved
            [4, 4, 3, 4, 3, 4, 3, 4, 3, 4, 3, 3],   // 1 scoring
            [2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 2, 1],   // 2 rejected
            [5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5],   // 3 launched
            [4, 3, 4, 4, 4, 3, 4, 3, 3, 4, 3, 4],   // 4 negotiation
            [3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3],   // 5 inspection
            [5, 5, 5, 5, 4, 5, 5, 4, 4, 5, 4, 5],   // 6 approved
            [3, 2, 3, 2, 2, 3, 2, 3, 2, 3, 2, 2],   // 7 screening (legal issue)
            [3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 3],   // 8 new
            [4, 4, 4, 3, 3, 4, 4, 3, 3, 4, 3, 4],   // 9 screening
        ];

        foreach ($sites as $i => $site) {
            $values = $profiles[$i];
            foreach ($items as $j => $item) {
                $site->checklistValues()->create([
                    'checklist_item_id' => $item->id,
                    'value'             => $values[$j] ?? 3,
                ]);
            }
        }
    }

    /** 30 фото — по 3 на каждую площадку. */
    private function seedAttachments(array $sites): void
    {
        $photos = [
            'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1000',
            'https://images.unsplash.com/photo-1448630360428-65456885c650?w=1000',
            'https://images.unsplash.com/photo-1542621334-a254cf47733d?w=1000',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1000',
            'https://images.unsplash.com/photo-1599643477877-530eb83abc8e?w=1000',
            'https://images.unsplash.com/photo-1554435493-93422e8d1a41?w=1000',
            'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1000',
            'https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=1000',
            'https://images.unsplash.com/photo-1486325212027-8081e485255e?w=1000',
        ];

        foreach ($sites as $site) {
            for ($k = 0; $k < 3; $k++) {
                Attachment::create([
                    'entity_type'   => 'site',
                    'entity_id'     => $site->id,
                    'kind'          => 'photo',
                    'path'          => $photos[($site->id * 3 + $k) % count($photos)],
                    'original_name' => "site_{$site->id}_photo_".($k + 1).'.jpg',
                    'mime_type'     => 'image/jpeg',
                    'size'          => 0,
                ]);
            }
        }
    }

    private function seedVisits(array $sites, ?int $authorId): void
    {
        $summaries = [
            'Первичный осмотр. Сделаны замеры по периметру, фотографии общего вида и подъездных путей.',
            'Повторный визит — встреча с представителем УК. Согласованы условия размещения, обсудили завоз.',
            'Замеры освещения и тестирование подъезда грузового транспорта. Все нормативы по доступу — OK.',
            'Встреча с собственником. Подтверждено согласие. Обсудили долгосрочную аренду 5+5 лет.',
            'Осмотр после дождя — проверили дренаж и водоотведение. Локальные подтопления зафиксированы на фото.',
        ];

        foreach ($sites as $i => $site) {
            $site->visits()->create([
                'visit_date'         => now()->subDays(($i + 1) * 3)->toDateString(),
                'visited_by_user_id' => $authorId,
                'summary'            => $summaries[$i % count($summaries)],
            ]);
        }
    }
}
