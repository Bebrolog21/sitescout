// src/shared/api/http.js
// HTTP-клиент для работы с Laravel Sanctum API

const TOKEN_KEY = "sitescout_token";
const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "/api/v1";

// ── Токен ────────────────────────────────────────────────────

export function getToken() {
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
  window.localStorage.removeItem(TOKEN_KEY);
}

export function isAuthenticated() {
  return Boolean(getToken());
}

// ── Базовый запрос ────────────────────────────────────────────

async function request(path, options = {}) {
  const token = getToken();
  const headers = new Headers(options.headers ?? {});
  const isFormData = options.body instanceof FormData;

  if (!isFormData && !headers.has("Content-Type") && options.body) {
    headers.set("Content-Type", "application/json");
  }
  // Для FormData Content-Type выставит сам браузер вместе с boundary.
  headers.set("Accept", "application/json");
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${BASE_URL}${path}`, { ...options, headers });

  if (response.status === 401) {
    clearToken();
    window.location.reload();
    return null;
  }

  if (response.status === 204) return null;

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(data.message ?? "Ошибка запроса к API");
    error.status = response.status;
    error.errors = data.errors ?? {};
    throw error;
  }

  return data;
}

// ── Auth ─────────────────────────────────────────────────────

export async function login(email, password) {
  const data = await request("/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  if (data?.token) setToken(data.token);
  return data;
}

export async function logout() {
  await request("/auth/logout", { method: "POST" });
  clearToken();
}

export function getMe() {
  return request("/auth/me");
}

export function forgotPassword(email) {
  return request("/auth/forgot-password", {
    method: "POST",
    body: JSON.stringify({ email }),
  });
}

export function resetPassword({ email, token, password, password_confirmation }) {
  return request("/auth/reset-password", {
    method: "POST",
    body: JSON.stringify({ email, token, password, password_confirmation }),
  });
}

export function changeInitialPassword({ password, password_confirmation }) {
  return request("/auth/change-initial-password", {
    method: "POST",
    body: JSON.stringify({ password, password_confirmation }),
  });
}

// ── CRUD ─────────────────────────────────────────────────────

export function apiGet(path) {
  return request(path);
}

export function apiPost(path, body) {
  const payload = body instanceof FormData ? body : JSON.stringify(body);
  return request(path, { method: "POST", body: payload });
}

export function apiPut(path, body) {
  return request(path, { method: "PUT", body: JSON.stringify(body) });
}

export function apiPatch(path, body) {
  return request(path, { method: "PATCH", body: JSON.stringify(body) });
}

export function apiDelete(path) {
  return request(path, { method: "DELETE" });
}
