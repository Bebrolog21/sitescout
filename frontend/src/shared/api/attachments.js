// src/shared/api/attachments.js
import { apiGet, apiPost, apiDelete } from './http.js';

export function fetchAttachments(entityType, entityId) {
  return apiGet(`/attachments?entity_type=${entityType}&entity_id=${entityId}`);
}

/**
 * @param {Object} data
 * @param {'site'|'visit'|'risk'} data.entity_type
 * @param {number} data.entity_id
 * @param {'photo'|'scheme'|'document'|'other'} data.kind
 * @param {File} [data.file]   — при выборе режима «загрузить файл»
 * @param {string} [data.url]  — при добавлении внешней ссылки
 */
export function createAttachment(data) {
  const fd = new FormData();
  fd.append('entity_type', data.entity_type);
  fd.append('entity_id', String(data.entity_id));
  fd.append('kind', data.kind);

  if (data.file) {
    fd.append('file', data.file);
  } else if (data.url) {
    fd.append('url', data.url);
  }

  return apiPost('/attachments', fd);
}

export function deleteAttachment(id) {
  return apiDelete(`/attachments/${id}`);
}
