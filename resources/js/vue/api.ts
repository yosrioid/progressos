import axios from 'axios';

export const api = axios.create({
  headers: { Accept: 'application/json' },
  withCredentials: true,
});

export function unwrap<T = any>(response: { data: T }) {
  return response.data;
}
