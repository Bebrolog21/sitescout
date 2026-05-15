// src/shared/ui/Icons.jsx
// Набор inline-SVG иконок в едином стиле (stroke-based, lucide-like).
// Все принимают size (по умолчанию 18) и className.

const baseProps = {
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.8,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
};

function Svg({ size = 18, className, children }) {
  return (
    <svg
      viewBox="0 0 24 24"
      width={size}
      height={size}
      className={className}
      aria-hidden="true"
      focusable="false"
      {...baseProps}
    >
      {children}
    </svg>
  );
}

export function IconDashboard(props) {
  return (
    <Svg {...props}>
      <rect x="3"  y="3"  width="7" height="7" rx="1.2" />
      <rect x="14" y="3"  width="7" height="7" rx="1.2" />
      <rect x="3"  y="14" width="7" height="7" rx="1.2" />
      <rect x="14" y="14" width="7" height="7" rx="1.2" />
    </Svg>
  );
}

export function IconSites(props) {
  return (
    <Svg {...props}>
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
      <circle cx="12" cy="10" r="3" />
    </Svg>
  );
}

export function IconPassport(props) {
  return (
    <Svg {...props}>
      <rect x="8" y="3" width="8" height="4" rx="1" />
      <path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2" />
      <path d="M9 12h6" />
      <path d="M9 16h6" />
    </Svg>
  );
}

export function IconUsers(props) {
  return (
    <Svg {...props}>
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </Svg>
  );
}

export function IconLogout(props) {
  return (
    <Svg {...props}>
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </Svg>
  );
}

export function IconChevronLeft(props) {
  return (
    <Svg {...props}>
      <polyline points="15 18 9 12 15 6" />
    </Svg>
  );
}

export function IconChevronRight(props) {
  return (
    <Svg {...props}>
      <polyline points="9 18 15 12 9 6" />
    </Svg>
  );
}

/**
 * Маленький логотип-аватар. Не stroke-based, заполненный — играет роль брендмарка.
 */
export function IconLogoMark({ size = 28, className }) {
  return (
    <svg
      viewBox="0 0 32 32"
      width={size}
      height={size}
      className={className}
      aria-hidden="true"
      focusable="false"
    >
      <defs>
        <linearGradient id="ssLogoGrad" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#6b9aff" />
          <stop offset="100%" stopColor="#3b62d1" />
        </linearGradient>
      </defs>
      <rect x="0" y="0" width="32" height="32" rx="8" fill="url(#ssLogoGrad)" />
      <path
        d="M11 11.5c0-1.5 1.4-2.5 3.2-2.5h3.4c1.6 0 2.7.7 3.2 1.7M21 20.5c0 1.5-1.4 2.5-3.2 2.5h-3.6c-1.6 0-2.8-.8-3.2-1.7M11.5 11.5c0 1.4 1 2.2 3 2.6l3 .5c2 .4 3 1.2 3 2.6 0 1.5-1.3 2.7-3.2 2.7"
        fill="none"
        stroke="white"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function IconEye(props) {
  return (
    <Svg {...props}>
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
      <circle cx="12" cy="12" r="3" />
    </Svg>
  );
}

export function IconEyeOff(props) {
  return (
    <Svg {...props}>
      <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.45 18.45 0 0 1 4.22-5.31" />
      <path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19" />
      <path d="M9.88 9.88a3 3 0 0 0 4.24 4.24" />
      <path d="M3 3l18 18" />
    </Svg>
  );
}

export function IconRefresh(props) {
  return (
    <Svg {...props}>
      <path d="M21 12a9 9 0 1 1-3.51-7.13" />
      <path d="M21 4v5h-5" />
    </Svg>
  );
}
