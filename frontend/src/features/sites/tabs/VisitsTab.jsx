// src/features/sites/tabs/VisitsTab.jsx
import { useEffect, useState } from 'react';
import { createVisit, deleteVisit, fetchSiteVisits } from '../../../shared/api/visits.js';
import { canEditSiteContent } from '../../../shared/lib/permissions.js';

function emptyVisit() {
  return {
    visit_date: new Date().toISOString().slice(0, 10),
    summary:    '',
  };
}

export function VisitsTab({ site, user }) {
  const canEdit = canEditSiteContent(user);
  const [visits,   setVisits]   = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form,     setForm]     = useState(emptyVisit);
  const [saving,   setSaving]   = useState(false);
  const [error,    setError]    = useState('');

  useEffect(() => {
    setLoading(true);
    fetchSiteVisits(site.id)
      .then(setVisits)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [site.id]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const visit = await createVisit(site.id, {
        ...form,
        visited_by_user_id: user?.id ?? 1,
      });
      setVisits((prev) => [visit, ...prev]);
      setForm(emptyVisit());
      setShowForm(false);
    } catch (err) {
      setError(err.message ?? 'Ошибка при добавлении визита');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (visitId) => {
    if (!window.confirm('Удалить визит?')) return;
    try {
      await deleteVisit(visitId);
      setVisits((prev) => prev.filter((v) => v.id !== visitId));
    } catch (err) {
      alert(err.message ?? 'Ошибка удаления');
    }
  };

  if (loading) {
    return (
      <div className="loading-state">
        <div className="spinner" />
        <p>Загрузка визитов…</p>
      </div>
    );
  }

  return (
    <div className="tab-content">
      <div className="tab-toolbar">
        <span className="visits-count">{visits.length} визитов зафиксировано</span>
        {canEdit && (
          <button className="primary-button" onClick={() => setShowForm((o) => !o)}>
            {showForm ? 'Скрыть' : '+ Добавить визит'}
          </button>
        )}
      </div>

      {canEdit && showForm && (
        <form className="panel editor-form" onSubmit={handleSubmit}>
          <div className="panel-heading"><h3>Новый визит</h3></div>
          {error && <div className="form-error">{error}</div>}
          <div className="form-grid">
            <label className="field">
              <span>Дата визита</span>
              <input
                className="input" type="date"
                value={form.visit_date}
                onChange={(e) => setForm((d) => ({ ...d, visit_date: e.target.value }))}
              />
            </label>
          </div>
          <label className="field">
            <span>Описание / результаты *</span>
            <textarea
              className="input textarea-input"
              value={form.summary}
              onChange={(e) => setForm((d) => ({ ...d, summary: e.target.value }))}
              required
              rows={4}
              placeholder="Что наблюдали, с кем встретились, какие выводы…"
            />
          </label>
          <div className="form-actions">
            <button className="ghost-button" type="button" onClick={() => setShowForm(false)}>Отмена</button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? 'Сохранение…' : 'Сохранить визит'}
            </button>
          </div>
        </form>
      )}

      {visits.length === 0 ? (
        <p className="empty-state">Визиты пока не зафиксированы</p>
      ) : (
        <div className="visit-list">
          {visits.map((v) => (
            <article className="panel visit-card" key={v.id}>
              <div className="visit-header">
                <strong>{v.visit_date}</strong>
                {canEdit && (
                  <button
                    className="ghost-button danger-button"
                    type="button"
                    onClick={() => handleDelete(v.id)}
                  >
                    Удалить
                  </button>
                )}
              </div>
              <p>{v.summary}</p>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
