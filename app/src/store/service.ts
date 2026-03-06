import { create } from 'zustand';
import { NotificationService } from '../services/notification';
import { LogEntry, ServiceStatus } from '../types';

interface ServiceState {
  status: ServiceStatus;
  logs: LogEntry[];
  refreshStatus: () => Promise<void>;
  toggleService: () => Promise<void>;
  addLog: (log: LogEntry) => void;
  clearLogs: () => void;
}

export const useService = create<ServiceState>((set, get) => ({
  status: {
    isRunning: false,
    hasPermission: false,
    isBatteryOptimized: false,
    lastActivity: null,
  },
  logs: [],

  refreshStatus: async () => {
    const hasPermission = await NotificationService.hasPermission();
    const isRunning = await NotificationService.isServiceRunning();
    set((s) => ({ status: { ...s.status, isRunning, hasPermission } }));
  },

  toggleService: async () => {
    const { status } = get();
    if (status.isRunning) {
      await NotificationService.stopService();
    } else {
      await NotificationService.startService();
    }
    await get().refreshStatus();
  },

  addLog: (log) => {
    set((s) => ({ logs: [log, ...s.logs].slice(0, 200) }));
  },

  clearLogs: () => set({ logs: [] }),
}));
