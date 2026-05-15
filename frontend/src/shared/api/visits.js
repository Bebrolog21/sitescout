// src/shared/api/visits.js
import { apiGet, apiPost, apiPut, apiDelete } from './http.js';

export function fetchSiteVisits(siteId) {
  return apiGet(`/sites/${siteId}/visits`);
}

export function createVisit(siteId, data) {
  return apiPost(`/sites/${siteId}/visits`, data);
}

export function updateVisit(visitId, data) {
  return apiPut(`/visits/${visitId}`, data);
}

export function deleteVisit(visitId) {
  return apiDelete(`/visits/${visitId}`);
}
