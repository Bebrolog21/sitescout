// src/shared/lib/permissions.js
// Должно совпадать с серверной логикой (App\Http\Middleware\EnsureRole +
// User::canEditSiteContent / canChangeSiteStatus).
// UI-проверки скрывают элементы для удобства; реальное разграничение — на сервере.

import { ROLES } from '../constants/roles.js';

const EDIT_ROLES = [ROLES.ADMIN, ROLES.ANALYST];
const STATUS_ROLES = [ROLES.ADMIN, ROLES.MANAGER];

export function isAdmin(user) {
  return user?.role === ROLES.ADMIN;
}

export function canEditSiteContent(user) {
  return EDIT_ROLES.includes(user?.role);
}

export function canChangeSiteStatus(user) {
  return STATUS_ROLES.includes(user?.role);
}

export function canManageUsers(user) {
  return isAdmin(user);
}

export function canManageChecklistItems(user) {
  return isAdmin(user);
}
