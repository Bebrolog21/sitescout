// src/shared/ui/StatusBadge.jsx
const STATUS_LABELS = {
  new: "Новая",
  screening: "Скрининг",
  inspection: "Осмотр",
  scoring: "Скоринг",
  negotiation: "Согласование",
  approved: "Согласована",
  rejected: "Отклонена",
  launched: "Запущена",
  archived: "В архиве",
};

export function StatusBadge({ status }) {
  return (
    <span className={`status-badge status-${status ?? "new"}`}>
      {STATUS_LABELS[status] ?? status}
    </span>
  );
}
