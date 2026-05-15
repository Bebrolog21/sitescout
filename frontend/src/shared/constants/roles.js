// src/shared/constants/roles.js
// Канонический источник правды о ролях на фронте.
// Значения должны совпадать с middleware EnsureRole на бэке.

export const ROLES = Object.freeze({
  ADMIN: 'admin',
  ANALYST: 'analyst',
  MANAGER: 'manager',
});

export const ROLE_LABEL = Object.freeze({
  [ROLES.ADMIN]: 'Администратор',
  [ROLES.ANALYST]: 'Аналитик',
  [ROLES.MANAGER]: 'Менеджер',
});

export const ROLE_BADGE_CLASS = Object.freeze({
  [ROLES.ADMIN]: 'role-admin',
  [ROLES.ANALYST]: 'role-analyst',
  [ROLES.MANAGER]: 'role-manager',
});

// Роли, доступные администратору при создании/редактировании пользователей.
export const ASSIGNABLE_ROLES = Object.freeze([
  { value: ROLES.ADMIN, label: ROLE_LABEL[ROLES.ADMIN] },
  { value: ROLES.ANALYST, label: ROLE_LABEL[ROLES.ANALYST] },
  { value: ROLES.MANAGER, label: ROLE_LABEL[ROLES.MANAGER] },
]);
