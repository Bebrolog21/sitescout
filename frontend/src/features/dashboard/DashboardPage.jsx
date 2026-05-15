import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { StatCard } from "../../shared/ui/StatCard.jsx";
import { StatusBadge } from "../../shared/ui/StatusBadge.jsx";
import { formatCurrency } from "../../shared/lib/formatters.js";

export function DashboardPage({ metrics, sites }) {
  const navigate = useNavigate();
  const openSite = (id) => navigate(`/sites/${id}/overview`);

  // Топ-4 для «короткого списка»: сначала по убыванию балла, при равенстве —
  // меньший риск-скор побеждает. Отклонённые исключаем (им в шортлисте не место).
  const featuredSites = useMemo(() => {
    return [...sites]
      .filter((s) => s.status !== "rejected" && s.status !== "archived")
      .sort((a, b) => {
        if ((b.score ?? 0) !== (a.score ?? 0)) {
          return (b.score ?? 0) - (a.score ?? 0);
        }
        return (a.risk_score ?? 0) - (b.risk_score ?? 0);
      })
      .slice(0, 4);
  }, [sites]);

  return (
    <section className="page-stack">
      <header className="page-header">
        <div>
          <p className="eyebrow">Дашборд</p>
          <h2>Сводка по проекту</h2>
        </div>
        <p className="page-copy">
          Главный экран показывает состояние воронки, средний балл, уровень
          рисков и окупаемость, чтобы быстро понять, какие площадки стоит
          двигать дальше.
        </p>
      </header>

      <div className="stats-grid">
        <StatCard
          label="Площадок в воронке"
          value={metrics.totalSites}
          tone="amber"
        />
        <StatCard
          label="Средний балл"
          value={`${metrics.averageScore}%`}
          tone="blue"
        />
        <StatCard
          label="Согласованные площадки"
          value={metrics.approvedSites}
          tone="green"
        />
        <StatCard
          label="Потенциал выручки в месяц"
          value={formatCurrency(metrics.totalRevenue)}
          tone="rose"
        />
      </div>

      <div className="panel-grid">
        <article className="panel">
          <div className="panel-heading">
            <h3>Воронка площадок</h3>
            <span>
              {Object.keys(metrics.byStatus).length} статусов отслеживается
            </span>
          </div>
          <div className="pipeline-list">
            {Object.entries(metrics.byStatus).map(([status, count]) => (
              <div className="pipeline-card" key={status}>
                <StatusBadge status={status} />
                <strong>{count}</strong>
              </div>
            ))}
          </div>
        </article>

        <article className="panel">
          <div className="panel-heading">
            <h3>Лучшая площадка</h3>
            <span>Простое правило go / no-go</span>
          </div>
          {metrics.bestSite ? (
            <div className="hero-site">
              <div>
                <p className="hero-title">{metrics.bestSite.title}</p>
                <p className="hero-copy">{metrics.bestSite.address}</p>
              </div>
              <div className="hero-tiles">
                <div className="hero-tile hero-tile--score">
                  <span className="hero-tile-label">Балл</span>
                  <strong className="hero-tile-value">
                    {metrics.bestSite.score}
                    <small>%</small>
                  </strong>
                  <div className="metric-bar">
                    <span
                      style={{
                        width: `${Math.min(100, Math.max(0, metrics.bestSite.score))}%`,
                      }}
                    />
                  </div>
                </div>
                <div className="hero-tile hero-tile--risk">
                  <span className="hero-tile-label">Риск</span>
                  <strong className="hero-tile-value">
                    {metrics.bestSite.risk_score}
                    <small>%</small>
                  </strong>
                  <div className="metric-bar">
                    <span
                      style={{
                        width: `${Math.min(100, Math.max(0, metrics.bestSite.risk_score))}%`,
                      }}
                    />
                  </div>
                </div>
                <div className="hero-tile">
                  <span className="hero-tile-label">Окупаемость</span>
                  <strong className="hero-tile-value">
                    {metrics.bestSite.finance?.results?.payback_months != null ? (
                      <>
                        {metrics.bestSite.finance.results.payback_months}
                        <small>мес</small>
                      </>
                    ) : (
                      "н/д"
                    )}
                  </strong>
                </div>
              </div>
            </div>
          ) : (
            <p className="empty-state">Пока нет площадки, готовой к решению.</p>
          )}
        </article>
      </div>

      <article className="panel">
        <div className="panel-heading">
          <h3>Короткий список</h3>
          <span>Приоритетные площадки с лучшими оценками</span>
        </div>
        <div className="site-list">
          {featuredSites.length > 0 && (
            <div className="site-row site-row--head" aria-hidden="true">
              <span>Площадка</span>
              <span className="site-cell-center">Статус</span>
              <span className="site-cell-center">Балл</span>
            </div>
          )}
          {featuredSites.map((site) => (
            <button
              className="site-row"
              key={site.id}
              onClick={() => openSite(site.id)}
            >
              <div className="site-row-info">
                <p className="site-title">{site.title}</p>
                <p className="site-copy">
                  {site.city}, {site.district}
                </p>
              </div>
              <span className="site-cell-center">
                <StatusBadge status={site.status} />
              </span>
              <span className="site-cell-center">
                <strong>{site.score}%</strong>
              </span>
            </button>
          ))}
        </div>
      </article>
    </section>
  );
}
