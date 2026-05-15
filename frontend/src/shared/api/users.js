// src/shared/api/users.js
import { apiDelete, apiGet, apiPatch, apiPost } from './http.js';

export function fetchUsers() {
  return apiGet('/users');
}

export function createUser(data) {
  return apiPost('/users', data);
}

export function updateUserRole(userId, role) {
  return apiPatch(`/users/${userId}/role`, { role });
}

export function deleteUser(userId) {
  return apiDelete(`/users/${userId}`);
}
