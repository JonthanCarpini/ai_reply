import { create } from 'zustand';
import api from '@/lib/api';
import type { User } from '@/lib/types';

interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
  setAuth: (user: User, token: string) => void;
  logout: () => void;
  fetchUser: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, phone: string, password: string, password_confirmation: string) => Promise<void>;
}

export const useAuth = create<AuthState>((set) => ({
  user: null,
  token: typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null,
  loading: true,

  setAuth: (user, token) => {
    localStorage.setItem('auth_token', token);
    set({ user, token, loading: false });
  },

  logout: () => {
    api.post('/auth/logout').catch(() => {});
    localStorage.removeItem('auth_token');
    set({ user: null, token: null, loading: false });
  },

  fetchUser: async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      set({ loading: false });
      return;
    }
    try {
      const res = await api.get('/auth/me');
      set({ user: res.data.user, token, loading: false });
    } catch {
      localStorage.removeItem('auth_token');
      set({ user: null, token: null, loading: false });
    }
  },

  login: async (email, password) => {
    const res = await api.post('/auth/login', { email, password });
    const { user, token } = res.data;
    localStorage.setItem('auth_token', token);
    set({ user, token, loading: false });
  },

  register: async (name, email, phone, password, password_confirmation) => {
    const res = await api.post('/auth/register', { name, email, phone, password, password_confirmation });
    const { user, token } = res.data;
    localStorage.setItem('auth_token', token);
    set({ user, token, loading: false });
  },
}));
