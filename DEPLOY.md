# Деплой SiteScout на Railway

Пошаговая инструкция для развёртывания на [Railway](https://railway.app) через CLI.

Стек: backend (Laravel 13 / PHP 8.3 / PostgreSQL) + frontend (Vite/React SPA), деплоятся как **два отдельных сервиса** в одном Railway-проекте. Источник кода — `railway up` из локальной папки (GitHub не используется).

---

## 0. Что нужно подготовить

1. **Аккаунт Railway** с привязанной картой (Hobby план $5/мес — бесплатный кредит трачивается за ~3 недели на одном проекте).
2. **Railway CLI**:
   ```powershell
   iwr -useb https://railway.app/install.ps1 | iex
   # или: npm i -g @railway/cli
   railway --version
   railway login
   ```
3. **Аккаунт [Resend](https://resend.com)** + API key (для писем сброса пароля). Без верификации домена можно слать только с `onboarding@resend.dev`.
4. **DaData токен** (опционально, для подсказок адресов) — у тебя уже есть в `frontend/.env.local`.
5. **`APP_KEY`** для Laravel — сгенерируй локально:
   ```powershell
   cd backend
   php artisan key:generate --show
   # скопируй вывод вида: base64:xxxxxxx... — сохрани в менеджер паролей
   ```

---

## 1. Создание проекта и PostgreSQL

```powershell
# из корня репо
railway login
railway init
# → выбери "Empty Project", назови "sitescout"
```

В [Railway Dashboard](https://railway.app/dashboard) открой проект:
- **+ New → Database → Add PostgreSQL** — появится сервис `Postgres`.

---

## 2. Деплой backend

```powershell
cd backend
railway link
# выбери проект sitescout → создать новый сервис → имя: "backend"
```

### 2.1 Переменные окружения backend

Через dashboard (Service `backend` → Variables) или CLI (`railway variables --set KEY=VALUE`):

| Переменная | Значение |
|---|---|
| `APP_NAME` | `SiteScout` |
| `APP_ENV` | `production` |
| `APP_KEY` | `base64:...` (из шага 0) |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<backend-public-domain>` (заполнишь после шага 2.3) |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `info` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `${{Postgres.PGHOST}}` |
| `DB_PORT` | `${{Postgres.PGPORT}}` |
| `DB_DATABASE` | `${{Postgres.PGDATABASE}}` |
| `DB_USERNAME` | `${{Postgres.PGUSER}}` |
| `DB_PASSWORD` | `${{Postgres.PGPASSWORD}}` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `FRONTEND_URL` | `https://<frontend-public-domain>` (после шага 3.3) |
| `TRUSTED_PROXIES` | `*` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp.resend.com` |
| `MAIL_PORT` | `465` |
| `MAIL_USERNAME` | `resend` |
| `MAIL_PASSWORD` | твой Resend API key |
| `MAIL_SCHEME` | `ssl` |
| `MAIL_FROM_ADDRESS` | `onboarding@resend.dev` (или свой домен после верификации) |
| `MAIL_FROM_NAME` | `SiteScout` |

> `${{Postgres.PGHOST}}` и т.д. — это Railway service references, они подставляются автоматически. Имя `Postgres` должно совпадать с именем сервиса БД (по умолчанию так).

### 2.2 Persistent storage (volume)

В Railway dashboard: Service `backend` → **Settings → Volumes → + New Volume**:
- Mount path: `/var/www/html/storage`
- Name: `backend-storage`

Без volume аттачменты будут пропадать при каждом редеплое.

### 2.3 Сборка и деплой

```powershell
# по-прежнему в backend/
railway up
```

Это запакует папку (с учётом `.dockerignore`), отправит на Railway, соберёт по `Dockerfile`. Сборка ~5–8 минут в первый раз.

После успешной сборки: dashboard → backend → **Settings → Networking → Generate Domain**. Получишь что-то вроде `backend-production-abcd.up.railway.app`. Скопируй — это твой backend URL.

Вернись в Variables и обнови `APP_URL=https://backend-production-abcd.up.railway.app`. Сервис автоматически передеплоится.

### 2.4 Проверка

```powershell
curl https://<backend-domain>/up
# → должен вернуть 200 OK (Laravel built-in health endpoint)

curl https://<backend-domain>/api/v1/
# → JSON со списком эндпоинтов
```

Посмотри логи: `railway logs --service backend` — там должны быть строки об успешных миграциях.

### 2.5 Сидинг (один раз, опционально)

Демо-данные (3 ЖК, 10 площадок, демо-пользователь):

```powershell
railway run --service backend php artisan db:seed
```

Демо-логин: `admin@sitescout.test / password`.

---

## 3. Деплой frontend

```powershell
cd ..\frontend
railway link
# выбери проект sitescout → создать новый сервис → имя: "frontend"
```

### 3.1 Build-time переменные

Vite зашивает `VITE_*` переменные в бандл на этапе сборки. В Railway они должны быть **обычными Variables** (Railway автоматически прокидывает их как build args в Dockerfile через `ARG VITE_API_BASE_URL` / `ARG VITE_DADATA_TOKEN`):

| Переменная | Значение |
|---|---|
| `VITE_API_BASE_URL` | `https://<backend-domain>/api/v1` (домен из шага 2.3) |
| `VITE_DADATA_TOKEN` | твой DaData токен (из `frontend/.env.local`) |

### 3.2 Сборка и деплой

```powershell
railway up
```

### 3.3 Получить публичный домен

Dashboard → frontend → **Settings → Networking → Generate Domain**. Получишь `frontend-production-xxxx.up.railway.app`.

### 3.4 Обновить backend CORS

Вернись в Variables backend-сервиса и установи:
```
FRONTEND_URL=https://frontend-production-xxxx.up.railway.app
```
Backend передеплоится автоматически.

### 3.5 Проверка

Открой `https://<frontend-domain>` в браузере:
1. Должна загрузиться форма логина.
2. DevTools → Network: запрос `POST /api/v1/auth/login` уходит на backend-домен.
3. Логин `admin@sitescout.test / password` → должен вернуть 200 и `token`.
4. После логина — должны загружаться площадки, ЖК, чеклисты.

---

## 4. Обновления после первого деплоя

Каждый редеплой:

```powershell
# Backend (включая миграции — выполнятся автоматически через entrypoint hook)
cd backend
railway up

# Frontend (если поменялся VITE_API_BASE_URL — нужна пересборка)
cd ..\frontend
railway up
```

---

## 5. Verification checklist

- [ ] `GET https://<backend>/up` → 200
- [ ] `GET https://<backend>/api/v1/` → JSON с `"status": "ok"`
- [ ] `POST https://<backend>/api/v1/auth/login` с `admin@sitescout.test/password` → `{ "token": "..." }`
- [ ] Открытие `https://<frontend>/` → SPA загружается, нет CORS-ошибок в консоли
- [ ] Логин через UI → видны площадки/ЖК
- [ ] Загрузка фото в аттачмент → файл доступен после `railway redeploy --service backend` (volume работает)
- [ ] Сброс пароля → письмо приходит (проверить в Resend → Logs)

---

## 6. Возможные проблемы

### CORS error в браузере
- Проверь, что в backend `FRONTEND_URL` точно совпадает с origin фронта (включая `https://`, без trailing `/`).
- В `config/cors.php` уже стоит `supports_credentials: true` и `allowed_origins` берётся из `FRONTEND_URL` — менять не надо.

### 500 на запросах к API
- `railway logs --service backend` — смотри stack trace.
- Чаще всего: не прокинули `APP_KEY` или DB переменные.

### Frontend ходит на `127.0.0.1:8000`
- Значит `VITE_API_BASE_URL` не попал в билд. Проверь, что переменная установлена в Service `frontend`, **до** запуска `railway up` (build args читаются на этапе сборки).
- Передеплой: `railway up` пересоберёт образ с новым значением.

### Migration error при старте
- Скорее всего сервис стартанул раньше, чем Postgres стал доступен. Railway обычно сам ждёт, но иногда первый деплой нужно просто перезапустить: dashboard → backend → ⋯ → Redeploy.

### Письма не приходят
- Проверь логи Resend (resend.com → Logs).
- Без верифицированного домена `MAIL_FROM_ADDRESS` должен быть `onboarding@resend.dev`, иначе Resend отклонит письмо.

---

## 7. Стоимость (ориентир)

Railway Hobby ($5/мес кредит):
- Postgres: ~$2–3/мес при низкой нагрузке
- Backend (PHP-FPM + Nginx): ~$3–5/мес
- Frontend (Nginx static): ~$1–2/мес
- Volume 1 ГБ: $0.25/мес

Реальный счёт для MVP — **$5–10/мес**.
