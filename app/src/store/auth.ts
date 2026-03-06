import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../services/api';
import { NotificationService } from '../services/notification';
import { User } from '../types';

const API_URL = 'https://api.aireply.xpainel.online/api';

const syncTokenToNative = async (token: string) => {
  if (NotificationService.isAvailable()) {
    await NotificationService.setAuthToken(token);
    await NotificationService.setApiUrl(API_URL);
  }
};

interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
  setAuth: (user: User, token: string) => void;
  logout: () => Promise<void>;
  loadFromStorage: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  fetchUser: () => Promise<void>;
}

export const useAuth = create<AuthState>((set, get) => ({
  user: null,
  token: null,
  loading: true,

  setAuth: async (user, token) => {
    await AsyncStorage.setItem('auth_token', token);
    await AsyncStorage.setItem('user', JSON.stringify(user));
    await syncTokenToNative(token);
    set({ user, token, loading: false });
  },

  logout: async () => {
    try { await api.post('/auth/logout'); } catch {}
    await AsyncStorage.multiRemove(['auth_token', 'user']);
    set({ user: null, token: null, loading: false });
  },

  loadFromStorage: async () => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      const userStr = await AsyncStorage.getItem('user');
      if (token && userStr) {
        await syncTokenToNative(token);
        set({ token, user: JSON.parse(userStr), loading: false });
        get().fetchUser();
      } else {
        set({ loading: false });
      }
    } catch {
      set({ loading: false });
    }
  },

  login: async (email, password) => {
    const { data } = await api.post('/auth/login', { email, password });
    get().setAuth(data.user, data.token);
  },

  fetchUser: async () => {
    try {
      const { data } = await api.get('/auth/user');
      await AsyncStorage.setItem('user', JSON.stringify(data));
      set({ user: data });
    } catch {}
  },
}));
