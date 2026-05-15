// src/shared/lib/passwordStrength.js
// Простой эвристический подсчёт сложности пароля для индикатора.
// Не криптостойкая оценка, цель — подсказать пользователю усилить пароль.

const MIN_LENGTH = 8;
const STRONG_LENGTH = 12;

export const STRENGTH_LEVELS = Object.freeze([
  { label: 'Очень слабый', tone: 'danger' },
  { label: 'Слабый',       tone: 'danger' },
  { label: 'Средний',      tone: 'warning' },
  { label: 'Хороший',      tone: 'primary' },
  { label: 'Сильный',      tone: 'success' },
]);

// Минимальный score, при котором разрешаем сохранять пароль.
export const MIN_ACCEPTABLE_SCORE = 2; // «Средний» и выше

export function evaluatePassword(password) {
  const p = String(password ?? '');
  const checks = {
    length:    p.length >= MIN_LENGTH,
    lowercase: /[a-zа-яё]/.test(p),
    uppercase: /[A-ZА-ЯЁ]/.test(p),
    digit:     /\d/.test(p),
    symbol:    /[^A-Za-zА-Яа-яЁё0-9]/.test(p),
  };

  const passed = Object.values(checks).filter(Boolean).length;

  // score 0..4: чем больше выполненных проверок и длиннее пароль, тем выше.
  let score = 0;
  if (passed >= 2) score = 1;
  if (passed >= 3) score = 2;
  if (passed >= 4) score = 3;
  if (passed === 5 && p.length >= STRONG_LENGTH) score = 4;
  if (p.length === 0) score = 0;

  return {
    score,
    level: STRENGTH_LEVELS[score],
    checks,
    isAcceptable: score >= MIN_ACCEPTABLE_SCORE && checks.length,
  };
}

export const PASSWORD_CHECK_LABELS = Object.freeze({
  length:    `Минимум ${MIN_LENGTH} символов`,
  lowercase: 'Строчные буквы (a–я)',
  uppercase: 'Заглавные буквы (A–Я)',
  digit:     'Цифры (0–9)',
  symbol:    'Спецсимволы (!@#$…)',
});
