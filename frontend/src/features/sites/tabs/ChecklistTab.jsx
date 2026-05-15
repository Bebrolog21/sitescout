// src/features/sites/tabs/ChecklistTab.jsx
import { useEffect, useState } from 'react';
import { fetchChecklistItems, updateSiteChecklist } from '../../../shared/api/checklist.js';
import { canEditSiteContent } from '../../../shared/lib/permissions.js';

const CATEGORY_LABELS = {
  access:      'Доступ',
  security:    'Безопасность',
  visibility:  'Видимость',
  legal:       'Юридический',
  engineering: 'Инженерия',
  neighbors:   'Соседи',
  sales:       'Спрос / продажи',
};

export function ChecklistTab({ site, user, onRefresh }) {
  const canEdit = canEditSiteContent(user);
  const [items,   setItems]   = useState([]);
  const [values,  setValues]  = useState({});   // { [checklist_item_id]: 0..5 }
  const [comments, setComments] = useState({}); // { [checklist_item_id]: string }
  const [loading, setLoading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [saved,   setSaved]   = useState(false);
  const [error,   setError]   = useState('');

  // Загружаем справочник и инициализируем значения из текущего чеклиста площадки
  useEffect(() => {
    setLoading(true);
    setError('');
    fetchChecklistItems()
      .then((data) => {
        setItems(data);

        const initVals     = {};
        const initComments = {};
        (site.checklist_values ?? []).forEach((cv) => {
          const id = cv.checklist_item_id ?? cv.checklistItem?.id;
          if (id !== undefined) {
            initVals[id]     = cv.value;
            initComments[id] = cv.comment ?? '';
          }
        });
        setValues(initVals);
        setComments(initComments);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [site.id]);

  const handleSave = async () => {
    setSaving(true);
    setSaved(false);
    setError('');
    try {
      const payload = items.map((item) => ({
        checklist_item_id: item.id,
        value:             Number(values[item.id] ?? 0),
        comment:           comments[item.id] || null,
      }));
      await updateSiteChecklist(site.id, payload);
      await onRefresh(site.id);
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-state">
        <div className="spinner" />
        <p>Загрузка чеклиста…</p>
      </div>
    );
  }

  // Группируем пункты по категории
  const byCategory = items.reduce((acc, item) => {
    const cat = item.category ?? 'other';
    (acc[cat] ??= []).push(item);
    return acc;
  }, {});

  return (
    <div className="tab-content">

      {/* Тулбар */}
      <div className="tab-toolbar">
        <div className="score-summary">
          <span>Итоговый балл:</span>
          <strong className="score-big">{site.score ?? 0}%</strong>
        </div>
        {canEdit && (
          <button
            className={saved ? 'primary-button saved-btn' : 'primary-button'}
            onClick={handleSave}
            disabled={saving}
          >
            {saved ? '✓ Сохранено' : saving ? 'Сохранение…' : 'Сохранить и пересчитать'}
          </button>
        )}
      </div>

      {error && <div className="form-error">{error}</div>}
      {items.length === 0 && !error && (
        <p className="empty-state">Справочник критериев пуст. Добавьте пункты в разделе Администратора.</p>
      )}

      {/* Категории */}
      {Object.entries(byCategory).map(([cat, catItems]) => (
        <article className="panel" key={cat}>
          <div className="panel-heading">
            <h3>{CATEGORY_LABELS[cat] ?? cat}</h3>
            <span>{catItems.length} критериев</span>
          </div>
          <div className="checklist-list">
            {catItems.map((item) => (
              <div className="checklist-row" key={item.id}>
                <div className="checklist-info">
                  <p className="checklist-title">{item.title}</p>
                  <p className="checklist-meta">Вес: {item.weight}</p>
                </div>
                <div className="checklist-controls">
                  {/* Оценка 0–5 */}
                  <div className="value-picker">
                    {[0, 1, 2, 3, 4, 5].map((v) => (
                      <button
                        key={v}
                        type="button"
                        className={`value-btn ${Number(values[item.id] ?? 0) === v ? 'active' : ''}`}
                        onClick={() => canEdit && setValues((prev) => ({ ...prev, [item.id]: v }))}
                        disabled={!canEdit}
                      >
                        {v}
                      </button>
                    ))}
                  </div>
                  <input
                    className="input comment-input"
                    placeholder="Комментарий…"
                    value={comments[item.id] ?? ''}
                    onChange={(e) =>
                      setComments((prev) => ({ ...prev, [item.id]: e.target.value }))
                    }
                    readOnly={!canEdit}
                  />
                </div>
              </div>
            ))}
          </div>
        </article>
      ))}
    </div>
  );
}
