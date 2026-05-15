// src/features/auth/ForgotPasswordPage.jsx
import { useState } from 'react';
import { forgotPassword } from '../../shared/api/http.js';

export function ForgotPasswordPage({ onBack }) {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    setLoading(true);
    try {
      const data = await forgotPassword(email);
      setMessage(data?.message ?? 'Если такой email зарегистрирован, мы отправили на него ссылку для сброса пароля.');
    } catch (err) {
      if (err?.status === 429) {
        setError(err.message ?? 'Слишком много запросов. Попробуйте позже.');
      } else {
        setError(err?.errors?.email?.[0] ?? err?.message ?? 'Не удалось отправить письмо.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-shell">
      <div className="login-card">
        <div className="login-brand">
          <p className="eyebrow">Восстановление доступа</p>
          <h1>Забыли пароль?</h1>
          <p className="login-sub">
            Введите email, привязанный к учётной записи. Мы отправим ссылку для сброса пароля.
          </p>
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
              autoFocus
              disabled={loading}
            />
          </label>

          <button className="primary-button" type="submit" disabled={loading}>
            {loading ? 'Отправка…' : 'Отправить ссылку'}
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
