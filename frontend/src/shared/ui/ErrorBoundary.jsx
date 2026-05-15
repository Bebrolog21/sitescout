// src/shared/ui/ErrorBoundary.jsx
// Глобальный предохранитель: ловит ошибки рендера/жизненного цикла в детях,
// чтобы один сломанный компонент не уронил всё приложение.

import { Component } from 'react';

export class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.error('[ErrorBoundary] caught:', error, info?.componentStack);
    }
  }

  handleReset = () => {
    this.setState({ error: null });
  };

  handleReload = () => {
    if (typeof window !== 'undefined') window.location.reload();
  };

  render() {
    const { error } = this.state;
    if (!error) return this.props.children;

    if (this.props.fallback) {
      return this.props.fallback({ error, reset: this.handleReset, reload: this.handleReload });
    }

    return (
      <div className="error-state full-screen" role="alert">
        <h2>Что-то пошло не так</h2>
        <p>{error?.message ?? 'Неизвестная ошибка интерфейса.'}</p>
        <div className="form-actions">
          <button className="ghost-button" type="button" onClick={this.handleReset}>
            Попробовать снова
          </button>
          <button className="primary-button" type="button" onClick={this.handleReload}>
            Перезагрузить страницу
          </button>
        </div>
        {import.meta.env.DEV && error?.stack && (
          <pre style={{ marginTop: 16, fontSize: 12, opacity: 0.7, whiteSpace: 'pre-wrap' }}>
            {error.stack}
          </pre>
        )}
      </div>
    );
  }
}
