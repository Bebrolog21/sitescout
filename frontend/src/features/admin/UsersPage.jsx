// src/features/admin/UsersPage.jsx
import { useEffect, useState } from 'react';
import { createUser, deleteUser, fetchUsers, updateUserRole } from '../../shared/api/users.js';
import {
  ASSIGNABLE_ROLES,
  ROLE_BADGE_CLASS,
  ROLE_LABEL,
  ROLES,
} from '../../shared/constants/roles.js';
import { IconEye, IconEyeOff, IconRefresh } from '../../shared/ui/Icons.jsx';

function emptyForm() {
  return { name: '', email: '', temporary_password: '', role: ROLES.ANALYST };
}

function generateTempPassword(length = 12) {
  const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#%&*';
  const cryptoObj = window.crypto || window.msCrypto;
  if (cryptoObj?.getRandomValues) {
    const buf = new Uint32Array(length);
    cryptoObj.getRandomValues(buf);
    return Array.from(buf, (n) => charset[n % charset.length]).join('');
  }
  return Array.from({ length }, () => charset[Math.floor(Math.random() * charset.length)]).join('');
}

export function UsersPage({ currentUser }) {
  const [users,    setUsers]    = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form,     setForm]     = useState(emptyForm);
  const [saving,   setSaving]   = useState(false);
  const [error,    setError]    = useState('');
  const [createdInfo, setCreatedInfo] = useState(null);
  const [tempPasswordVisible, setTempPasswordVisible] = useState(true);

  const load = () => {
    setLoading(true);
    fetchUsers()
      .then(setUsers)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const upd = (f, v) => setForm((d) => ({ ...d, [f]: v }));

  const handleCreate = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const user = await createUser(form);
      setUsers((prev) => [...prev, user]);
      setCreatedInfo({
        name: user.name,
        email: user.email,
        temporaryPassword: form.temporary_password,
      });
      setForm(emptyForm());
      setShowForm(false);
    } catch (err) {
      setError(err.message ?? 'Ошибка при создании пользователя');
    } finally {
      setSaving(false);
    }
  };

  const handleRoleChange = async (userId, role) => {
    try {
      const updated = await updateUserRole(userId, role);
      setUsers((prev) => prev.map((u) => (u.id === updated.id ? updated : u)));
    } catch (err) {
      alert(err.message ?? 'Не удалось изменить роль');
    }
  };

  const handleDelete = async (userId, userName) => {
    if (!window.confirm(`Удалить пользователя «${userName}»?`)) return;
    try {
      await deleteUser(userId);
      setUsers((prev) => prev.filter((u) => u.id !== userId));
    } catch (err) {
      alert(err.message ?? 'Не удалось удалить пользователя');
    }
  };

  return (
    <section className="page-stack">

      {/* Шапка */}
      <header className="page-header">
        <div>
          <p className="eyebrow">Администрирование</p>
          <h2>Управление пользователями</h2>
        </div>
        <button className="primary-button" onClick={() => setShowForm((o) => !o)}>
          {showForm ? 'Скрыть форму' : '+ Новый пользователь'}
        </button>
      </header>

      {/* Форма создания */}
      {showForm && (
        <form className="panel editor-form" onSubmit={handleCreate}>
          <div className="panel-heading"><h3>Новый пользователь</h3></div>
          {error && <div className="form-error">{error}</div>}
          <div className="form-grid">
            <label className="field">
              <span>Имя *</span>
              <input
                className="input"
                value={form.name}
                onChange={(e) => upd('name', e.target.value)}
                required
                placeholder="Иван Иванов"
              />
            </label>
            <label className="field">
              <span>Email *</span>
              <input
                  className="input"
                  type="email"
                  value={form.email}
                  onChange={(e) => upd('email', e.target.value)}
                  required
                  pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+.[a-zA-Z]{2,}"
                  title="Введите email в формате user@example.com"
                  placeholder="user@example.com"
              />
            </label>
            <label className="field field--full">
              <span>Временный пароль * (мин. 8 символов)</span>
              <div className="temp-password-row">
                <div className="password-field">
                  <input
                    className="input password-field__input"
                    type={tempPasswordVisible ? 'text' : 'password'}
                    value={form.temporary_password}
                    onChange={(e) => upd('temporary_password', e.target.value)}
                    required
                    minLength={8}
                    placeholder="Будет отправлен сотруднику"
                    autoComplete="off"
                  />
                  <button
                    type="button"
                    className="password-field__toggle"
                    onClick={() => setTempPasswordVisible((v) => !v)}
                    aria-label={tempPasswordVisible ? 'Скрыть пароль' : 'Показать пароль'}
                    tabIndex={-1}
                  >
                    {tempPasswordVisible ? <IconEyeOff size={18} /> : <IconEye size={18} />}
                  </button>
                </div>
                <button
                  className="ghost-button"
                  type="button"
                  onClick={() => upd('temporary_password', generateTempPassword())}
                >
                  <IconRefresh size={16} />
                  <span>Сгенерировать</span>
                </button>
              </div>
              <small className="field-hint">
                Сотрудник войдёт с этим паролем один раз и сразу же установит свой постоянный.
              </small>
            </label>
            <label className="field">
              <span>Роль</span>
              <select
                className="input"
                value={form.role}
                onChange={(e) => upd('role', e.target.value)}
              >
                {ASSIGNABLE_ROLES.map((r) => (
                  <option key={r.value} value={r.value}>{r.label}</option>
                ))}
              </select>
            </label>
          </div>
          <div className="form-actions">
            <button
              className="ghost-button"
              type="button"
              onClick={() => { setShowForm(false); setError(''); }}
            >
              Отмена
            </button>
            <button className="primary-button" type="submit" disabled={saving}>
              {saving ? 'Создание…' : 'Создать пользователя'}
            </button>
          </div>
        </form>
      )}

      {/* Таблица пользователей */}
      <article className="panel">
        <div className="panel-heading">
          <h3>Все пользователи</h3>
          <span>{users.length} аккаунтов</span>
        </div>

        {loading ? (
          <div className="loading-state">
            <div className="spinner" />
            <p>Загрузка…</p>
          </div>
        ) : (
          <div className="users-table">
            {users.map((user) => (
              <div className="user-row" key={user.id}>

                {/* Инфо */}
                <div className="user-info">
                  <div className="user-avatar">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <div>
                    <p className="user-name-cell">
                      {user.name}
                      {user.id === currentUser?.id && (
                        <span className="user-self-badge">вы</span>
                      )}
                      {user.password_change_required && (
                        <span className="user-pending-badge" title="Сотрудник ещё не сменил временный пароль">
                          ожидает смены пароля
                        </span>
                      )}
                    </p>
                    <p className="user-email-cell">{user.email}</p>
                  </div>
                </div>

                {/* Смена роли */}
                <div className="user-role-cell">
                  {user.id === currentUser?.id ? (
                    <span className={`role-badge ${ROLE_BADGE_CLASS[user.role]}`}>
                      {ROLE_LABEL[user.role]}
                    </span>
                  ) : (
                    <select
                      className="input role-select"
                      value={user.role}
                      onChange={(e) => handleRoleChange(user.id, e.target.value)}
                    >
                      {ASSIGNABLE_ROLES.map((r) => (
                        <option key={r.value} value={r.value}>{r.label}</option>
                      ))}
                    </select>
                  )}
                </div>

                {/* Удаление */}
                <div className="user-actions">
                  {user.id !== currentUser?.id && (
                    <button
                      className="ghost-button danger-button"
                      onClick={() => handleDelete(user.id, user.name)}
                    >
                      Удалить
                    </button>
                  )}
                </div>

              </div>
            ))}
          </div>
        )}
      </article>

      {/* Модалка с креденшалами созданного пользователя */}
      {createdInfo && (
        <div className="modal-backdrop" role="dialog" aria-modal="true">
          <div className="modal-card">
            <h3>Пользователь создан</h3>
            <p className="modal-text">
              Передайте сотруднику email и временный пароль — при первом входе система
              попросит его установить постоянный пароль.
            </p>
            <div className="credentials-block">
              <div>
                <span className="credentials-label">Email</span>
                <code>{createdInfo.email}</code>
              </div>
              <div>
                <span className="credentials-label">Временный пароль</span>
                <code>{createdInfo.temporaryPassword}</code>
              </div>
            </div>
            <div className="form-actions">
              <button
                className="ghost-button"
                type="button"
                onClick={() => {
                  navigator.clipboard?.writeText(
                    `Email: ${createdInfo.email}\nВременный пароль: ${createdInfo.temporaryPassword}`,
                  );
                }}
              >
                Скопировать
              </button>
              <button
                className="primary-button"
                type="button"
                onClick={() => setCreatedInfo(null)}
              >
                Готово
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Подсказка по ролям */}
      <article className="panel">
        <div className="panel-heading"><h3>Права доступа по ролям</h3></div>
        <div className="roles-legend">
          <div className="role-item">
            <span className={`role-badge ${ROLE_BADGE_CLASS[ROLES.ADMIN]}`}>{ROLE_LABEL[ROLES.ADMIN]}</span>
            <p>Полный доступ. Управление пользователями, справочниками, всеми площадками.</p>
          </div>
          <div className="role-item">
            <span className={`role-badge ${ROLE_BADGE_CLASS[ROLES.ANALYST]}`}>{ROLE_LABEL[ROLES.ANALYST]}</span>
            <p>Создаёт площадки, заполняет чеклист, загружает файлы, формирует отчёты. Не может редактировать справочник критериев.</p>
          </div>
          <div className="role-item">
            <span className={`role-badge ${ROLE_BADGE_CLASS[ROLES.MANAGER]}`}>{ROLE_LABEL[ROLES.MANAGER]}</span>
            <p>Просматривает площадки и рейтинги, меняет статусы, смотрит финансовые модели и риски.</p>
          </div>
        </div>
      </article>

    </section>
  );
}
