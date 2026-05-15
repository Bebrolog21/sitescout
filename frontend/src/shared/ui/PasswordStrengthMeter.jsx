// src/shared/ui/PasswordStrengthMeter.jsx
// Прогресс-бар сложности пароля + чек-лист требований.
// Принимает уже посчитанный объект из evaluatePassword().

import { PASSWORD_CHECK_LABELS, STRENGTH_LEVELS } from '../lib/passwordStrength.js';

export function PasswordStrengthMeter({ result, show = true }) {
  if (!show || !result) return null;

  const { score, level, checks } = result;
  const segments = STRENGTH_LEVELS.length - 1; // 4 сегмента под score 1..4

  return (
    <div className="pw-strength" aria-live="polite">
      <div className="pw-strength__bars">
        {Array.from({ length: segments }).map((_, i) => (
          <span
            key={i}
            className={`pw-strength__bar ${
              i < score ? `pw-strength__bar--filled tone-${level.tone}` : ''
            }`}
          />
        ))}
      </div>
      <div className={`pw-strength__label tone-${level.tone}`}>
        Сложность: <strong>{level.label}</strong>
      </div>
      <ul className="pw-strength__checks">
        {Object.entries(PASSWORD_CHECK_LABELS).map(([key, label]) => {
          const ok = Boolean(checks[key]);
          return (
            <li
              key={key}
              className={`pw-strength__check ${ok ? 'is-ok' : 'is-todo'}`}
            >
              <span className="pw-strength__mark" aria-hidden="true">
                {ok ? '✓' : '○'}
              </span>
              {label}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
