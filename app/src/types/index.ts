export interface User {
  id: number;
  name: string;
  email: string;
  phone: string;
  subscription: Subscription | null;
}

export interface Subscription {
  id: number;
  plan: Plan;
  status: string;
  ends_at: string | null;
}

export interface Plan {
  id: number;
  name: string;
  slug: string;
  price: number;
  message_limit: number;
  features: Record<string, any>;
}

export interface DashboardStats {
  conversations_today: number;
  messages_today: number;
  actions_today: number;
  ai_tokens_today: number;
}

export interface Conversation {
  id: number;
  contact_name: string;
  contact_phone: string;
  status: string;
  messages_count: number;
  actions_count: number;
  last_message_at: string;
}

export interface Message {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  tokens_used: number;
  created_at: string;
}

export interface LogEntry {
  id: string;
  timestamp: string;
  type: 'message_received' | 'ai_response' | 'action_executed' | 'error';
  contactName?: string;
  contactPhone?: string;
  message?: string;
  response?: string;
  actionType?: string;
  error?: string;
}

export interface ServiceStatus {
  isRunning: boolean;
  hasPermission: boolean;
  isBatteryOptimized: boolean;
  lastActivity: string | null;
}

export type RootStackParamList = {
  Splash: undefined;
  Login: undefined;
  Main: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Conversations: undefined;
  Logs: undefined;
  Settings: undefined;
};

export type SettingsStackParamList = {
  SettingsMain: undefined;
  PanelConfig: undefined;
  AIConfig: undefined;
  Prompts: undefined;
  Actions: undefined;
  Rules: undefined;
  Plan: undefined;
};
