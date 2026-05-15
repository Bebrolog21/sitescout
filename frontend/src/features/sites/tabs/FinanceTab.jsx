// src/features/sites/tabs/FinanceTab.jsx
import { useEffect, useState } from 'react';
import { fetchSiteFinance, updateSiteFinance } from '../../../shared/api/finance.js';
import { formatCurrency } from '../../../shared/lib/formatters.js';
import { canEditSiteContent } from '../../../shared/lib/permissions.js';

function defaultAssumptions() {
  return {
    containers_count:    4,
    units_per_container: 8,
    occupancy_percent:   65,
    capex_total:         2_000_000,
    opex_total:          100_000,
    price_per_unit:      { '2m2': 4500, '3m2': 6500, '5m2': 9000 },
  };
}

export function FinanceTab({ site, user, onRefresh }) {
  const canEdit = canEditSiteContent(user);
  const [finance,     setFinance]     = useState(null);
  const [assumptions, setAssumptions] = useState(defaultAssumptions);
  const [loading,     setLoading]     = useState(true);
  const [saving,      setSaving]      = useState(false);
  const [error,       setError]       = useState('');

  // Загружаем существующую финмодель
  useEffect(() => {
    setLoading(true);
    setError('');
    fetchSiteFinance(site.id)
      .then((data) => {
        if (data) {
          setFinance(data);
          if (data.assumptions_json) {
            setAssumptions(data.assumptions_json);
          }
        }
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [site.id]);

  const upd = (field, value) =>
    setAssumptions((d) => ({ ...d, [field]: value }));

  const updPrice = (key, value) =>
    setAssumptions((d) => ({
      ...d,
      price_per_unit: { ...d.price_per_unit, [key]: value },
    }));

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...assumptions,
        containers_count:    Number(assumptions.containers_count),
        units_per_container: Number(assumptions.units_per_container),
        occupancy_percent:   Number(assumptions.occupancy_percent),
        capex_total:         Number(assumptions.capex_total),
        opex_total:          Number(assumptions.opex_total),
        price_per_unit: Object.fromEntries(
          Object.entries(assumptions.price_per_unit ?? {}).map(([k, v]) => [k, Number(v)])
        ),
      };
      const updated = await updateSiteFinance(site.id, payload);
      setFinance(updated);
      await onRefresh(site.id);
    } catch (err) {
      setError(err.message ?? 'Ошибка при сохранении финмодели');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-state">
        <div className="spinner" />
        <p>Загрузка финансовой модели…</p>
      </div>
    );
  }

  const results    = finance?.results_json ?? {};
  const hasResults = results.monthly_revenue > 0;
  const isProfit   = (results.monthly_gross_profit ?? 0) > 0;

  return (
    <div className="tab-content">

      {/* KPI-итоги (если рассчитано) */}
      {hasResults && (
        <div className="finance-kpi">
          <div className="kpi-card">
            <span>Выручка / мес</span>
            <strong>{formatCurrency(results.monthly_revenue)}</strong>
          </div>
          <div className="kpi-card">
            <span>Прибыль / мес</span>
            <strong className={isProfit ? 'positive' : 'negative'}>
              {formatCurrency(results.monthly_gross_profit)}
            </strong>
          </div>
          <div className="kpi-card">
            <span>Окупаемость</span>
            <strong>
              {results.payback_months != null
                ? `${results.payback_months} мес`
                : <span className="negative">не окупается</span>}
            </strong>
          </div>
          <div className="kpi-card">
            <span>ROI за 12 мес</span>
            <strong>{results.roi_12m ?? 0}%</strong>
          </div>
          <div className="kpi-card">
            <span>Точка безубыт.</span>
            <strong>{results.breakeven_occupancy ?? 0}% загрузки</strong>
          </div>
        </div>
      )}

      {/* Форма параметров */}
      {canEdit && (
      <form className="panel editor-form" onSubmit={handleSave}>
        <div className="panel-heading">
          <h3>Параметры расчёта</h3>
          <span>Изменения → нажмите «Рассчитать»</span>
        </div>
        {error && <div className="form-error">{error}</div>}

        <div className="form-grid">
          <label className="field">
            <span>Контейнеров</span>
            <input
              className="input" type="number" min="1"
              value={assumptions.containers_count}
              onChange={(e) => upd('containers_count', e.target.value)}
            />
          </label>
          <label className="field">
            <span>Кладовок в контейнере</span>
            <input
              className="input" type="number" min="1"
              value={assumptions.units_per_container}
              onChange={(e) => upd('units_per_container', e.target.value)}
            />
          </label>
          <label className="field">
            <span>Загрузка, %</span>
            <input
              className="input" type="number" min="0" max="100"
              value={assumptions.occupancy_percent}
              onChange={(e) => upd('occupancy_percent', e.target.value)}
            />
          </label>
          <label className="field">
            <span>CAPEX общий, ₽</span>
            <input
              className="input" type="number" min="0"
              value={assumptions.capex_total}
              onChange={(e) => upd('capex_total', e.target.value)}
            />
          </label>
          <label className="field">
            <span>OPEX в месяц, ₽</span>
            <input
              className="input" type="number" min="0"
              value={assumptions.opex_total}
              onChange={(e) => upd('opex_total', e.target.value)}
            />
          </label>
        </div>

        {/* Цены по типам кладовок */}
        <div className="editor-section">
          <h4>Цена кладовки по размеру (₽ / мес)</h4>
          <div className="form-grid">
            {Object.entries(assumptions.price_per_unit ?? {}).map(([size, price]) => (
              <label key={size} className="field">
                <span>{size}</span>
                <input
                  className="input" type="number" min="0"
                  value={price}
                  onChange={(e) => updPrice(size, e.target.value)}
                />
              </label>
            ))}
          </div>
        </div>

        <div className="form-actions">
          <button className="primary-button" type="submit" disabled={saving}>
            {saving ? 'Расчёт…' : 'Рассчитать'}
          </button>
        </div>
      </form>
      )}
    </div>
  );
}
