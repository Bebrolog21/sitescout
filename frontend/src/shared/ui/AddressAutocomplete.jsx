// src/shared/ui/AddressAutocomplete.jsx
// Подсказки адресов через DaData Suggestions API.
// Документация: https://dadata.ru/api/suggest/address/
//
// Token для Suggestions API специально проектируется как «публичный» —
// его можно класть прямо в JS-код. См. https://dadata.ru/api/.
//
// Конфигурация: VITE_DADATA_TOKEN в frontend/.env (или .env.local).
// При отсутствии токена компонент тихо отключается и инпут работает как обычное
// поле ввода — пользователь всё равно может заполнить адрес руками.

import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const DADATA_URL =
  import.meta.env.VITE_DADATA_URL ??
  'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';
const DADATA_GEOLOCATE_URL =
  import.meta.env.VITE_DADATA_GEOLOCATE_URL ??
  'https://suggestions.dadata.ru/suggestions/api/4_1/rs/geolocate/address';
const DADATA_TOKEN = import.meta.env.VITE_DADATA_TOKEN ?? '';
const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 250;

// Координаты крупных российских городов имеют city_district в БД DaData,
// но для уровня "улица" это поле часто пустое. Когда первичный pick не дал
// район, добиваем reverse-геокодом через /geolocate/address.
async function enrichDistrictByCoords(lat, lng) {
  if (!DADATA_TOKEN) {
    // eslint-disable-next-line no-console
    console.warn('[DaData /geolocate] токен не задан (VITE_DADATA_TOKEN)');
    return null;
  }
  if (lat == null || lng == null) {
    // eslint-disable-next-line no-console
    console.warn('[DaData /geolocate] нет координат');
    return null;
  }
  try {
    const r = await fetch(DADATA_GEOLOCATE_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Token ${DADATA_TOKEN}`,
      },
      body: JSON.stringify({ lat, lon: lng, count: 1, radius_meters: 200 }),
    });
    if (!r.ok) {
      // eslint-disable-next-line no-console
      console.warn(`[DaData /geolocate] HTTP ${r.status}`, await r.text());
      return null;
    }
    const data = await r.json();
    const first = Array.isArray(data?.suggestions) ? data.suggestions[0] : null;
    return first?.data ?? null;
  } catch (e) {
    // eslint-disable-next-line no-console
    console.warn('[DaData /geolocate] ошибка запроса:', e);
    return null;
  }
}

export function AddressAutocomplete({
  value,
  onChange,
  onSelect,
  placeholder,
  required,
  disabled,
  limit = 7,
  bias,
}) {
  const [suggestions, setSuggestions] = useState([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [highlight, setHighlight] = useState(-1);
  const [rect, setRect] = useState(null);
  const [errorMsg, setErrorMsg] = useState('');

  const inputRef = useRef(null);
  const listRef = useRef(null);
  const abortRef = useRef(null);
  const debounceRef = useRef(null);

  const tokenMissing = !DADATA_TOKEN;

  const fetchSuggestions = useCallback(
    (query) => {
      const trimmed = (query ?? '').trim();
      if (trimmed.length < MIN_QUERY_LENGTH) {
        setSuggestions([]);
        setOpen(false);
        return;
      }
      if (tokenMissing) {
        setErrorMsg('Токен DaData не настроен (VITE_DADATA_TOKEN).');
        setOpen(true);
        return;
      }

      if (abortRef.current) abortRef.current.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      const body = {
        query: trimmed,
        count: limit,
      };
      if (bias?.city) {
        // подталкиваем к указанному городу — он окажется первым в списке.
        body.locations_boost = [{ kladr_id: '55' }, { kladr_id: '54' }]; // Омская обл., Новосибирская
      }

      setLoading(true);
      setOpen(true);
      setErrorMsg('');
      fetch(DADATA_URL, {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Token ${DADATA_TOKEN}`,
        },
        body: JSON.stringify(body),
      })
        .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
        .then((data) => {
          setSuggestions(Array.isArray(data?.suggestions) ? data.suggestions : []);
          setHighlight(-1);
        })
        .catch((err) => {
          if (err?.name === 'AbortError') return;
          // eslint-disable-next-line no-console
          console.warn('[AddressAutocomplete] DaData fetch failed:', err);
          setSuggestions([]);
          setErrorMsg(err?.message ?? 'Не удалось получить подсказки');
        })
        .finally(() => setLoading(false));
    },
    [bias?.city, limit, tokenMissing],
  );

  const handleInput = (e) => {
    const v = e.target.value;
    onChange?.(v);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => fetchSuggestions(v), DEBOUNCE_MS);
  };

  const pick = (item) => {
    const parsed = parseDaData(item);

    // Всегда логируем, чтобы видеть в DevTools, что именно вернул DaData.
    // eslint-disable-next-line no-console
    console.log('[DaData /suggest] выбрана подсказка:', {
      value: item?.value,
      district_parsed: parsed.district,
      raw: item?.data,
    });

    onSelect?.(parsed);
    setOpen(false);
    setSuggestions([]);

    if (parsed.district) return;

    if (parsed.lat == null || parsed.lng == null) {
      // eslint-disable-next-line no-console
      console.warn('[DaData] район пустой и нет координат — fallback невозможен', item?.data);
      return;
    }

    // Reverse-геокод через /geolocate/address — иногда возвращает более полные данные.
    enrichDistrictByCoords(parsed.lat, parsed.lng).then((richer) => {
      const recovered = richer ? pickDistrictFromData(richer) : '';
      // eslint-disable-next-line no-console
      console.log('[DaData /geolocate] результат:', { recovered, raw: richer });

      if (recovered) {
        onSelect?.({ ...parsed, district: recovered });
        return;
      }

      // eslint-disable-next-line no-console
      console.warn('[DaData] район определить не удалось ни одним способом', {
        suggest: item?.data,
        geolocate: richer,
      });
    });
  };

  const updateRect = useCallback(() => {
    if (!inputRef.current) return;
    const r = inputRef.current.getBoundingClientRect();
    setRect({ top: r.bottom, left: r.left, width: r.width });
  }, []);

  useLayoutEffect(() => {
    if (!open) return undefined;
    updateRect();
    window.addEventListener('scroll', updateRect, true);
    window.addEventListener('resize', updateRect);
    return () => {
      window.removeEventListener('scroll', updateRect, true);
      window.removeEventListener('resize', updateRect);
    };
  }, [open, updateRect]);

  useEffect(() => {
    if (!open) return undefined;
    const handler = (e) => {
      const t = e.target;
      if (inputRef.current?.contains(t)) return;
      if (listRef.current?.contains(t)) return;
      setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  useEffect(
    () => () => {
      if (abortRef.current) abortRef.current.abort();
      clearTimeout(debounceRef.current);
    },
    [],
  );

  const handleKeyDown = (e) => {
    if (!open || suggestions.length === 0) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlight((h) => (h + 1) % suggestions.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlight((h) => (h <= 0 ? suggestions.length - 1 : h - 1));
    } else if (e.key === 'Enter' && highlight >= 0) {
      e.preventDefault();
      pick(suggestions[highlight]);
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  };

  const dropdown =
    open && rect && (suggestions.length > 0 || loading || errorMsg) ? (
      <ul
        ref={listRef}
        className="address-autocomplete__list"
        role="listbox"
        style={{
          position: 'fixed',
          top: rect.top + 4,
          left: rect.left,
          width: rect.width,
        }}
      >
        {loading && suggestions.length === 0 && (
          <li className="address-autocomplete__hint">Поиск…</li>
        )}
        {!loading && suggestions.length === 0 && errorMsg && (
          <li className="address-autocomplete__hint">Ошибка: {errorMsg}</li>
        )}
        {!loading && suggestions.length === 0 && !errorMsg && (
          <li className="address-autocomplete__hint">Ничего не найдено</li>
        )}
        {suggestions.map((item, i) => {
          const { primary, secondary } = formatLabel(item);
          return (
            <li
              key={`${item.data?.fias_id ?? item.value ?? ''}-${i}`}
              role="option"
              aria-selected={i === highlight}
              className={`address-autocomplete__item ${i === highlight ? 'is-active' : ''}`}
              onMouseDown={(e) => {
                e.preventDefault();
                pick(item);
              }}
              onMouseEnter={() => setHighlight(i)}
            >
              <span className="address-autocomplete__primary">{primary}</span>
              {secondary && <span className="address-autocomplete__secondary">{secondary}</span>}
            </li>
          );
        })}
      </ul>
    ) : null;

  return (
    <div className="address-autocomplete">
      <input
        ref={inputRef}
        className="input"
        value={value ?? ''}
        onChange={handleInput}
        onFocus={() => {
          if (suggestions.length > 0) {
            updateRect();
            setOpen(true);
          }
        }}
        onKeyDown={handleKeyDown}
        placeholder={placeholder}
        required={required}
        disabled={disabled}
        autoComplete="off"
        role="combobox"
        aria-expanded={open}
        aria-autocomplete="list"
      />
      {loading && <span className="address-autocomplete__loading" aria-hidden="true">…</span>}
      {typeof document !== 'undefined' && createPortal(dropdown, document.body)}
    </div>
  );
}

// ── Парсинг ответа DaData ─────────────────────────────────────

// Возвращает первый непустой строковый аргумент. Чисто для читаемости цепочек.
function pickFirst(...values) {
  for (const v of values) {
    if (typeof v === 'string' && v.trim() !== '') return v.trim();
  }
  return '';
}

// Достаёт человеко-читаемое название района из DaData-data.
// Перебирает поля по приоритету: специфичные для города → общие → settlement как fallback.
export function pickDistrictFromData(d) {
  if (!d) return '';
  return pickFirst(
    d.city_district_with_type,                                      // "Кировский р-н" — в составе города
    d.city_district,                                                // "Кировский" — короткое
    d.city_area,                                                    // "Зеленоград" — спецзоны (Москва)
    d.area_with_type,                                               // "Любинский р-н" — район области
    d.area,                                                         // короткое имя района области
    d.settlement_with_type && d.settlement_with_type !== d.city_with_type
      ? d.settlement_with_type
      : null,
  );
}

function parseDaData(item) {
  const d = item?.data ?? {};

  const city = pickFirst(d.city, d.settlement, d.region);
  const district = pickDistrictFromData(d);

  // Адрес: улица + дом (с типом, если есть).
  let address = '';
  if (d.street_with_type) {
    address = d.house ? `${d.street_with_type}, ${d.house_type ?? 'д'} ${d.house}` : d.street_with_type;
  } else if (d.settlement_with_type) {
    address = d.settlement_with_type;
  } else {
    address = item?.value ?? '';
  }

  const lat = d.geo_lat != null ? Number(d.geo_lat) : null;
  const lng = d.geo_lon != null ? Number(d.geo_lon) : null;

  return {
    address,
    city,
    district,
    lat: Number.isFinite(lat) ? lat : null,
    lng: Number.isFinite(lng) ? lng : null,
    raw: item,
  };
}

function formatLabel(item) {
  const d = item?.data ?? {};

  // Primary — улица + дом или верхушка населённого пункта.
  let primary = '';
  if (d.street_with_type) {
    primary = d.house ? `${d.street_with_type}, ${d.house_type ?? 'д'} ${d.house}` : d.street_with_type;
  } else if (d.settlement_with_type) {
    primary = d.settlement_with_type;
  } else if (d.city_with_type) {
    primary = d.city_with_type;
  } else {
    primary = item?.value ?? '';
  }

  // Secondary — район, город, регион.
  const secondaryParts = [];
  const city = d.city_with_type ?? d.settlement_with_type;
  if (city && !primary.includes(city)) secondaryParts.push(city);
  if (d.city_district_with_type) secondaryParts.push(d.city_district_with_type);
  if (d.region_with_type && d.region_with_type !== city) secondaryParts.push(d.region_with_type);

  return {
    primary: primary || item?.value || '(без названия)',
    secondary: secondaryParts.join(', '),
  };
}
