export function inputDate(value?: string | null) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

export function formatDate(value?: string | null) {
  const raw = inputDate(value);
  if (!raw) return '-';
  const parsed = new Date(`${raw}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return raw;

  return new Intl.DateTimeFormat('en', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsed);
}

export function compactDate(value?: string | null) {
  const raw = inputDate(value);
  if (!raw) return '';
  const parsed = new Date(`${raw}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return raw;

  return new Intl.DateTimeFormat('en', {
    day: '2-digit',
    month: 'short',
  }).format(parsed);
}

export function minutes(value?: number | string | null) {
  const total = Number(value || 0);
  if (total < 60) return `${total}m`;
  const hours = Math.floor(total / 60);
  const rest = total % 60;
  return rest ? `${hours}h ${rest}m` : `${hours}h`;
}
