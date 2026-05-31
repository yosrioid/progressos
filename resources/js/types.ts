export type Page<T = {}> = T & {
  auth: { user: { id: number; name: string; email: string; timezone: string; theme: string; avatar_path?: string } | null };
  flash: { success?: string; status?: string };
};

export type Paginator<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  from: number | null;
  to: number | null;
  total: number;
};

export type Tag = { id: number; name: string };
