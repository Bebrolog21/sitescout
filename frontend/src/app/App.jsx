// src/app/App.jsx
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Navigate,
  NavLink,
  Route,
  Routes,
  useLocation,
  useNavigate,
  useSearchParams,
} from "react-router-dom";
import { UsersPage } from "../features/admin/UsersPage.jsx";
import { ChangeInitialPasswordPage } from "../features/auth/ChangeInitialPasswordPage.jsx";
import { ForgotPasswordPage } from "../features/auth/ForgotPasswordPage.jsx";
import { LoginPage } from "../features/auth/LoginPage.jsx";
import { ResetPasswordPage } from "../features/auth/ResetPasswordPage.jsx";
import { DashboardPage } from "../features/dashboard/DashboardPage.jsx";
import { SiteDetailRoute } from "../features/sites/SiteDetailRoute.jsx";
import { SitesPage } from "../features/sites/SitesPage.jsx";
import {
  clearToken,
  getMe,
  isAuthenticated,
  logout,
} from "../shared/api/http.js";
import {
  createSite,
  fetchSite,
  fetchSites,
  updateSiteStatus,
} from "../shared/api/sites.js";
import { ROLE_LABEL } from "../shared/constants/roles.js";
import { calcPortfolioMetrics } from "../shared/lib/calculations.js";
import { isAdmin as isAdminUser } from "../shared/lib/permissions.js";
import {
  IconChevronLeft,
  IconChevronRight,
  IconDashboard,
  IconLogoMark,
  IconLogout,
  IconPassport,
  IconSites,
  IconUsers,
} from "../shared/ui/Icons.jsx";

const SIDEBAR_PREF_KEY = "sitescout_sidebar_collapsed";

function getInitials(name = "") {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export default function App() {
  // ── Аутентификация ─────────────────────────────────────────
  const [user, setUser] = useState(null);
  const [authChecked, setAuthChecked] = useState(false);

  useEffect(() => {
    if (!isAuthenticated()) {
      setAuthChecked(true);
      return;
    }
    getMe()
      .then(setUser)
      .catch(() => clearToken())
      .finally(() => setAuthChecked(true));
  }, []);

  // ── Площадки ───────────────────────────────────────────────
  const [sites, setSites] = useState([]);
  const [sitesLoading, setSitesLoading] = useState(false);

  const loadSites = useCallback(async () => {
    setSitesLoading(true);
    try {
      setSites(await fetchSites());
    } finally {
      setSitesLoading(false);
    }
  }, []);

  useEffect(() => {
    if (user && !user.password_change_required) loadSites();
  }, [user, loadSites]);

  // ── Сайдбар ────────────────────────────────────────────────
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(() => {
    try {
      return window.localStorage.getItem(SIDEBAR_PREF_KEY) === "1";
    } catch {
      return false;
    }
  });

  useEffect(() => {
    try {
      window.localStorage.setItem(
        SIDEBAR_PREF_KEY,
        isSidebarCollapsed ? "1" : "0",
      );
    } catch {
      /* noop */
    }
  }, [isSidebarCollapsed]);

  // Последняя открытая площадка — гейт для пункта «Паспорт» в сайдбаре.
  // Намеренно НЕ персистим: в каждой новой сессии пользователь должен сначала
  // открыть хотя бы одну площадку, прежде чем ссылка «Паспорт» станет активной.
  const [lastSiteId, setLastSiteId] = useState(null);

  const updateLastSiteId = useCallback((id) => {
    setLastSiteId(id);
  }, []);

  const metrics = useMemo(() => calcPortfolioMetrics(sites), [sites]);
  const isAdmin = isAdminUser(user);

  // ── Действия над площадками ───────────────────────────────
  const handleCreateSite = useCallback(async (data) => {
    const newSite = await createSite(data);
    setSites((prev) => [newSite, ...prev]);
    return newSite;
  }, []);

  const handleUpdateStatus = useCallback(async (siteId, status) => {
    await updateSiteStatus(siteId, status);
    const fresh = await fetchSite(siteId);
    setSites((prev) => prev.map((s) => (s.id === siteId ? fresh : s)));
  }, []);

  const handleRefreshSite = useCallback(async (siteId) => {
    const fresh = await fetchSite(siteId);
    setSites((prev) => prev.map((s) => (s.id === siteId ? fresh : s)));
  }, []);

  const upsertSite = useCallback((fresh) => {
    setSites((prev) => {
      const idx = prev.findIndex((s) => s.id === fresh.id);
      if (idx === -1) return [fresh, ...prev];
      const next = prev.slice();
      next[idx] = fresh;
      return next;
    });
  }, []);

  const handleLogout = useCallback(async () => {
    try {
      await logout();
    } catch {
      /* noop */
    }
    setUser(null);
    setSites([]);
    updateLastSiteId(null);
  }, [updateLastSiteId]);

  // ── Рендер ─────────────────────────────────────────────────
  if (!authChecked) {
    return (
      <div className="loading-state full-screen">
        <div className="spinner" />
        <p>Загрузка…</p>
      </div>
    );
  }

  if (!user) {
    return <PublicRoutes onAuthenticated={setUser} />;
  }

  if (user.password_change_required) {
    return (
      <PasswordChangeGate
        user={user}
        onSuccess={(updated) => setUser(updated ?? { ...user, password_change_required: false })}
        onCancel={() => { clearToken(); setUser(null); }}
      />
    );
  }

  return (
    <AppShell
      user={user}
      metrics={metrics}
      isAdmin={isAdmin}
      isSidebarCollapsed={isSidebarCollapsed}
      onToggleSidebar={() => setIsSidebarCollapsed((s) => !s)}
      lastSiteId={lastSiteId}
      onLogout={handleLogout}
    >
      <Routes>
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route
          path="/dashboard"
          element={<DashboardPage metrics={metrics} sites={sites} />}
        />
        <Route
          path="/sites"
          element={
            <SitesPage
              sites={sites}
              user={user}
              onCreateSite={handleCreateSite}
            />
          }
        />
        <Route
          path="/sites/:siteId"
          element={
            <SiteDetailRoute
              sites={sites}
              sitesLoading={sitesLoading}
              user={user}
              onUpdateStatus={handleUpdateStatus}
              onRefreshSite={handleRefreshSite}
              onSitesUpsert={upsertSite}
              onLastSiteChange={updateLastSiteId}
            />
          }
        />
        <Route
          path="/sites/:siteId/:tab"
          element={
            <SiteDetailRoute
              sites={sites}
              sitesLoading={sitesLoading}
              user={user}
              onUpdateStatus={handleUpdateStatus}
              onRefreshSite={handleRefreshSite}
              onSitesUpsert={upsertSite}
              onLastSiteChange={updateLastSiteId}
            />
          }
        />
        <Route
          path="/users"
          element={isAdmin ? <UsersPage currentUser={user} /> : <Navigate to="/dashboard" replace />}
        />
        {/* Аутентифицированный пользователь не должен видеть формы логина — кидаем на дашборд */}
        <Route path="/login" element={<Navigate to="/dashboard" replace />} />
        <Route path="/forgot-password" element={<Navigate to="/dashboard" replace />} />
        <Route path="/reset-password" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
      {sitesLoading && sites.length === 0 && (
        <div className="loading-state">
          <div className="spinner" />
          <p>Загрузка площадок…</p>
        </div>
      )}
    </AppShell>
  );
}

// ── Публичные роуты (не залогинен) ─────────────────────────────
function PublicRoutes({ onAuthenticated }) {
  const navigate = useNavigate();
  return (
    <Routes>
      <Route
        path="/login"
        element={
          <LoginPage
            onSuccess={(u) => {
              onAuthenticated(u);
              navigate("/dashboard", { replace: true });
            }}
            onForgotPassword={() => navigate("/forgot-password")}
          />
        }
      />
      <Route
        path="/forgot-password"
        element={<ForgotPasswordPage onBack={() => navigate("/login")} />}
      />
      <Route
        path="/reset-password"
        element={<ResetPasswordRouteElement onDone={() => navigate("/login", { replace: true })} />}
      />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}

function ResetPasswordRouteElement({ onDone }) {
  const [params] = useSearchParams();
  return (
    <ResetPasswordPage
      token={params.get("token") ?? ""}
      email={params.get("email") ?? ""}
      onSuccess={onDone}
      onBack={onDone}
    />
  );
}

// ── Гейт смены временного пароля ───────────────────────────────
function PasswordChangeGate({ user, onSuccess, onCancel }) {
  const location = useLocation();
  if (location.pathname !== "/change-password") {
    return <Navigate to="/change-password" replace />;
  }
  return (
    <Routes>
      <Route
        path="/change-password"
        element={
          <ChangeInitialPasswordPage
            user={user}
            onSuccess={onSuccess}
            onCancel={onCancel}
          />
        }
      />
      <Route path="*" element={<Navigate to="/change-password" replace />} />
    </Routes>
  );
}

// ── Каркас приложения (сайдбар + контент) ──────────────────────
function AppShell({
  user,
  metrics,
  isAdmin,
  isSidebarCollapsed,
  onToggleSidebar,
  lastSiteId,
  onLogout,
  children,
}) {
  const passportTarget = lastSiteId ? `/sites/${lastSiteId}/overview` : null;

  return (
    <div className={isSidebarCollapsed ? "app-shell sidebar-collapsed" : "app-shell"}>
      <aside className={isSidebarCollapsed ? "sidebar is-collapsed" : "sidebar"}>
        <div className="sidebar-top">
          <div className="sidebar-brand">
            <span className="brand-mark" aria-hidden="true">
              <IconLogoMark size={32} />
            </span>
            {!isSidebarCollapsed && <h1 className="brand-text">SiteScout</h1>}
          </div>

          <button
            className="sidebar-toggle"
            onClick={onToggleSidebar}
            aria-label={isSidebarCollapsed ? "Развернуть боковую панель" : "Свернуть боковую панель"}
            type="button"
          >
            {isSidebarCollapsed ? <IconChevronRight size={18} /> : <IconChevronLeft size={16} />}
          </button>
        </div>

        <nav className="sidebar-nav" aria-label="Основная навигация">
          <SidebarLink to="/dashboard" Icon={IconDashboard} label="Дашборд" />
          <SidebarLink to="/sites" Icon={IconSites} label="Площадки" />
          {passportTarget ? (
            <SidebarLink to={passportTarget} Icon={IconPassport} label="Паспорт" matchPrefix="/sites/" />
          ) : (
            <button className="nav-link" type="button" disabled>
              <span className="nav-icon"><IconPassport size={18} /></span>
              <span className="nav-label">Паспорт</span>
            </button>
          )}
          {isAdmin && (
            <SidebarLink to="/users" Icon={IconUsers} label="Пользователи" />
          )}
        </nav>

        <div className="sidebar-footer">
          {!isSidebarCollapsed && (
            <div className="sidebar-summary">
              <span>Средний балл</span>
              <strong>{metrics.averageScore}%</strong>
              <span>Согласовано</span>
              <strong>{metrics.approvedSites}</strong>
            </div>
          )}

          <div className="sidebar-user">
            <div className="user-avatar">{getInitials(user?.name)}</div>
            {!isSidebarCollapsed && (
              <div className="user-meta">
                <span className="user-name">{user?.name}</span>
                <span className="user-role">{ROLE_LABEL[user?.role] ?? user?.role}</span>
              </div>
            )}
          </div>

          <button className="logout-btn" onClick={onLogout} aria-label="Выйти" type="button">
            <IconLogout size={16} />
            {!isSidebarCollapsed && <span>Выйти</span>}
          </button>
        </div>
      </aside>

      <main className="content-shell">{children}</main>
    </div>
  );
}

function SidebarLink({ to, Icon, label, matchPrefix }) {
  const location = useLocation();
  const isActive = matchPrefix
    ? location.pathname.startsWith(matchPrefix)
    : undefined;
  return (
    <NavLink
      to={to}
      className={({ isActive: routerActive }) => {
        const active = isActive ?? routerActive;
        return `nav-link ${active ? "is-active" : ""}`;
      }}
      end={!matchPrefix && to !== "/sites"}
    >
      <span className="nav-icon"><Icon size={18} /></span>
      <span className="nav-label">{label}</span>
    </NavLink>
  );
}
