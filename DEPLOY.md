# Деплой SiteScout на Railway (GitHub flow)

Пошаговая инструкция для развёртывания на [Railway](https://railway.app).
Деплой автоматический: `git push origin develop` → Railway собирает и поднимает оба сервиса.

**Репозиторий:** [Bebrolog21/sitescout](https://github.com/Bebrolog21/sitescout)
**Ветка деплоя:** `develop`

Стек:
- `backend/` — Laravel 13 / PHP 8.3 / PostgreSQL / Sanctum (Bearer tokens)
- `frontend/` — Vite/React SPA

Два сервиса в одном Railway-проекте, каждый указывает на свой подкаталог монорепо.

---

## 0. Что нужно подготовить

1. **Аккаунт Railway** ([railway.app](https://railway.app)) с привязанной картой. Стартовый кредит $5, дальше Hobby план $5/мес + потребление.
2. **GitHub-приложение Railway** должно быть установлено на твой аккаунт `Bebrolog21` и иметь доступ к репозиторию `sitescout`. При первом подключении Railway сам предложит установить — соглашаешься, выбираешь "Only select repositories" и даёшь доступ к `sitescout`.
3. **Аккаунт [Resend](https://resend.com)** + API key (для писем сброса пароля).
4. **DaData токен** (опционально, для подсказок адресов) — у тебя уже есть в `frontend/.env.local`.
5. **`APP_KEY`** для Laravel — сгенерируй локально:
   ```powershell
   cd backend
   php artisan key:generate --show
   # скопируй вывод вида: base64:xxxxxxx...
   ```
   Сохрани в менеджер паролей — потеря ключа = потеря зашифрованных данных.

---

## 1. Создание проекта Railway и подключение GitHub

1. Открой [railway.app/new](https://railway.app/new) → **Deploy from GitHub repo**.
2. Если Railway ещё не подключен к GitHub — нажми **Configure GitHub App**, выбери `Bebrolog21/sitescout`, разреши доступ.
3. Назад в Railway → выбери репо `Bebrolog21/sitescout` → нажми **Deploy Now**.
4. Railway создаст проект и **первый сервис** автоматически (без правильного root path — поправим в шаге 2).
5. Переименуй проект: **Settings → Project Name → `sitescout`**.

---

## 2. PostgreSQL plugin

В Railway dashboard внутри проекта:
- **+ New → Database → Add PostgreSQL** — появится сервис `Postgres`.

Он автоматически экспортирует переменные `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `DATABASE_URL` для других сервисов проекта через Service References (`${{Postgres.PGHOST}}`).

---

## 3. Backend service

### 3.1 Настройка сервиса

Если Railway автоматически создал сервис в шаге 1 — переименуй его в `backend`. Иначе: **+ New → GitHub Repo → Bebrolog21/sitescout**.

Открой сервис `backend` → **Settings**:

| Поле | Значение |
|---|---|
| Service Name | `backend` |
| Source Repo | `Bebrolog21/sitescout` |
| Branch | `develop` |
| **Root Directory** | `backend` |
| Builder | Dockerfile (определяется автоматически по наличию `backend/Dockerfile`) |
| Dockerfile Path | `Dockerfile` (относительно root directory) |
| Watch Paths | `backend/**` (пересобирать только если меняется backend; опционально) |

### 3.2 Переменные окружения backend

**Settings → Variables → Raw Editor** — вставь блоком (заменив значения в `<...>`):

```env
APP_NAME=SiteScout
APP_ENV=production
APP_KEY=<base64:... из шага 0>
APP_DEBUG=false
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

FRONTEND_URL=https://<frontend-public-domain>
TRUSTED_PROXIES=*

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=<твой Resend API key>
MAIL_SCHEME=ssl
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME=SiteScout
```

> `${{Postgres.PGHOST}}` и т.п. — это Railway service references. `${{RAILWAY_PUBLIC_DOMAIN}}` — встроенная переменная с публичным доменом сервиса.
> `FRONTEND_URL` заполнишь после шага 4.

### 3.3 Persistent storage (volume)

Service `backend` → **Settings → Volumes → + New Volume**:
- Mount path: `/var/www/html/storage`
- Name: `backend-storage`

Без volume аттачменты пропадают при каждом редеплое.

### 3.4 Сгенерировать публичный домен

Service `backend` → **Settings → Networking → Generate Domain**. Получишь `backend-production-xxxx.up.railway.app`.

### 3.5 Запустить деплой

Railway автоматически начнёт сборку, как только увидит новые настройки. Если нет — **Deployments → Deploy**.

Первая сборка ~5–8 минут (Composer install + Docker build).

### 3.6 Проверка

```powershell
curl https://<backend-domain>/up
# → 200 OK (Laravel built-in health endpoint)

curl https://<backend-domain>/api/v1/
# → JSON со списком эндпоинтов
```

Логи: dashboard → backend → **Deployments → View Logs**. Там должны быть строки об успешных миграциях (`Migrating: ...`, `Migrated: ...`).

### 3.7 Сидинг демо-данных (один раз, опционально)

В dashboard → backend → **⋯ → Open Shell** (или через `railway run --service backend php artisan db:seed` если стоит CLI).

Демо-логин: `admin@sitescout.test / password`.

---

## 4. Frontend service

### 4.1 Создание сервиса

Проект → **+ New → GitHub Repo → Bebrolog21/sitescout**.

Service → **Settings**:

| Поле | Значение |
|---|---|
| Service Name | `frontend` |
| Source Repo | `Bebrolog21/sitescout` |
| Branch | `develop` |
| **Root Directory** | `frontend` |
| Builder | Dockerfile |
| Dockerfile Path | `Dockerfile` |
| Watch Paths | `frontend/**` (опционально) |

### 4.2 Build-time переменные

Vite зашивает `VITE_*` в бандл на этапе сборки. Railway автоматически прокидывает все переменные сервиса как build args в Dockerfile (там объявлены `ARG VITE_API_BASE_URL` / `ARG VITE_DADATA_TOKEN`).

**Settings → Variables:**

```env
VITE_API_BASE_URL=https://<backend-domain>/api/v1
VITE_DADATA_TOKEN=<твой DaData токен>
```

> Любое изменение `VITE_*` требует **пересборки** образа (Railway сделает это автоматически при изменении переменных).

### 4.3 Публичный домен

Service `frontend` → **Settings → Networking → Generate Domain**. Получишь `frontend-production-xxxx.up.railway.app`.

### 4.4 Обновить backend CORS

Вернись в Variables backend-сервиса и установи:
```env
FRONTEND_URL=https://frontend-production-xxxx.up.railway.app
```
Backend передеплоится автоматически.

### 4.5 Проверка

Открой `https://<frontend-domain>` в браузере:
1. Загружается форма логина.
2. DevTools → Network: `POST /api/v1/auth/login` идёт на backend-домен.
3. Логин `admin@sitescout.test / password` → 200 + `token` в ответе.
4. После логина — должны загружаться площадки, ЖК, чеклисты.

---

## 5. Обновления после первого деплоя

Каждый редеплой — это просто `git push` в `develop`:

```powershell
cd "F:\SiteScout (2)\SiteScout"
git add -A
git commit -m "Описание изменений"
git push origin develop
```

Railway сам:
- Замечает push, скачивает новый код
- Пересобирает backend и/или frontend (зависит от Watch Paths)
- Запускает entrypoint `10-laravel-deploy.sh` → миграции применяются автоматически

Логи деплоя видны в dashboard каждого сервиса.

---

## 6. Verification checklist

- [ ] `GET https://<backend>/up` → 200
- [ ] `GET https://<backend>/api/v1/` → JSON с `"status": "ok"`
- [ ] `POST https://<backend>/api/v1/auth/login` с `admin@sitescout.test/password` → `{ "token": "..." }`
- [ ] Открытие `https://<frontend>/` → SPA загружается, нет CORS-ошибок в консоли
- [ ] Логин через UI → видны площадки/ЖК
- [ ] Загрузка фото в аттачмент → файл доступен после redeploy backend (volume работает)
- [ ] Сброс пароля → письмо приходит (проверить в Resend → Logs)

---

## 7. Возможные проблемы

### CORS error в браузере
- Проверь `FRONTEND_URL` в backend Variables: должен точно совпадать с origin фронта (`https://...`, без trailing `/`).
- `config/cors.php` уже стоит `supports_credentials: true`, `allowed_origins` берётся из `FRONTEND_URL`.

### 500 на запросах к API
- Открой логи backend в Railway dashboard.
- Чаще всего: не прокинули `APP_KEY` или DB переменные не подцепились.

### Frontend ходит на `127.0.0.1:8000`
- `VITE_API_BASE_URL` не попал в билд. Проверь Variables сервиса frontend, потом **Redeploy** (пересобрать образ).

### Migration error при старте
- Postgres стартовал позже backend. Просто **Redeploy** backend ещё раз через dashboard.

### Письма не приходят
- Без верифицированного домена в Resend `MAIL_FROM_ADDRESS` должен быть `onboarding@resend.dev`.
- Логи: resend.com → Logs.

### Railway собрал не тот сервис / выкатил frontend как backend
- Проверь **Settings → Root Directory** у обоих сервисов: должно быть `backend` и `frontend` соответственно.

---

## 8. Структура файлов деплоя в репо

```
sitescout/
├── .gitignore
├── DEPLOY.md                       ← эта инструкция
├── README.md
├── backend/
│   ├── Dockerfile                  ← образ Laravel (serversideup/php base)
│   ├── .dockerignore
│   ├── railway.toml                ← healthcheck /up, restart policy
│   └── docker/
│       └── 10-laravel-deploy.sh    ← миграции при старте контейнера
└── frontend/
    ├── Dockerfile                  ← multi-stage Vite build → Nginx static
    ├── .dockerignore
    ├── nginx.conf                  ← SPA fallback, listen $PORT
    └── railway.toml                ← healthcheck /, restart policy
```

---

## 9. Стоимость (ориентир)

Railway Hobby ($5/мес кредит):
- Postgres: ~$2–3/мес при низкой нагрузке
- Backend (PHP-FPM + Nginx): ~$3–5/мес
- Frontend (Nginx static): ~$1–2/мес
- Volume 1 ГБ: $0.25/мес

Реальный счёт для MVP — **$5–10/мес**.
