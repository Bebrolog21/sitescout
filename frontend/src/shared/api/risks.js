// src/shared/api/risks.js
import { apiGet, apiPost, apiPut, apiDelete } from './http.js';

export function fetchSiteRisks(siteId) {
  return apiGet(`/sites/${siteId}/risks`);
}

export function createRisk(siteId, data) {
  return apiPost(`/sites/${siteId}/risks`, data);
}

export function updateRisk(riskId, data) {
  return apiPut(`/risks/${riskId}`, data);
}

export function deleteRisk(riskId) {
  return apiDelete(`/risks/${riskId}`);
}
