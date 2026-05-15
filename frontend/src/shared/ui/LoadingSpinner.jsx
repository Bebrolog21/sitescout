// src/shared/ui/LoadingSpinner.jsx
export function LoadingSpinner({ text = 'Загрузка…' }) {
  return (
    <div className="loading-state">
      <div className="spinner" />
      <p>{text}</p>
    </div>
  );
}
