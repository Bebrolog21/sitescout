# SiteScout

Внутренний веб-сервис подбора и оценки площадок под self-storage контейнеры. Воронка площадок, скоринг по чек-листу, расчёт юнит-экономики, риск-менеджмент и финальный go/no-go-вердикт по простому правилу.

---

## Стек

| Слой     | Технологии                                                                                                                |
|----------|---------------------------------------------------------------------------------------------------------------------------|
| Backend  | PHP 8.3+ · Laravel 13 · Sanctum (Bearer-токены) · PostgreSQL                                                              |
| Frontend | React 18 · Vite · React Router 6 · Tailwind base (минимально) + кастомный CSS (тёмная тема) · Recharts                    |
| Адреса   | [DaData](https://dadata.ru/) Suggestions API (через `VITE_DADATA_TOKEN`)                                                  |
| Тесты    | Pest 4 · SQLite in-memory                                                                                                  |
| Email    | Mailtrap (sandbox) — для писем сброса пароля                                                                              |

---

## Быстрый старт

### 1. PostgreSQL

```sql
CREATE DATABASE sitescout;
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
# заполнить DB_* и MAIL_USERNAME / MAIL_PASSWORD (Mailtrap)
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

API доступен по `http://127.0.0.1:8000/api/v1`.

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env.local
# вписать VITE_DADATA_TOKEN (см. ниже)
npm run dev
```

UI поднимется на `http://localhost:5173` с проксированием `/api/*` на бэк.

### 4. Токен DaData (опционально, но рекомендовано)

Без токена строка адреса работает как обычный инпут — без подсказок.

1. Зарегистрироваться на [dadata.ru](https://dadata.ru/).
2. В **Профиль → API → Ключи** скопировать токен **Suggestions** (его можно безопасно класть в JS, ограничен только подсказками).
3. `frontend/.env.local`:
   ```
   VITE_DADATA_TOKEN=<токен>
   ```
4. Перезапустить `npm run dev`.

Free-тариф: **10 000 подсказок в день** — для дев-окружения с запасом.

---

## Демо-аккаунты (после `--seed`)

| Роль    | Email                          | Пароль   |
|---------|--------------------------------|----------|
| Admin   | admin@sitescout.test           | password |

Остальные пользователи создаются админом через UI («Пользователи» → «+ Новый пользователь»). Им выдаётся **временный пароль**; при первом входе backend (middleware `password.changed`) блокирует всё, кроме экрана смены пароля.

---

## Seed-данные (`migrate:fresh --seed`)

Все площадки — в **Омске** или **Новосибирске**:

| Сущность              | Количество | Заметки                                                                            |
|-----------------------|-----------:|------------------------------------------------------------------------------------|
| Жилые комплексы       |          3 | Серебряный берег / Парковый (Омск) и Радуга (Новосибирск)                          |
| Площадки              |         10 | Все 8 статусов воронки представлены                                                |
| Пункты чеклиста       |         12 | 4 категории × 3 пункта (access, legal, engineering, sales)                        |
| Риски                 |         20 | 2 на площадку; 2 критических без митигации — для проверки блокировки `approved`    |
| Финансовые модели     |         10 | 5 «хороших» (payback ≤ 16 мес, ROI ≈ 80%) + 5 «плохих» (отрицательный GP)         |
| Фото-вложения         |         30 | Unsplash, 3 на площадку                                                            |
| Визиты                |         10 | По одному на площадку                                                              |

---

## Структура проекта

```
SiteScout/
├── backend/                                # Laravel 13
│   ├── app/
│   │   ├── Enums/SiteStatus.php            # new → screening → ... → approved/rejected/launched/archived
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/         # 10 контроллеров под /api/v1/*
│   │   │   ├── Middleware/
│   │   │   │   ├── EnsureRole.php          # RBAC: admin / analyst / manager
│   │   │   │   └── EnsurePasswordChanged.php
│   │   │   └── Requests/                   # Form Requests с русскими сообщениями
│   │   ├── Models/                         # 9 Eloquent моделей
│   │   ├── Services/
│   │   │   ├── ScoringService.php          # site_score из чеклиста
│   │   │   ├── RiskService.php             # risk_score из рисков
│   │   │   ├── FinanceService.php          # выручка / GP / payback / ROI
│   │   │   └── PassportService.php         # go/no-go-решение
│   │   └── Notifications/ResetPasswordNotification.php
│   ├── database/
│   │   ├── factories/                      # UserFactory, SiteFactory (для тестов)
│   │   ├── migrations/                     # 17 миграций
│   │   └── seeders/DemoDataSeeder.php
│   ├── openapi/openapi.yaml                # OpenAPI 3.0 — актуальная спецификация всех роутов
│   └── routes/api.php
│
└── frontend/                               # React 18 + Vite
    └── src/
        ├── main.jsx                        # Root: ErrorBoundary → BrowserRouter → App
        ├── app/App.jsx                     # Маршрутизация + sidebar
        ├── features/
        │   ├── auth/                       # Login, Forgot/Reset/ChangeInitial password
        │   ├── dashboard/DashboardPage.jsx # KPI, воронка, лучшая площадка, короткий список
        │   ├── admin/UsersPage.jsx
        │   └── sites/
        │       ├── SitesPage.jsx           # реестр + вкладки Активные/Архив
        │       ├── SiteDetailRoute.jsx     # маршрут /sites/:id/:tab
        │       ├── SiteDetailPage.jsx      # шапка + 6 вкладок
        │       └── tabs/
        │           ├── OverviewTab.jsx
        │           ├── ChecklistTab.jsx
        │           ├── RisksTab.jsx
        │           ├── FinanceTab.jsx
        │           ├── FilesTab.jsx
        │           └── VisitsTab.jsx
        ├── shared/
        │   ├── api/                        # http.js + по модулю на сущность
        │   ├── constants/roles.js          # ROLES, ROLE_LABEL, ROLE_BADGE_CLASS
        │   ├── lib/                        # calculations, formatters, addressFormat, normalizeSite, permissions
        │   └── ui/
        │       ├── AddressAutocomplete.jsx # DaData Suggestions API
        │       ├── ErrorBoundary.jsx
        │       ├── PhoneInput.jsx          # маска +7 (XXX) XXX-XX-XX
        │       ├── StatusBadge.jsx
        │       ├── StatCard.jsx
        │       └── Icons.jsx
        └── styles/index.css
```

---

## Ключевые UX-фичи

- **Глубокие ссылки** через React Router: `/sites/:id/overview`, `/sites/:id/checklist`, `/sites/:id/finance`, и т. д. Refresh страницы / прямой URL работают корректно.
- **ErrorBoundary** на корне приложения: один кривой компонент не кладёт всю SPA.
- **Деление на сессии**: пункт «Паспорт» в сайдбаре активен только после первого открытия площадки в текущей сессии.
- **Вкладка «Архив»**: площадки в статусе `archived` пропадают из всех метрик (дашборд, среднее, выручка, шортлист, лучшая) и сидят на отдельной вкладке.
- **Адресный автокомплит** через DaData: одно поле «Город, район, адрес» с автозаполнением города/района/координат.
- **Маска телефона** `+7 (XXX) XXX-XX-XX` с `tel:`-ссылкой в карточке.
- **Прогресс-бары** на плитках Балл/Риск с градиентами синий→зелёный и жёлтый→красный.

---

## API (краткий справочник)

> Полная спецификация в [`backend/openapi/openapi.yaml`](backend/openapi/openapi.yaml).

### Auth

```
POST   /auth/login                          # → { token, user }
POST   /auth/logout
GET    /auth/me
POST   /auth/forgot-password                # шлёт письмо через Mailtrap
POST   /auth/reset-password                 # token + email + password + password_confirmation
POST   /auth/change-initial-password        # обязательная смена временного пароля
```

### Пользователи (только admin)

```
GET    /users
POST   /users                               # { name, email, temporary_password, role }
PATCH  /users/{user}/role
DELETE /users/{user}
```

### Площадки

```
GET    /sites?status=&district=&min_score=&q=     # архив скрыт по умолчанию
POST   /sites                                     # admin / analyst
GET    /sites/{id}
PUT    /sites/{id}                                # admin / analyst
DELETE /sites/{id}                                # admin / analyst
PATCH  /sites/{id}/status                         # admin / manager — валидация go/no-go
GET    /sites/{id}/passport                       # итоговое go/no-go
GET    /sites/{id}/stats                          # visits_count / risks_count / attachments_count
```

### Чеклист

```
GET    /checklist-items
POST   /checklist-items                     # admin
PUT    /checklist-items/{id}                # admin
GET    /sites/{id}/checklist
PUT    /sites/{id}/checklist                # bulk + пересчёт site_score
POST   /sites/{id}/recalculate-score
```

### Риски

```
GET    /sites/{id}/risks
POST   /sites/{id}/risks                    # admin / analyst
PUT    /risks/{id}                          # admin / analyst
DELETE /risks/{id}                          # admin / analyst
```

### Финансы

```
GET    /sites/{id}/finance
PUT    /sites/{id}/finance                  # сохранить допущения + пересчитать
POST   /sites/{id}/finance/recalculate
GET    /sites/{id}/finance/summary
```

### Визиты

```
GET    /sites/{id}/visits
POST   /sites/{id}/visits                   # admin / analyst
GET    /visits/{id}
PUT    /visits/{id}                         # admin / analyst
DELETE /visits/{id}                         # admin / analyst
```

### Файлы

```
GET    /attachments?entity_type=&entity_id=
POST   /attachments                         # multipart: file ИЛИ url
DELETE /attachments/{id}
```

### ЖК

```
GET    /residential-complexes
GET    /residential-complexes/{id}
POST   /residential-complexes               # admin / analyst
PUT    /residential-complexes/{id}          # admin / analyst
```

---

## Бизнес-логика

### Скоринг площадки (0–100)

```
site_score = Σ(value × weight) / Σ(5 × weight) × 100
```

Где `value ∈ [0..5]` — оценка по каждому пункту чеклиста, `weight ∈ [1..10]` — вес пункта из справочника.

### Риск-скор (0–100)

```
severity:    low=1, medium=2, high=3, critical=4
probability: low=1, medium=2, high=3

risk_score = Σ(severity × probability) / (count × 12) × 100
```

### Финансовая модель

```
avg_price        = avg(price_per_unit values)
monthly_revenue  = containers × units × avg_price × occupancy%
monthly_gp       = monthly_revenue − opex_total
payback_months   = capex_total / monthly_gp                            # null если GP ≤ 0
roi_12m          = monthly_gp × 12 / capex_total × 100%
breakeven_occupancy = opex_total / monthly_revenue × 100%
```

### Решение go/no-go (паспорт)

```
recommended = site_score ≥ 70 ∧ risk_score ≤ 50 ∧ payback_months ≤ 18
```

### Жёсткие блокировки при переводе в `approved`

При `PATCH /sites/{id}/status` со `status=approved` проверяется:
1. `site_score > 0` — иначе **«Перед согласованием нужно рассчитать балл площадки.»**
2. Есть `finance.results_json` — иначе **«Перед согласованием нужно рассчитать финансовую модель.»**
3. Нет критических рисков без `mitigation` — иначе **«У площадки есть критические риски без плана митигации.»**

---

## Роли и права (RBAC)

| Действие                          | admin | analyst | manager |
|-----------------------------------|:-----:|:-------:|:-------:|
| Просматривать площадки и аналитику|   ✓   |    ✓    |    ✓    |
| Создавать / редактировать площадки|   ✓   |    ✓    |         |
| Заполнять чеклист                 |   ✓   |    ✓    |         |
| Добавлять/править риски           |   ✓   |    ✓    |         |
| Править финмодель                 |   ✓   |    ✓    |         |
| Загружать файлы и визиты          |   ✓   |    ✓    |         |
| Менять статус площадки            |   ✓   |         |    ✓    |
| Управлять пользователями          |   ✓   |         |         |
| Управлять справочником чеклиста   |   ✓   |         |         |

UI-проверки скрывают элементы для удобства; **реальное разграничение — на сервере** через middleware `role:...`.

---

## Тесты

```bash
cd backend
php artisan test
```

Покрытие через Pest + SQLite in-memory: создание площадок, пересчёт скоринга, валидация статуса, RBAC.

---

## Полезные ссылки

- OpenAPI спецификация: [`backend/openapi/openapi.yaml`](backend/openapi/openapi.yaml) — можно открыть в Swagger UI / Stoplight Studio / Redocly.
- ТЗ проекта: `ТЗ Проект A (solo).docx` (в корне репозитория).
