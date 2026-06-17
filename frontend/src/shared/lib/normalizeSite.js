const CATEGORY_LABELS = {
  access: 'Доступ',
  security: 'Безопасность',
  visibility: 'Видимость',
  legal: 'Юр. вопросы',
  engineering: 'Инженерия',
  neighbors: 'Соседи',
  sales: 'Спрос',
};

function toNumber(value, fallback = 0) {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

function groupChecklistScores(checklistValues = []) {
  const buckets = new Map();

  checklistValues.forEach((item) => {
    const category = item?.checklist_item?.category ?? item?.checklistItem?.category ?? 'other';
    const weight = toNumber(item?.checklist_item?.weight ?? item?.checklistItem?.weight, 1);
    const value = toNumber(item?.value, 0);
    const current = buckets.get(category) ?? { earned: 0, max: 0 };

    current.earned += value * weight;
    current.max += 5 * weight;
    buckets.set(category, current);
  });

  return Array.from(buckets.entries()).map(([code, values]) => ({
    code,
    label: CATEGORY_LABELS[code] ?? code,
    score: values.max > 0 ? Math.round((values.earned / values.max) * 100) : 0,
  }));
}

function normalizeFinance(finance) {
  if (!finance) {
    return {
      assumptions: {},
      results: {
        monthly_revenue: 0,
        monthly_gross_profit: 0,
        payback_months: null,
        roi_12m: 0,
        breakeven_occupancy: 0,
      },
    };
  }

  return {
    assumptions: finance.assumptions_json ?? {},
    results: finance.results_json ?? {},
  };
}

export function normalizeSite(site) {
  const checklistValues = site.checklist_values ?? site.checklistValues ?? [];

  return {
    id: site.id,
    title: site.title,
    city: site.city,
    district: site.district,
    address: site.address,
    lat: site.lat,
    lng: site.lng,
    site_type: site.site_type,
    area_m2: toNumber(site.area_m2),
    status: site.status,
    owner_type: site.owner_type,
    contact_name: site.contact_name ?? '',
    contact_phone: site.contact_phone ?? '',
    score: toNumber(site.site_score),
    risk_score: toNumber(site.risk_score),
    finance: normalizeFinance(site.finance),
    risks: site.risks ?? [],
    visits: site.visits ?? [],
    checklist_values: checklistValues,
    category_scores: groupChecklistScores(checklistValues),
    workflow: site.workflow ?? null,
  };
}

export function normalizeSiteCollection(payload) {
  const items = Array.isArray(payload) ? payload : payload?.data ?? [];
  return items.map(normalizeSite);
}
