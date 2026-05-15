// src/shared/ui/StatCard.jsx
export function StatCard({ label, value, tone = "blue" }) {
  return (
    <div className={`stat-card tone-${tone}`}>
      <span className="stat-label">{label}</span>
      <strong className="stat-value">{value}</strong>
    </div>
  );
}
