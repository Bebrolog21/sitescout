// src/features/sites/tabs/OverviewTab.jsx
import { useState } from "react";
import { updateSite } from "../../../shared/api/sites.js";
import { joinAddress, splitAddress } from "../../../shared/lib/addressFormat.js";
import {
  canChangeSiteStatus,
  canEditSiteContent,
} from "../../../shared/lib/permissions.js";
import { AddressAutocomplete } from "../../../shared/ui/AddressAutocomplete.jsx";
import { PhoneInput } from "../../../shared/ui/PhoneInput.jsx";
import { StatusBadge } from "../../../shared/ui/StatusBadge.jsx";

const STATUS_OPTIONS = [
  { value: "new", label: "Новая" },
  { value: "screening", label: "Скрининг" },
  { value: "inspection", label: "Осмотр" },
  { value: "scoring", label: "Скоринг" },
  { value: "negotiation", label: "Согласование" },
  { value: "approved", label: "Согласована" },
  { value: "rejected", label: "Отклонена" },
  { value: "launched", label: "Запущена" },
  { value: "archived", label: "В архиве" },
];

const SITE_TYPES = [
  { value: "parking", label: "Парковка" },
  { value: "yard", label: "Двор" },
  { value: "tech_zone", label: "Техзона" },
  { value: "other", label: "Другое" },
];

const OWNER_TYPES = [
  { value: "management_company", label: "Управляющая компания" },
  { value: "developer", label: "Застройщик" },
  { value: "private", label: "Частный владелец" },
  { value: "municipality", label: "Муниципалитет" },
];

const SITE_TYPE_LABEL = Object.fromEntries(
  SITE_TYPES.map((t) => [t.value, t.label]),
);
const OWNER_TYPE_LABEL = Object.fromEntries(
  OWNER_TYPES.map((t) => [t.value, t.label]),
);

function formatCoord(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return value ?? "";
  return n.toFixed(4);
}

// Срезаем избыточные административные приставки/суффиксы, которые приходят из
// Nominatim ("городской округ Омск", "Кировский административный округ" и т.п.) —
// в карточке их видеть незачем.
function tidyAdmin(value) {
  if (!value) return "";
  return String(value)
    .replace(/^городской округ\s+/i, "")
    .replace(/^городское поселение\s+/i, "")
    .replace(/^муниципальный округ\s+/i, "")
    .replace(/^муниципальный район\s+/i, "")
    .replace(/^город\s+(?=\S)/i, "")
    .replace(/\s+административный округ$/i, "")
    .replace(/\s+муниципальный район$/i, "")
    .trim();
}

function formatCityDistrict(site) {
  const city = tidyAdmin(site.city);
  const district = tidyAdmin(site.district);
  return [city, district].filter(Boolean).join(", ");
}

function buildDraft(site) {
  return {
    title: site.title,
    city: site.city,
    district: site.district,
    address: site.address,
    site_type: site.site_type,
    owner_type: site.owner_type,
    area_m2: site.area_m2,
    lat: site.lat ?? "",
    lng: site.lng ?? "",
    contact_name: site.contact_name ?? "",
    contact_phone: site.contact_phone ?? "",
  };
}

export function OverviewTab({ site, user, onRefresh, onUpdateStatus }) {
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState(() => buildDraft(site));
  const [addressText, setAddressText] = useState(() => joinAddress(site));
  // Снимок строки после автокомплита: если на момент сохранения совпадает —
  // данные у нас уже разложены по полям, фолбэк-сплит не нужен.
  const [addressSnapshot, setAddressSnapshot] = useState(() => joinAddress(site));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [statusErr, setStatusErr] = useState("");
  const [statusBusy, setStatusBusy] = useState(false);

  const canEdit = canEditSiteContent(user);
  const canChangeStatus = canChangeSiteStatus(user);

  const upd = (field, value) => setDraft((d) => ({ ...d, [field]: value }));

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      // Если строка не менялась после пика DaData — берём draft как есть.
      // Иначе пытаемся аккуратно разделить пользовательский ввод по запятым.
      const usePick = addressText === addressSnapshot;
      const split = usePick ? null : splitAddress(addressText);
      const payload = {
        ...draft,
        ...(usePick
          ? {}
          : {
              city: split.city || draft.city,
              district: split.district || draft.district,
              address: split.address || draft.address,
            }),
        area_m2: Number(draft.area_m2),
        lat: draft.lat !== "" ? Number(draft.lat) : null,
        lng: draft.lng !== "" ? Number(draft.lng) : null,
        status: site.status,
      };
      await updateSite(site.id, payload);
      await onRefresh(site.id);
      setIsEditing(false);
    } catch (err) {
      setError(err.message ?? "Ошибка сохранения");
    } finally {
      setSaving(false);
    }
  };

  const goToStatus = async (to, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) return;
    setStatusErr("");
    setStatusBusy(true);
    try {
      await onUpdateStatus(site.id, to);
    } catch (err) {
      setStatusErr(err.message ?? "Нельзя установить этот статус");
    } finally {
      setStatusBusy(false);
    }
  };

  // Метаданные статусов приходят с бэка (site.workflow).
  const transitions = site.workflow?.transitions ?? [];
  // Подсказка — только для следующего этапа воронки (forward-переход).
  const forward = transitions.find((t) => t.kind === "forward");
  const forwardBlocked = (forward?.requirements?.length ?? 0) > 0;

  // В списке доступны только валидные переходы без невыполненных условий.
  const byTo = new Map(transitions.map((t) => [t.to, t]));
  const isSelectable = (value) => {
    if (value === site.status) return true;
    const t = byTo.get(value);
    return !!t && (t.requirements?.length ?? 0) === 0;
  };

  const handleSelect = (e) => {
    const to = e.target.value;
    if (to === site.status) return;
    const t = byTo.get(to);
    const confirmText =
      t?.kind === "archive"
        ? "Отправить площадку в архив? Она пропадёт из реестра и метрик."
        : undefined;
    goToStatus(to, confirmText);
  };

  const cancelEdit = () => {
    setDraft(buildDraft(site));
    const restored = joinAddress(site);
    setAddressText(restored);
    setAddressSnapshot(restored);
    setIsEditing(false);
    setError("");
  };

  return (
    <div className="tab-content">
      {/* ── Панель управления статусом ── */}
      <div className="tab-toolbar">
        <div className="status-picker">
          <span>Статус:</span>
          {canChangeStatus ? (
            <select
              className="input status-select"
              value={site.status}
              onChange={handleSelect}
              disabled={statusBusy}
            >
              {STATUS_OPTIONS.map((o) => (
                <option
                  key={o.value}
                  value={o.value}
                  disabled={!isSelectable(o.value)}
                >
                  {o.label}
                </option>
              ))}
            </select>
          ) : (
            <StatusBadge status={site.status} />
          )}
        </div>

        {canEdit && (
          <button
            className="ghost-button"
            onClick={() => {
              setDraft(buildDraft(site));
              const restored = joinAddress(site);
              setAddressText(restored);
              setAddressSnapshot(restored);
              setIsEditing((e) => !e);
            }}
          >
            {isEditing ? "Отменить" : "Редактировать"}
          </button>
        )}
      </div>

      {/* Подсказка: что нужно для перехода на следующий этап */}
      {canChangeStatus && forwardBlocked && (
        <div className="status-hint">
          <span className="status-hint-title">
            Что нужно для перехода в «{forward.label}»:
          </span>
          <ul>
            {forward.requirements.map((req) => (
              <li key={req}>{req}</li>
            ))}
          </ul>
        </div>
      )}

      {statusErr && <div className="form-error">{statusErr}</div>}

      {/* ── Форма редактирования ── */}
      {isEditing ? (
        <form className="panel editor-form" onSubmit={handleSave}>
          {error && <div className="form-error">{error}</div>}

          <section className="editor-section">
            <h4>Основное</h4>
            <div className="form-grid">
              <label className="field field--full">
                <span>Название *</span>
                <input
                  className="input"
                  value={draft.title}
                  onChange={(e) => upd("title", e.target.value)}
                  required
                  maxLength={100}
                />
              </label>
              <div className="field field--full">
                <span>Город, район, адрес *</span>
                <AddressAutocomplete
                  value={addressText}
                  onChange={setAddressText}
                  onSelect={(picked) => {
                    const combined = joinAddress(picked);
                    setAddressText(combined);
                    setAddressSnapshot(combined);
                    setDraft((d) => ({
                      ...d,
                      city: picked.city || d.city,
                      district: picked.district || d.district,
                      address: picked.address || d.address,
                      lat: picked.lat ?? d.lat,
                      lng: picked.lng ?? d.lng,
                    }));
                  }}
                  placeholder="Например: Омск, Центральный, ул. Ленина, 5"
                  required
                  bias={{ city: draft.city }}
                />
              </div>
            </div>
          </section>

          <section className="editor-section">
            <h4>Параметры</h4>
            <div className="form-grid">
              <label className="field">
                <span>Тип площадки</span>
                <select
                  className="input"
                  value={draft.site_type}
                  onChange={(e) => upd("site_type", e.target.value)}
                >
                  {SITE_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Тип владельца</span>
                <select
                  className="input"
                  value={draft.owner_type}
                  onChange={(e) => upd("owner_type", e.target.value)}
                >
                  {OWNER_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Площадь, м²</span>
                <input
                  className="input"
                  type="number"
                  min="1"
                  value={draft.area_m2}
                  onChange={(e) => upd("area_m2", e.target.value)}
                />
              </label>
            </div>
          </section>

          <section className="editor-section">
            <h4>Координаты</h4>
            <div className="form-grid">
              <label className="field">
                <span>Широта</span>
                <input
                  className="input"
                  type="number"
                  step="any"
                  value={draft.lat}
                  onChange={(e) => upd("lat", e.target.value)}
                  placeholder="55.0411"
                />
              </label>
              <label className="field">
                <span>Долгота</span>
                <input
                  className="input"
                  type="number"
                  step="any"
                  value={draft.lng}
                  onChange={(e) => upd("lng", e.target.value)}
                  placeholder="73.3686"
                />
              </label>
            </div>
          </section>

          <section className="editor-section">
            <h4>Контакты</h4>
            <div className="form-grid">
              <label className="field">
                <span>Контактное лицо</span>
                <input
                  className="input"
                  value={draft.contact_name}
                  onChange={(e) => upd("contact_name", e.target.value)}
                />
              </label>
              <label className="field">
                <span>Телефон</span>
                <PhoneInput
                  value={draft.contact_phone}
                  onChange={(v) => upd("contact_phone", v)}
                />
              </label>
            </div>
          </section>

          <div className="form-actions">
            <button className="ghost-button" type="button" onClick={cancelEdit}>
              Отмена
            </button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? "Сохранение…" : "Сохранить"}
            </button>
          </div>
        </form>
      ) : (
        /* ── Просмотр ── */
        <>
          <div className="overview-top">
            <article className="panel">
              <div className="panel-heading">
                <h3>Основные данные</h3>
              </div>
              <div className="info-section">
                <h4 className="info-section-title">Площадка</h4>
                <dl className="details-grid">
                  <div>
                    <dt>Город, район</dt>
                    <dd>{formatCityDistrict(site)}</dd>
                  </div>
                  <div>
                    <dt>Адрес</dt>
                    <dd>{site.address}</dd>
                  </div>
                  <div>
                    <dt>Тип площадки</dt>
                    <dd>{SITE_TYPE_LABEL[site.site_type] ?? site.site_type}</dd>
                  </div>
                  <div>
                    <dt>Тип владельца</dt>
                    <dd>{OWNER_TYPE_LABEL[site.owner_type] ?? site.owner_type}</dd>
                  </div>
                  <div>
                    <dt>Площадь</dt>
                    <dd>{site.area_m2} м²</dd>
                  </div>
                  {site.lat != null && (
                    <div>
                      <dt>Координаты</dt>
                      <dd>
                        {formatCoord(site.lat)}, {formatCoord(site.lng)}
                      </dd>
                    </div>
                  )}
                </dl>
              </div>

              {(site.contact_name || site.contact_phone) && (
                <div className="info-section">
                  <h4 className="info-section-title">Контакты</h4>
                  <dl className="details-grid">
                    {site.contact_name && (
                      <div>
                        <dt>Контактное лицо</dt>
                        <dd>{site.contact_name}</dd>
                      </div>
                    )}
                    {site.contact_phone && (
                      <div>
                        <dt>Телефон</dt>
                        <dd>
                          <a
                            className="contact-phone-link"
                            href={`tel:${site.contact_phone.replace(/[^+\d]/g, "")}`}
                          >
                            {site.contact_phone}
                          </a>
                        </dd>
                      </div>
                    )}
                  </dl>
                </div>
              )}
            </article>

            <article className="panel metrics-panel">
              <div className="panel-heading">
                <h3>Показатели</h3>
              </div>
              <div className="metric-tiles">
                <div className="metric-tile metric-tile--score">
                  <span className="metric-label">Балл оценки</span>
                  <strong className="metric-value">
                    {site.score}
                    <small>%</small>
                  </strong>
                  <div className="metric-bar">
                    <span style={{ width: `${Math.min(100, Math.max(0, site.score))}%` }} />
                  </div>
                </div>
                <div className="metric-tile metric-tile--risk">
                  <span className="metric-label">Риск-скор</span>
                  <strong className="metric-value">
                    {site.risk_score}
                    <small>%</small>
                  </strong>
                  <div className="metric-bar">
                    <span style={{ width: `${Math.min(100, Math.max(0, site.risk_score))}%` }} />
                  </div>
                </div>
                <div className="metric-tile metric-tile--status">
                  <span className="metric-label">Статус</span>
                  <StatusBadge status={site.status} />
                </div>
              </div>
            </article>
          </div>

          {/* Оценка по категориям */}
          {site.category_scores?.length > 0 && (
            <article className="panel">
              <div className="panel-heading">
                <h3>Оценка по категориям</h3>
              </div>
              <div className="score-grid">
                {site.category_scores.map((cat) => {
                  const pct = Math.min(100, Math.max(0, cat.score));
                  return (
                    <div className="score-card" key={cat.code}>
                      <span className="score-label">{cat.label}</span>
                      <strong className="score-value">
                        {cat.score}
                        <small>%</small>
                      </strong>
                      <div className="score-bar">
                        <span
                          style={{
                            width: `${pct}%`,
                            background: `hsl(${Math.round(pct * 1.2)}, 65%, 50%)`,
                          }}
                        />
                      </div>
                    </div>
                  );
                })}
              </div>
            </article>
          )}
        </>
      )}
    </div>
  );
}
