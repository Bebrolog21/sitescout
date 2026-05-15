// src/shared/ui/ErrorMessage.jsx
export function ErrorMessage({ message, onRetry }) {
  return (
    <div className="error-state">
      <p>⚠ {message}</p>
      {onRetry && (
        <button className="ghost-button" onClick={onRetry}>
          Повторить
        </button>
      )}
    </div>
  );
}
