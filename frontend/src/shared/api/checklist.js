// src/shared/api/checklist.js
import { apiGet, apiPost, apiPut } from './http.js';

export function fetchChecklistItems() {
  return apiGet('/checklist-items');
}

export function createChecklistItem(data) {
  return apiPost('/checklist-items', data);
}

export function updateChecklistItem(itemId, data) {
  return apiPut(`/checklist-items/${itemId}`, data);
}

export function fetchSiteChecklist(siteId) {
  return apiGet(`/sites/${siteId}/checklist`);
}

export function updateSiteChecklist(siteId, items) {
  return apiPut(`/sites/${siteId}/checklist`, { items });
}

export function recalculateSiteScore(siteId) {
  return apiPost(`/sites/${siteId}/recalculate-score`, {});
}
