// src/features/sites/tabs/FilesTab.jsx
import { useEffect, useState } from 'react';
import { createAttachment, deleteAttachment, fetchAttachments } from '../../../shared/api/attachments.js';
import { canEditSiteContent } from '../../../shared/lib/permissions.js';

const KIND_OPTIONS = [
  { value: 'photo',    label: 'Фото' },
  { value: 'scheme',   label: 'Схема' },
  { value: 'document', label: 'Документ' },
  { value: 'other',    label: 'Прочее' },
];

const KIND_LABEL = Object.fromEntries(KIND_OPTIONS.map((k) => [k.value, k.label]));

const MODE = { FILE: 'file', URL: 'url' };

const MAX_FILE_BYTES = 20 * 1024 * 1024;

function emptyForm() {
  return { mode: MODE.FILE, kind: 'photo', file: null, url: '' };
}

function formatSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} Б`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
}

function attachmentLabel(att) {
  return att.original_name || att.path;
}

export function FilesTab({ site, user }) {
  const canEdit = canEditSiteContent(user);
  const [attachments, setAttachments] = useState([]);
  const [loading,     setLoading]     = useState(true);
  const [showForm,    setShowForm]    = useState(false);
  const [form,        setForm]        = useState(emptyForm);
  const [saving,      setSaving]      = useState(false);
  const [error,       setError]       = useState('');

  useEffect(() => {
    setLoading(true);
    fetchAttachments('site', site.id)
      .then(setAttachments)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [site.id]);

  const resetForm = () => {
    setForm(emptyForm());
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (form.mode === MODE.FILE) {
      if (!form.file) {
        setError('Выберите файл для загрузки.');
        return;
      }
      if (form.file.size > MAX_FILE_BYTES) {
        setError('Файл слишком большой (максимум 20 МБ).');
        return;
      }
    } else if (!form.url.trim()) {
      setError('Укажите URL.');
      return;
    }

    setSaving(true);
    try {
      const att = await createAttachment({
        entity_type: 'site',
        entity_id:   site.id,
        kind:        form.kind,
        file:        form.mode === MODE.FILE ? form.file : undefined,
        url:         form.mode === MODE.URL  ? form.url.trim() : undefined,
      });
      setAttachments((prev) => [att, ...prev]);
      resetForm();
      setShowForm(false);
    } catch (err) {
      const fieldErrors = err.errors ? Object.values(err.errors).flat().join(' ') : '';
      setError(fieldErrors || err.message || 'Ошибка при добавлении файла');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Удалить вложение?')) return;
    try {
      await deleteAttachment(id);
      setAttachments((prev) => prev.filter((a) => a.id !== id));
    } catch (err) {
      alert(err.message ?? 'Ошибка удаления');
    }
  };

  if (loading) {
    return (
      <div className="loading-state">
        <div className="spinner" />
        <p>Загрузка файлов…</p>
      </div>
    );
  }

  return (
    <div className="tab-content">
      <div className="tab-toolbar">
        <span>{attachments.length} файлов прикреплено</span>
        {canEdit && (
          <button
            className="primary-button"
            onClick={() => {
              if (showForm) resetForm();
              setShowForm((o) => !o);
            }}
          >
            {showForm ? 'Скрыть форму' : '+ Добавить файл'}
          </button>
        )}
      </div>

      {canEdit && showForm && (
        <form className="panel editor-form" onSubmit={handleSubmit}>
          <div className="panel-heading"><h3>Новый файл</h3></div>
          {error && <div className="form-error">{error}</div>}

          <div className="form-grid">
            <label className="field">
              <span>Источник</span>
              <select
                className="input"
                value={form.mode}
                onChange={(e) => setForm((d) => ({ ...d, mode: e.target.value, file: null, url: '' }))}
              >
                <option value={MODE.FILE}>Загрузить файл</option>
                <option value={MODE.URL}>Внешняя ссылка</option>
              </select>
            </label>

            <label className="field">
              <span>Тип</span>
              <select
                className="input"
                value={form.kind}
                onChange={(e) => setForm((d) => ({ ...d, kind: e.target.value }))}
              >
                {KIND_OPTIONS.map((k) => (
                  <option key={k.value} value={k.value}>{k.label}</option>
                ))}
              </select>
            </label>
          </div>

          {form.mode === MODE.FILE ? (
            <label className="field">
              <span>Файл (макс. 20 МБ) *</span>
              <input
                className="input"
                type="file"
                onChange={(e) => setForm((d) => ({ ...d, file: e.target.files?.[0] ?? null }))}
                required
              />
              {form.file && (
                <small className="field-hint">
                  {form.file.name} — {formatSize(form.file.size)}
                </small>
              )}
            </label>
          ) : (
            <label className="field">
              <span>URL *</span>
              <input
                className="input"
                type="url"
                value={form.url}
                onChange={(e) => setForm((d) => ({ ...d, url: e.target.value }))}
                required
                placeholder="https://…"
              />
            </label>
          )}

          <div className="form-actions">
            <button
              className="ghost-button"
              type="button"
              onClick={() => { resetForm(); setShowForm(false); }}
            >
              Отмена
            </button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? 'Сохранение…' : 'Прикрепить'}
            </button>
          </div>
        </form>
      )}

      {attachments.length === 0 ? (
        <p className="empty-state">Файлы не прикреплены</p>
      ) : (
        <div className="files-grid">
          {attachments.map((att) => (
            <div className="file-card" key={att.id}>
              <span className="file-kind">{KIND_LABEL[att.kind] ?? att.kind}</span>
              <a
                className="file-path"
                href={att.url ?? att.path}
                target="_blank"
                rel="noopener noreferrer"
              >
                {attachmentLabel(att)}
              </a>
              {att.size > 0 && (
                <small className="file-meta">{formatSize(att.size)}</small>
              )}
              {canEdit && (
                <button
                  className="ghost-button danger-button"
                  type="button"
                  onClick={() => handleDelete(att.id)}
                >
                  Удалить
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
