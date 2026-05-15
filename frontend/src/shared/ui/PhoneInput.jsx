// src/shared/ui/PhoneInput.jsx
// Простой input с маской российского телефона: +7 (XXX) XXX-XX-XX.
// На бэкенд уходит уже отформатированная строка (поле строковое, без ограничения формата).

import { useMemo } from 'react';

export function PhoneInput({ value, onChange, ...rest }) {
  const display = useMemo(() => formatPhone(value), [value]);

  const handleChange = (e) => {
    const formatted = formatPhone(e.target.value);
    onChange?.(formatted);
  };

  return (
    <input
      type="tel"
      inputMode="tel"
      autoComplete="tel"
      className="input"
      value={display}
      onChange={handleChange}
      placeholder="+7 (___) ___-__-__"
      maxLength={18}
      {...rest}
    />
  );
}

// Нормализует ввод к российскому формату.
// Принимает что угодно (вставка из буфера, цифры с пробелами, +7 / 8 / без префикса) и
// возвращает строку "+7 (XXX) XXX-XX-XX" с подстановкой по мере набора.
export function formatPhone(input) {
  if (!input) return '';
  let digits = String(input).replace(/\D/g, '');
  if (!digits) return '';

  // Нормализуем код страны: 8XXXXXXXXXX → 7XXXXXXXXXX; без префикса → добавим 7.
  if (digits[0] === '8') digits = '7' + digits.slice(1);
  if (digits[0] !== '7') digits = '7' + digits;

  digits = digits.slice(0, 11); // +7 + 10 цифр

  const rest = digits.slice(1); // 10 цифр после кода страны
  let out = '+7';
  if (rest.length === 0) return out;
  out += ' (' + rest.slice(0, 3);
  if (rest.length < 3) return out;
  out += ')';
  if (rest.length > 3) out += ' ' + rest.slice(3, 6);
  if (rest.length > 6) out += '-' + rest.slice(6, 8);
  if (rest.length > 8) out += '-' + rest.slice(8, 10);
  return out;
}
