// src/shared/api/sites.js
import { apiGet, apiPost, apiPut, apiPatch, apiDelete, getToken } from "./http.js";
import {
  normalizeSite,
  normalizeSiteCollection,
} from "../lib/normalizeSite.js";

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "/api/v1";

export async function fetchSites(params = {}) {
  const query = new URLSearchParams(
    Object.entries(params).filter(
      ([, v]) => v !== "" && v !== null && v !== undefined,
    ),
  ).toString();
  const payload = await apiGet(`/sites${query ? `?${query}` : ""}`);
  return normalizeSiteCollection(payload);
}

export async function fetchSite(siteId) {
  const payload = await apiGet(`/sites/${siteId}`);
  return normalizeSite(payload);
}

export async function createSite(data) {
  const payload = await apiPost("/sites", data);
  return normalizeSite(payload);
}

export async function updateSite(siteId, data) {
  const payload = await apiPut(`/sites/${siteId}`, data);
  return normalizeSite(payload);
}

export async function updateSiteStatus(siteId, status) {
  const payload = await apiPatch(`/sites/${siteId}/status`, { status });
  return normalizeSite(payload);
}

export async function fetchSitePassport(siteId) {
  return apiGet(`/sites/${siteId}/passport`);
}

// Скачивает PDF-паспорт через fetch (минуем JSON-обёртку http.js — нужен blob).
// Имя файла берём из Content-Disposition, fallback — passport-site-{id}.pdf.
export async function downloadSitePassportPdf(siteId) {
  const token = getToken();
  let tz = "";
  try {
    tz = Intl.DateTimeFormat().resolvedOptions().timeZone ?? "";
  } catch (_) {
    // браузер не поддерживает Intl — отправим без tz, бэк возьмёт серверную
  }
  const qs = tz ? `?tz=${encodeURIComponent(tz)}` : "";
  const response = await fetch(`${BASE_URL}/sites/${siteId}/passport.pdf${qs}`, {
    headers: {
      Accept: "application/pdf",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  if (!response.ok) {
    let message = "Не удалось сформировать PDF";
    try {
      const data = await response.json();
      if (data?.message) message = data.message;
    } catch (_) {
      // не JSON — оставляем дефолтное сообщение
    }
    const err = new Error(message);
    err.status = response.status;
    throw err;
  }

  const blob = await response.blob();
  const disposition = response.headers.get("Content-Disposition") ?? "";
  const match = /filename\*?=(?:UTF-8'')?["']?([^"';]+)/i.exec(disposition);
  const filename = match ? decodeURIComponent(match[1]) : `passport-site-${siteId}.pdf`;

  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 500);
}
