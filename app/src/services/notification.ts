import { NativeModules, NativeEventEmitter, Platform } from 'react-native';

const { NotificationBridge } = NativeModules;

const emitter = Platform.OS === 'android' && NotificationBridge
  ? new NativeEventEmitter(NotificationBridge)
  : null;

export const NotificationService = {
  isAvailable: () => Platform.OS === 'android' && !!NotificationBridge,

  hasPermission: async (): Promise<boolean> => {
    if (!NotificationBridge) return false;
    return NotificationBridge.hasNotificationAccess();
  },

  openPermissionSettings: () => {
    NotificationBridge?.openNotificationAccessSettings();
  },

  startService: async (): Promise<boolean> => {
    if (!NotificationBridge) return false;
    return NotificationBridge.startListenerService();
  },

  stopService: async (): Promise<boolean> => {
    if (!NotificationBridge) return false;
    return NotificationBridge.stopListenerService();
  },

  isServiceRunning: async (): Promise<boolean> => {
    if (!NotificationBridge) return false;
    return NotificationBridge.isServiceRunning();
  },

  setAuthToken: async (token: string): Promise<void> => {
    NotificationBridge?.setAuthToken(token);
  },

  setApiUrl: async (url: string): Promise<void> => {
    NotificationBridge?.setApiUrl(url);
  },

  setWhatsAppPackages: async (packages: string[]): Promise<void> => {
    NotificationBridge?.setWhatsAppPackages(packages);
  },

  getWhatsAppPackages: async (): Promise<string[]> => {
    if (!NotificationBridge) return ['com.whatsapp', 'com.whatsapp.w4b'];
    const arr = await NotificationBridge.getWhatsAppPackages();
    return arr ? Array.from(arr) : ['com.whatsapp', 'com.whatsapp.w4b'];
  },

  requestBatteryOptimization: () => {
    NotificationBridge?.requestIgnoreBatteryOptimization();
  },

  openAppSettings: () => {
    NotificationBridge?.openAppSettings();
  },

  needsRestrictedSettings: async (): Promise<boolean> => {
    if (!NotificationBridge) return false;
    return NotificationBridge.needsRestrictedSettingsPermission();
  },

  onMessageProcessed: (callback: (data: any) => void) => {
    if (!emitter) return { remove: () => {} };
    return emitter.addListener('onMessageProcessed', callback);
  },

  onServiceStatusChange: (callback: (data: { running: boolean }) => void) => {
    if (!emitter) return { remove: () => {} };
    return emitter.addListener('onServiceStatusChange', callback);
  },

  onError: (callback: (data: { error: string }) => void) => {
    if (!emitter) return { remove: () => {} };
    return emitter.addListener('onError', callback);
  },
};
