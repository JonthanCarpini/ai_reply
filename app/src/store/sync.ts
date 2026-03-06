import { create } from 'zustand';
import api from '../services/api';
import { SyncData } from '../types';

interface SyncState {
  data: SyncData | null;
  loading: boolean;
  lastSync: string | null;
  pull: () => Promise<SyncData | null>;
  push: (payload: Record<string, any>) => Promise<void>;
}

export const useSync = create<SyncState>((set, get) => ({
  data: null,
  loading: false,
  lastSync: null,

  pull: async () => {
    set({ loading: true });
    try {
      const { data } = await api.get('/sync/pull');
      const syncData = data.data as SyncData;
      set({ data: syncData, lastSync: syncData.synced_at, loading: false });
      return syncData;
    } catch {
      set({ loading: false });
      return null;
    }
  },

  push: async (payload) => {
    set({ loading: true });
    try {
      const { data } = await api.post('/sync/push', payload);
      set({ lastSync: data.synced_at, loading: false });
      await get().pull();
    } catch {
      set({ loading: false });
      throw new Error('Erro ao sincronizar configurações');
    }
  },
}));
