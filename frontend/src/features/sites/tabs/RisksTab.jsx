// src/features/sites/tabs/RisksTab.jsx
import { useState } from 'react';
import { createRisk, deleteRisk } from '../../../shared/api/risks.js';
import { canEditSiteContent } from '../../../shared/lib/permissions.js';

const RISK_TYPES = [
  { value: 'legal',       label: 'Юридический' },
  { value: 'security',    label: 'Безопасность' },
  { value: 'neighbors',   label: 'Соседи' },
  { value: 'engineering', label: 'Инженерия' },
  { value: 'competition', label: 'Конкуренция' },
  { value: 'finance',     label: 'Финансовый' },
  { value: 'other',       label: 'Прочее' },
];

const SEVERITIES = [
  { value: 'low',      label: 'Низкий',      cls: 'risk-low' },
  { value: 'medium',   label: 'Средний',     cls: 'risk-medium' },
  { value: 'high',     label: 'Высокий',     cls: 'risk-high' },
  { value: 'critical', label: 'Критический', cls: 'risk-critical' },
];

const PROBABILITIES = [
  { value: 'low',    label: 'Низкая' },
  { value: 'medium', label: 'Средняя' },
  { value: 'high',   label: 'Высокая' },
];

const SEV_LABEL  = Object.fromEntries(SEVERITIES.map((s)    => [s.value, s.label]));
const SEV_CLS    = Object.fromEntries(SEVERITIES.map((s)    => [s.value, s.cls]));
const PROB_LABEL = Object.fromEntries(PROBABILITIES.map((p) => [p.value, p.label]));
const TYPE_LABEL = Object.fromEntries(RISK_TYPES.map((t)    => [t.value, t.label]));

function emptyRisk() {
  return {
    type:        'legal',
    severity:    'medium',
    probability: 'medium',
    description: '',
    mitigation:  '',
  };
}

export function RisksTab({ site, user, onRefresh }) {
  const canEdit = canEditSiteContent(user);
  const [risks,    setRisks]    = useState(site.risks ?? []);
  const [showForm, setShowForm] = useState(false);
  const [form,     setForm]     = useState(emptyRisk);
  const [saving,   setSaving]   = useState(false);
  const [error,    setError]    = useState('');

  const upd = (field, value) => setForm((d) => ({ ...d, [field]: value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const result = await createRisk(site.id, form);
      setRisks((prev) => [...prev, result.risk]);
      await onRefresh(site.id);
      setForm(emptyRisk());
      setShowForm(false);
    } catch (err) {
      setError(err.message ?? 'Ошибка при добавлении риска');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (riskId) => {
    if (!window.confirm('Удалить этот риск?')) return;
    try {
      await deleteRisk(riskId);
      setRisks((prev) => prev.filter((r) => r.id !== riskId));
      await onRefresh(site.id);
    } catch (err) {
      alert(err.message ?? 'Ошибка удаления');
    }
  };

  return (
    <div className="tab-content">

      {/* Тулбар */}
      <div className="tab-toolbar">
        <div className="score-summary">
          <span>Риск-скор:</span>
          <strong className="score-big risk-score-val">{site.risk_score ?? 0}%</strong>
        </div>
        {canEdit && (
          <button className="primary-button" onClick={() => setShowForm((o) => !o)}>
            {showForm ? 'Скрыть форму' : '+ Добавить риск'}
          </button>
        )}
      </div>

      {/* Форма добавления */}
      {canEdit && showForm && (
        <form className="panel editor-form" onSubmit={handleSubmit}>
          <div className="panel-heading"><h3>Новый риск</h3></div>
          {error && <div className="form-error">{error}</div>}
          <div className="form-grid">
            <label className="field">
              <span>Тип риска</span>
              <select className="input" value={form.type} onChange={(e) => upd('type', e.target.value)}>
                {RISK_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </label>
            <label className="field">
              <span>Серьёзность</span>
              <select className="input" value={form.severity} onChange={(e) => upd('severity', e.target.value)}>
                {SEVERITIES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </label>
            <label className="field">
              <span>Вероятность</span>
              <select className="input" value={form.probability} onChange={(e) => upd('probability', e.target.value)}>
                {PROBABILITIES.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
              </select>
            </label>
          </div>
          <label className="field">
            <span>Описание *</span>
            <textarea
              className="input textarea-input"
              value={form.description}
              onChange={(e) => upd('description', e.target.value)}
              required
              rows={3}
            />
          </label>
          <label className="field">
            <span>Меры снижения риска</span>
            <textarea
              className="input textarea-input"
              value={form.mitigation}
              onChange={(e) => upd('mitigation', e.target.value)}
              rows={2}
            />
          </label>
          <div className="form-actions">
            <button className="ghost-button" type="button" onClick={() => setShowForm(false)}>Отмена</button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? 'Сохранение…' : 'Добавить риск'}
            </button>
          </div>
        </form>
      )}

      {/* Список рисков */}
      {risks.length === 0 ? (
        <p className="empty-state">Риски не зарегистрированы</p>
      ) : (
        <div className="risk-list">
          {risks.map((risk) => (
            <article className={`risk-card ${SEV_CLS[risk.severity] ?? ''}`} key={risk.id}>
              <div className="risk-header">
                <span className="risk-type">{TYPE_LABEL[risk.type] ?? risk.type}</span>
                <div className="risk-badges">
                  <span className={`badge ${SEV_CLS[risk.severity] ?? ''}`}>
                    {SEV_LABEL[risk.severity] ?? risk.severity}
                  </span>
                  <span className="badge">{PROB_LABEL[risk.probability] ?? risk.probability}</span>
                </div>
              </div>
              <p className="risk-description">{risk.description}</p>
              {risk.mitigation && (
                <p className="risk-mitigation">✓ Меры: {risk.mitigation}</p>
              )}
              {canEdit && (
                <button
                  className="ghost-button danger-button"
                  type="button"
                  onClick={() => handleDelete(risk.id)}
                >
                  Удалить
                </button>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
