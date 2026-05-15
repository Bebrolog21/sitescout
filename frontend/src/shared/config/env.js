// src/shared/config/env.js
// В dev-режиме запросы идут через Vite прокси (/api → http://127.0.0.1:8000/api)
// В prod — через переменную окружения VITE_API_BASE_URL
export const env = {
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL ?? "/api/v1",
  demoEmail: import.meta.env.VITE_API_EMAIL ?? "admin@sitescout.test",
  demoPassword: import.meta.env.VITE_API_PASSWORD ?? "password",
};
