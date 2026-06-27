import axios from 'axios';

export const api = axios.create({
  headers: { Accept: 'application/json' },
  withCredentials: true,
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !window.location.pathname.startsWith('/login') && !window.location.pathname.startsWith('/forgot-password') && !window.location.pathname.startsWith('/reset-password')) {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  },
);

export function unwrap<T = any>(response: { data: T }) {
  return response.data;
}
