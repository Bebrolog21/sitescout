// src/features/auth/LoginPage.jsx
import { useState } from 'react';
import { login } from '../../shared/api/http.js';
import { PasswordInput } from '../../shared/ui/PasswordInput.jsx';

export function LoginPage({ onSuccess, onForgotPassword }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const data = await login(email, password);
      if (data?.token) onSuccess(data.user);
      else setError('Неверный email или пароль');
    } catch {
      setError('Неверный email или пароль');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-shell">
      <div className="login-card">
        <div className="login-brand">
          <p className="eyebrow">Добро пожаловать</p>
          <h1>SiteScout</h1>
          <p className="login-sub">Платформа оценки площадок для self-storage</p>
        </div>

        <form className="login-form" onSubmit={handleSubmit}>
          {error && <div className="login-error">{error}</div>}

          <label className="field">
            <span>Email</span>
            <input
              className="input"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoFocus
            />
          </label>

          <label className="field">
            <span>Пароль</span>
            <PasswordInput
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoComplete="current-password"
              disabled={loading}
            />
          </label>

          <button className="primary-button" type="submit" disabled={loading}>
            {loading ? 'Вход…' : 'Войти'}
          </button>

          {onForgotPassword && (
            <button
              className="link-button"
              type="button"
              onClick={onForgotPassword}
              disabled={loading}
            >
              Забыли пароль?
            </button>
          )}
        </form>
      </div>
    </div>
  );
}
