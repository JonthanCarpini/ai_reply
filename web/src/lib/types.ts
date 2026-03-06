export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  subscription?: Subscription;
}

export interface Plan {
  id: number;
  name: string;
  slug: string;
  price: number;
  messages_limit: number;
  whatsapp_limit: number;
  actions_limit: number;
  analytics_enabled: boolean;
  priority_support: boolean;
}

export interface Subscription {
  id: number;
  plan_id: number;
  status: string;
  trial_ends_at: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  plan?: Plan;
}

export interface PanelConfig {
  id: number;
  panel_name: string;
  panel_url: string;
  is_active: boolean;
  status: 'connected' | 'error' | 'untested';
  last_verified_at: string | null;
  has_api_key: boolean;
}

export interface AiConfig {
  id: number;
  provider: 'openai' | 'anthropic' | 'google';
  model: string;
  temperature: number;
  max_tokens: number;
  is_active: boolean;
  has_api_key: boolean;
}

export interface Prompt {
  id: number;
  name: string;
  system_prompt: string;
  greeting_message: string | null;
  fallback_message: string | null;
  offline_message: string | null;
  custom_variables: Record<string, string> | null;
  is_active: boolean;
  version: number;
}

export interface Action {
  id: number;
  action_type: string;
  label: string;
  enabled: boolean;
  params: Record<string, unknown> | null;
  custom_instructions: string | null;
  daily_limit: number;
  daily_count: number;
}

export interface Rule {
  id: number;
  type: 'schedule' | 'blacklist' | 'whitelist' | 'keyword' | 'rate_limit';
  config: Record<string, unknown>;
  enabled: boolean;
}

export interface Conversation {
  id: number;
  contact_phone: string;
  contact_name: string | null;
  whatsapp_number: string | null;
  status: 'active' | 'archived' | 'blocked';
  message_count: number;
  actions_executed: number;
  last_message_at: string | null;
}

export interface Message {
  id: number;
  conversation_id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  action_type: string | null;
  action_result: Record<string, unknown> | null;
  action_success: boolean | null;
  ai_provider: string | null;
  ai_model: string | null;
  tokens_input: number;
  tokens_output: number;
  latency_ms: number;
  created_at: string;
}

export interface DashboardStats {
  today: {
    messages_received: number;
    messages_sent: number;
    actions_executed: number;
    tokens_used: number;
    tests_created: number;
    renewals_done: number;
    errors_count: number;
  };
  month: {
    messages_sent: number;
    messages_limit: number;
    usage_percent: number;
  };
  subscription: {
    plan_name: string;
    status: string;
    expires_at: string | null;
  } | null;
  conversations_active: number;
}

export interface ChartData {
  labels: string[];
  messages: number[];
  actions: number[];
  tokens: number[];
}
