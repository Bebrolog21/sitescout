// src/shared/api/finance.js
import { apiGet, apiPut, apiPost } from './http.js';

export function fetchSiteFinance(siteId) {
  return apiGet(`/sites/${siteId}/finance`);
}

export function updateSiteFinance(siteId, assumptions) {
  return apiPut(`/sites/${siteId}/finance`, assumptions);
}

export function recalculateFinance(siteId) {
  return apiPost(`/sites/${siteId}/finance/recalculate`, {});
}

export function fetchFinanceSummary(siteId) {
  return apiGet(`/sites/${siteId}/finance/summary`);
}
