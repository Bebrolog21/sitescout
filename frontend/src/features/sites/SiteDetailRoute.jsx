// src/features/sites/SiteDetailRoute.jsx
// Адаптер между URL (/sites/:siteId/:tab?) и SiteDetailPage.
// - Достаёт площадку из общего списка либо догружает по id (deep-link сценарий).
// - Превращает выбор вкладки в навигацию по URL.

import { useEffect, useState } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import { fetchSite } from '../../shared/api/sites.js';
import { ErrorMessage } from '../../shared/ui/ErrorMessage.jsx';
import { SiteDetailPage } from './SiteDetailPage.jsx';

const VALID_TABS = ['overview', 'checklist', 'risks', 'finance', 'files', 'visits'];

export function SiteDetailRoute({
  sites,
  sitesLoading,
  user,
  onUpdateStatus,
  onRefreshSite,
  onSitesUpsert,
  onLastSiteChange,
}) {
  const { siteId, tab } = useParams();
  const navigate = useNavigate();

  const numericId = Number(siteId);
  const siteFromList = sites.find((s) => s.id === numericId) ?? null;

  const [fetching, setFetching] = useState(false);
  const [fetchError, setFetchError] = useState(null);

  // Догружаем полный объект (список грузит «тонкие» данные без checklist_values/risks/visits).
  // Результат складываем сразу в общий sites через onSitesUpsert — это единственный
  // источник правды; локальный кэш отдельно не держим, чтобы после save/refresh не
  // оставалось устаревшего состояния, перебивающего свежий список.
  useEffect(() => {
    if (!siteId || Number.isNaN(numericId)) return;
    if (sitesLoading) return;
    const showSpinner = !siteFromList;
    if (showSpinner) setFetching(true);
    setFetchError(null);
    fetchSite(numericId)
      .then((fresh) => onSitesUpsert?.(fresh))
      .catch((err) => setFetchError(err?.message ?? 'Не удалось загрузить площадку.'))
      .finally(() => {
        if (showSpinner) setFetching(false);
      });
    // siteFromList намеренно не в зависимостях — иначе upsert провоцирует повторный fetch.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [siteId, numericId, sitesLoading, onSitesUpsert]);

  const site = siteFromList;

  // Запоминаем последнюю открытую — для пункта «Паспорт» в сайдбаре.
  useEffect(() => {
    if (site?.id) onLastSiteChange?.(site.id);
  }, [site?.id, onLastSiteChange]);

  if (Number.isNaN(numericId)) {
    return <Navigate to="/sites" replace />;
  }

  if (!tab) {
    return <Navigate to={`/sites/${siteId}/overview`} replace />;
  }

  if (!VALID_TABS.includes(tab)) {
    return <Navigate to={`/sites/${siteId}/overview`} replace />;
  }

  if (sitesLoading || fetching) {
    return (
      <div className="loading-state">
        <div className="spinner" />
        <p>Загрузка площадки…</p>
      </div>
    );
  }

  if (fetchError) {
    return <ErrorMessage message={fetchError} onRetry={() => navigate(0)} />;
  }

  if (!site) {
    return (
      <ErrorMessage
        message="Площадка не найдена."
        onRetry={() => navigate('/sites', { replace: true })}
      />
    );
  }

  return (
    <SiteDetailPage
      site={site}
      user={user}
      activeTab={tab}
      onTabChange={(nextTab) => navigate(`/sites/${siteId}/${nextTab}`)}
      onBack={() => navigate('/sites')}
      onUpdateStatus={onUpdateStatus}
      onRefreshSite={onRefreshSite}
    />
  );
}
