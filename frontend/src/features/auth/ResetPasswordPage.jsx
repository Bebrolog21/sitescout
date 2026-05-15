// src/features/auth/ResetPasswordPage.jsx
import { useMemo, useState } from 'react';
import { resetPassword } from '../../shared/api/http.js';
import { evaluatePassword } from '../../shared/lib/passwordStrength.js';
import { PasswordInput } from '../../shared/ui/PasswordInput.jsx';
import { PasswordStrengthMeter } from '../../shared/ui/PasswordStrengthMeter.jsx';

export function ResetPasswordPage({ token, email: initialEmail, onSuccess, onBack }) {
  const [email, setEmail] = useState(initialEmail ?? '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const strength = useMemo(() => evaluatePassword(password), [password]);
  const passwordsMatch = passwordConfirmation.length > 0 && password === passwordConfirmation;
  const canSubmit = strength.isAcceptable && passwordsMatch && !loading;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');

    if (!strength.checks.length) {
      setError('Пароль должен быть не короче 8 символов.');
      return;
    }
    if (!strength.isAcceptable) {
      setError('Пароль слишком простой — добавьте заглавные, цифры или символы.');
      return;
    }
    if (password !== passwordConfirmation) {
      setError('Пароли не совпадают.');
      return;
    }

    setLoading(true);
    try {
      const data = await resetPassword({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage(data?.message ?? 'Пароль успешно изменён.');
      setTimeout(() => onSuccess?.(), 1500);
    } catch (err) {
      const fieldError =
        err?.errors?.email?.[0] ??
        err?.errors?.password?.[0] ??
        err?.errors?.token?.[0];
      setError(fieldError ?? err?.message ?? 'Не удалось сбросить пароль.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-shell">
      <div className="login-card">
        <div className="login-brand">
          <p className="eyebrow">Сброс пароля</p>
          <h1>Новый пароль</h1>
          <p className="login-sub">Придумайте новый пароль для вашей учётной записи.</p>
        </div>

        <form className="login-form" onSubmit={handleSubmit}>
          {error && <div className="login-error">{error}</div>}
          {message && <div className="login-success">{message}</div>}

          <label className="field">
            <span>Email</span>
            <input
              className="input"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              disabled={loading || Boolean(initialEmail)}
            />
          </label>

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
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
              disabled={loading}
            />
            {passwordConfirmation.length > 0 && !passwordsMatch && (
              <small className="field-hint field-hint--error">
                Пароли не совпадают
              </small>
            )}
          </label>

          <button className="primary-button" type="submit" disabled={!canSubmit}>
            {loading ? 'Сохранение…' : 'Сохранить пароль'}
          </button>

          <button
            className="link-button"
            type="button"
            onClick={onBack}
            disabled={loading}
          >
            ← Вернуться ко входу
          </button>
        </form>
      </div>
    </div>
  );
}
