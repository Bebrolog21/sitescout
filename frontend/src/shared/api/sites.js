// src/shared/api/sites.js
import { apiGet, apiPost, apiPut, apiPatch, apiDelete } from "./http.js";
import {
  normalizeSite,
  normalizeSiteCollection,
} from "../lib/normalizeSite.js";

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
