// src/features/sites/SitesPage.jsx
import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { fetchSites } from "../../shared/api/sites.js";
import { joinAddress, splitAddress } from "../../shared/lib/addressFormat.js";
import { canEditSiteContent } from "../../shared/lib/permissions.js";
import { AddressAutocomplete } from "../../shared/ui/AddressAutocomplete.jsx";
import { StatusBadge } from "../../shared/ui/StatusBadge.jsx";

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

function emptySiteDraft() {
  return {
    title: "",
    city: "Омск",
    district: "",
    address: "",
    site_type: "parking",
    area_m2: 120,
    owner_type: "management_company",
    status: "new",
    lat: null,
    lng: null,
  };
}

export function SitesPage({ sites, user, onCreateSite }) {
  const navigate = useNavigate();
  const canEdit = canEditSiteContent(user);
  const openSite = (id) => navigate(`/sites/${id}/overview`);
  const [query, setQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [view, setView] = useState("active"); // 'active' | 'archive'
  const [archivedSites, setArchivedSites] = useState([]);
  const [archivedLoading, setArchivedLoading] = useState(false);
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [draft, setDraft] = useState(emptySiteDraft);
  const [addressText, setAddressText] = useState(() => joinAddress(emptySiteDraft()));
  // Снимок строки на момент последнего пика из автокомплита.
  // Если на submit addressText === addressSnapshot, доверяем draft (DaData уже
  // разложила всё по полям). Если поменялся — фолбэчно сплитим по запятым.
  const [addressSnapshot, setAddressSnapshot] = useState(() => joinAddress(emptySiteDraft()));
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState("");

  // Подгружаем архив отдельным запросом (бэкенд по умолчанию архив не отдаёт).
  useEffect(() => {
    if (view !== "archive") return;
    setArchivedLoading(true);
    fetchSites({ status: "archived" })
      .then(setArchivedSites)
      .catch(() => setArchivedSites([]))
      .finally(() => setArchivedLoading(false));
  }, [view]);

  // Список под текущую вкладку. На «Активные» из общего стейта дополнительно
  // отсекаем архив (на случай, если площадка только что туда переведена и ещё
  // не пропала из локального кэша App).
  const baseList = view === "archive"
    ? archivedSites
    : sites.filter((s) => s.status !== "archived");

  const filtered = useMemo(() => {
    return baseList.filter((site) => {
      const q = query.trim().toLowerCase();
      const matchQ =
        q === "" ||
        site.title.toLowerCase().includes(q) ||
        site.address.toLowerCase().includes(q) ||
        site.district.toLowerCase().includes(q);
      const matchStatus =
        statusFilter === "all" || site.status === statusFilter;
      return matchQ && matchStatus;
    });
  }, [query, statusFilter, baseList]);

  const upd = (field, value) => setDraft((d) => ({ ...d, [field]: value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setSaveError("");
    try {
      // Если строка не менялась после пика DaData — берём draft как есть.
      // Иначе пытаемся аккуратно разделить пользовательский ввод по запятым.
      const usePick = addressText === addressSnapshot;
      const split = usePick ? null : splitAddress(addressText);
      const payload = usePick
        ? { ...draft }
        : {
            ...draft,
            city: split.city || draft.city,
            district: split.district || draft.district,
            address: split.address || draft.address,
          };
      const newSite = await onCreateSite(payload);
      const fresh = emptySiteDraft();
      setDraft(fresh);
      const freshAddr = joinAddress(fresh);
      setAddressText(freshAddr);
      setAddressSnapshot(freshAddr);
      setIsFormOpen(false);
      if (newSite?.id) {
        navigate(`/sites/${newSite.id}/overview`);
      }
    } catch (err) {
      setSaveError(err.message ?? "Ошибка при создании площадки");
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="page-stack">
      {/* ── Шапка ── */}
      <header className="page-header">
        <div>
          <p className="eyebrow">Реестр площадок</p>
          <h2>{view === "archive" ? "Архив площадок" : "Поиск, фильтрация и управление"}</h2>
        </div>
        {canEdit && view === "active" && (
          <button
            className="primary-button"
            onClick={() => setIsFormOpen((o) => !o)}
          >
            {isFormOpen ? "Скрыть форму" : "+ Добавить площадку"}
          </button>
        )}
      </header>

      {/* ── Вкладки ── */}
      <nav className="tab-bar">
        <button
          type="button"
          className={`tab-btn ${view === "active" ? "active" : ""}`}
          onClick={() => setView("active")}
        >
          Активные
          <span className="tab-counter">
            {sites.filter((s) => s.status !== "archived").length}
          </span>
        </button>
        <button
          type="button"
          className={`tab-btn ${view === "archive" ? "active" : ""}`}
          onClick={() => setView("archive")}
        >
          Архив
          {archivedSites.length > 0 && (
            <span className="tab-counter">{archivedSites.length}</span>
          )}
        </button>
      </nav>

      {/* ── Форма создания ── */}
      {isFormOpen && (
        <form className="panel editor-form" onSubmit={handleSubmit}>
          <div className="panel-heading">
            <h3>Новая площадка</h3>
          </div>
          {saveError && <div className="form-error">{saveError}</div>}

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
                  placeholder="ЖК Северный"
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
                  onChange={(e) => upd("area_m2", Number(e.target.value))}
                />
              </label>
            </div>
          </section>
          <div className="form-actions">
            <button
              className="ghost-button"
              type="button"
              onClick={() => {
                setIsFormOpen(false);
                setSaveError("");
              }}
            >
              Отмена
            </button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? "Создание…" : "Создать площадку"}
            </button>
          </div>
        </form>
      )}

      {/* ── Фильтры ── */}
      <article className="panel filter-bar">
        <input
          className="input"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Поиск по названию, адресу или району…"
        />
        <select
          className="input"
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
        >
          <option value="all">Все статусы</option>
          <option value="new">Новая</option>
          <option value="screening">Скрининг</option>
          <option value="inspection">Осмотр</option>
          <option value="scoring">Скоринг</option>
          <option value="negotiation">Согласование</option>
          <option value="approved">Согласована</option>
          <option value="rejected">Отклонена</option>
          <option value="launched">Запущена</option>
        </select>
      </article>

      {/* ── Таблица площадок ── */}
      <article className="panel">
        <div className="table-head">
          <span>{filtered.length} площадок найдено</span>
        </div>
        <div className="table-like">
          {filtered.length > 0 && (
            <div className="table-row table-row--head" aria-hidden="true">
              <span>Площадка</span>
              <span>Тип</span>
              <span className="cell-numeric">Площадь</span>
              <span className="cell-numeric">Балл</span>
              <span className="cell-status">Статус</span>
            </div>
          )}

          {archivedLoading && view === "archive" && (
            <div className="loading-state">
              <div className="spinner" />
              <p>Загрузка архива…</p>
            </div>
          )}
          {!archivedLoading && filtered.length === 0 && (
            <p className="empty-state">
              {view === "archive"
                ? "В архиве пока пусто."
                : "Ничего не найдено. Попробуйте изменить фильтры или добавьте площадку."}
            </p>
          )}
          {filtered.map((site) => (
            <button
              className="table-row"
              key={site.id}
              onClick={() => openSite(site.id)}
            >
              <div className="row-info">
                <p className="site-title">{site.title}</p>
                <p className="site-copy">
                  {site.city}, {site.district} — {site.address}
                </p>
              </div>
              <span className="cell-type">
                {SITE_TYPE_LABEL[site.site_type] ?? site.site_type}
              </span>
              <span className="cell-numeric">{site.area_m2} м²</span>
              <span className="cell-numeric">
                <span className="score-pill">{site.score}%</span>
              </span>
              <span className="cell-status">
                <StatusBadge status={site.status} />
              </span>
            </button>
          ))}
        </div>
      </article>
    </section>
  );
}
