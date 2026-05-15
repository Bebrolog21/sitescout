export function calcPortfolioMetrics(allSites) {
  // Архивные площадки выпадают из всех агрегатов: воронки, среднего балла,
  // выручки, шортлиста и «Лучшей площадки».
  const sites = (allSites ?? []).filter((s) => s.status !== 'archived');

  const totalSites = sites.length;
  const averageScore =
    totalSites > 0 ? Math.round(sites.reduce((sum, site) => sum + site.score, 0) / totalSites) : 0;
  const approvedSites = sites.filter((site) => site.status === 'approved').length;
  const totalRevenue = sites.reduce(
    (sum, site) => sum + (site.finance?.results?.monthly_revenue ?? 0),
    0,
  );
  const byStatus = sites.reduce((acc, site) => {
    acc[site.status] = (acc[site.status] ?? 0) + 1;
    return acc;
  }, {});
  // «Лучшая площадка» — самая сильная из тех, что реально проходят правило
  // go/no-go (см. calcRecommendation). Отклонённые исключаем сразу.
  // Если ни одна не проходит — bestSite === undefined, в дашборде показывается
  // empty-state «Пока нет площадки, готовой к решению.»
  const bestSite = [...sites]
    .filter((site) => site.status !== 'rejected')
    .filter((site) => calcRecommendation(site).isRecommended)
    .sort((left, right) => {
      const byScore = (right.score ?? 0) - (left.score ?? 0);
      if (byScore !== 0) return byScore;
      const byRisk = (left.risk_score ?? 0) - (right.risk_score ?? 0);
      if (byRisk !== 0) return byRisk;
      // финальный tie-break — кратчайшая окупаемость
      const lp = left.finance?.results?.payback_months ?? Infinity;
      const rp = right.finance?.results?.payback_months ?? Infinity;
      return lp - rp;
    })
    .at(0);

  return {
    totalSites,
    averageScore,
    approvedSites,
    totalRevenue,
    byStatus,
    bestSite,
  };
}

export function calcRecommendation(site) {
  const payback = site.finance?.results?.payback_months ?? null;
  const isRecommended = site.score >= 70 && site.risk_score <= 50 && payback !== null && payback <= 18;

  return {
    isRecommended,
    label: isRecommended ? 'рекомендуется' : 'не рекомендуется',
  };
}
