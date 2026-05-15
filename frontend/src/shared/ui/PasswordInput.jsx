// src/shared/ui/PasswordInput.jsx
import { useState } from 'react';
import { IconEye, IconEyeOff } from './Icons.jsx';

export function PasswordInput({
  value,
  onChange,
  required = false,
  minLength,
  autoFocus = false,
  autoComplete = 'current-password',
  disabled = false,
  placeholder,
  defaultVisible = false,
  className = '',
  inputProps = {},
}) {
  const [visible, setVisible] = useState(defaultVisible);

  return (
    <div className={`password-field ${className}`.trim()}>
      <input
        className="input password-field__input"
        type={visible ? 'text' : 'password'}
        value={value}
        onChange={onChange}
        required={required}
        minLength={minLength}
        autoFocus={autoFocus}
        autoComplete={autoComplete}
        disabled={disabled}
        placeholder={placeholder}
        {...inputProps}
      />
      <button
        type="button"
        className="password-field__toggle"
        onClick={() => setVisible((v) => !v)}
        aria-label={visible ? 'Скрыть пароль' : 'Показать пароль'}
        tabIndex={-1}
        disabled={disabled}
      >
        {visible ? <IconEyeOff size={18} /> : <IconEye size={18} />}
      </button>
    </div>
  );
}
