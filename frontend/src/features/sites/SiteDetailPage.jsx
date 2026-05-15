// src/features/sites/SiteDetailPage.jsx
import { calcRecommendation } from "../../shared/lib/calculations.js";
import { formatCurrency } from "../../shared/lib/formatters.js";
import { StatusBadge } from "../../shared/ui/StatusBadge.jsx";
import { ChecklistTab } from "./tabs/ChecklistTab.jsx";
import { FilesTab } from "./tabs/FilesTab.jsx";
import { FinanceTab } from "./tabs/FinanceTab.jsx";
import { OverviewTab } from "./tabs/OverviewTab.jsx";
import { RisksTab } from "./tabs/RisksTab.jsx";
import { VisitsTab } from "./tabs/VisitsTab.jsx";

const TABS = [
  { id: "overview", label: "Обзор" },
  { id: "checklist", label: "Чеклист" },
  { id: "risks", label: "Риски" },
  { id: "finance", label: "Финансы" },
  { id: "files", label: "Файлы" },
  { id: "visits", label: "Визиты" },
];

export function SiteDetailPage({
  site,
  user,
  activeTab = "overview",
  onTabChange,
  onBack,
  onUpdateStatus,
  onRefreshSite,
}) {
  const recommendation = calcRecommendation(site);
  const payback = site.finance?.results?.payback_months ?? null;

  return (
    <section className="page-stack">
      {/* ── Верхняя навигация ── */}
      <div className="detail-header">
        <button className="ghost-button" onClick={onBack}>
          ← Назад к списку
        </button>
        <div className="detail-actions">
          <StatusBadge status={site.status} />
          <span
            className={`recommendation-badge ${recommendation.isRecommended ? "rec-yes" : "rec-no"}`}
          >
            {recommendation.isRecommended
              ? "✓ Рекомендуется"
              : "✗ Не рекомендуется"}
          </span>
        </div>
      </div>

      {/* ── Заголовок площадки ── */}
      <header className="page-header">
        <div>
          <p className="eyebrow">Площадка</p>
          <h2>{site.title}</h2>
          <p className="page-copy">
            {site.city}, {site.district} — {site.address}
          </p>
        </div>

        {/* Быстрые KPI */}
        <div className="site-kpi">
          <div className="kpi-pill">
            <span>Балл</span>
            <strong>{site.score}%</strong>
          </div>
          <div className="kpi-pill">
            <span>Риск</span>
            <strong>{site.risk_score}%</strong>
          </div>
          <div className="kpi-pill">
            <span>Окупаемость</span>
            <strong>{payback != null ? `${payback} мес` : "н/д"}</strong>
          </div>
          {site.finance?.results?.monthly_revenue > 0 && (
            <div className="kpi-pill">
              <span>Выручка/мес</span>
              <strong>
                {formatCurrency(site.finance.results.monthly_revenue)}
              </strong>
            </div>
          )}
        </div>
      </header>

      {/* ── Вкладки ── */}
      <nav className="tab-bar">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            className={`tab-btn ${activeTab === tab.id ? "active" : ""}`}
            onClick={() => onTabChange?.(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </nav>

      {/* ── Содержимое активной вкладки ── */}
      {activeTab === "overview" && (
        <OverviewTab
          site={site}
          user={user}
          onRefresh={onRefreshSite}
          onUpdateStatus={onUpdateStatus}
        />
      )}
      {activeTab === "checklist" && (
        <ChecklistTab site={site} user={user} onRefresh={onRefreshSite} />
      )}
      {activeTab === "risks" && (
        <RisksTab site={site} user={user} onRefresh={onRefreshSite} />
      )}
      {activeTab === "finance" && (
        <FinanceTab site={site} user={user} onRefresh={onRefreshSite} />
      )}
      {activeTab === "files" && <FilesTab site={site} user={user} />}
      {activeTab === "visits" && <VisitsTab site={site} user={user} />}
    </section>
  );
}
