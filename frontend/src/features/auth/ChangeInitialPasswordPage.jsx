// src/features/auth/ChangeInitialPasswordPage.jsx
import { useMemo, useState } from 'react';
import { changeInitialPassword, logout } from '../../shared/api/http.js';
import { evaluatePassword } from '../../shared/lib/passwordStrength.js';
import { PasswordInput } from '../../shared/ui/PasswordInput.jsx';
import { PasswordStrengthMeter } from '../../shared/ui/PasswordStrengthMeter.jsx';

export function ChangeInitialPasswordPage({ user, onSuccess, onCancel }) {
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const strength = useMemo(() => evaluatePassword(password), [password]);
  const passwordsMatch = confirmation.length > 0 && password === confirmation;
  const canSubmit = strength.isAcceptable && passwordsMatch && !loading;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!strength.checks.length) {
      setError('Пароль должен быть не короче 8 символов.');
      return;
    }
    if (!strength.isAcceptable) {
      setError('Пароль слишком простой — добавьте заглавные, цифры или символы.');
      return;
    }
    if (password !== confirmation) {
      setError('Пароли не совпадают.');
      return;
    }

    setLoading(true);
    try {
      const data = await changeInitialPassword({
        password,
        password_confirmation: confirmation,
      });
      onSuccess?.(data?.user);
    } catch (err) {
      setError(
        err?.errors?.password?.[0] ?? err?.message ?? 'Не удалось сохранить пароль.',
      );
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = async () => {
    try {
      await logout();
    } catch {
      /* noop */
    }
    onCancel?.();
  };

  return (
    <div className="login-shell">
      <div className="login-card">
        <div className="login-brand">
          <p className="eyebrow">Первый вход — {user?.email}</p>
          <h1>Установите свой пароль</h1>
          <p className="login-sub">
            Вы вошли с временным паролем. Прежде чем продолжить, придумайте свой
            постоянный пароль — он понадобится для последующих входов.
          </p>
        </div>

        <form className="login-form" onSubmit={handleSubmit}>
          {error && <div className="login-error">{error}</div>}

          <label className="field">
            <span>Новый пароль</span>
            <PasswordInput
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoFocus
              minLength={8}
              autoComplete="new-password"
              disabled={loading}
            />
          </label>

          <PasswordStrengthMeter result={strength} show={password.length > 0} />

          <label className="field">
            <span>Повторите пароль</span>
            <PasswordInput
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
              disabled={loading}
            />
            {confirmation.length > 0 && !passwordsMatch && (
              <small className="field-hint field-hint--error">
                Пароли не совпадают
              </small>
            )}
          </label>

          <button
            className="primary-button"
            type="submit"
            disabled={!canSubmit}
          >
            {loading ? 'Сохранение…' : 'Сохранить и войти'}
          </button>

          <button
            className="link-button"
            type="button"
            onClick={handleCancel}
            disabled={loading}
          >
            Выйти
          </button>
        </form>
      </div>
    </div>
  );
}
