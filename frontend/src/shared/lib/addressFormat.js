// src/shared/lib/addressFormat.js
// Помощники для форм, где Город / Район / Адрес показываются как один инпут,
// но на бэкенд уходят раздельно.

export function joinAddress({ city, district, address } = {}) {
  return [city, district, address].map((s) => (s ?? '').trim()).filter(Boolean).join(', ');
}

// Эвристика для случая, когда пользователь набил адрес вручную, без Photon.
// 3+ части через запятую → город, район, остальное в адрес.
// 2 части → город, адрес (район пустой).
// 1 часть → только адрес.
export function splitAddress(text) {
  const parts = (text ?? '').split(',').map((s) => s.trim()).filter(Boolean);
  if (parts.length >= 3) {
    return { city: parts[0], district: parts[1], address: parts.slice(2).join(', ') };
  }
  if (parts.length === 2) {
    return { city: parts[0], district: '', address: parts[1] };
  }
  return { city: '', district: '', address: parts[0] ?? '' };
}
