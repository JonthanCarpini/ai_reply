export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  is_admin: boolean;
  subscription?: Subscription;
}

export interface AdminUser extends User {
  messages_this_month?: number;
  created_at?: string;
}

export interface AdminStats {
  total_users: number;
  active_users: number;
  admins: number;
  messages_this_month: number;
  messages_today: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface Plan {
  id: number;
  name: string;
  slug: string;
  price: number;
  messages_limit: number;
  whatsapp_limit: number;
  actions_limit: number;
  ai_generation_limit: number;
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
  default_test_package_id: number | null;
}

export interface PanelPackage {
  id: number;
  name: string;
  official_credits: number;
  trial_credits: number;
  official_duration: number | null;
  official_duration_in: string | null;
  trial_duration: number | null;
  trial_duration_in: string | null;
  max_connections: number;
  is_trial?: boolean;
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

export interface StructuredPromptConfig {
  identity?: string;
  tone?: string;
  permanent_rules?: string;
  automatic_triggers?: string;
  phase_flow?: string;
  response_policy?: string;
}

export interface ReplyPolicyConfig {
  max_chars?: number;
  max_tool_steps?: number;
  enforce_short_reply?: boolean;
  blocked_terms?: string[];
}

export interface Prompt {
  id: number;
  name: string;
  system_prompt: string;
  structured_prompt: StructuredPromptConfig | null;
  reply_policy: ReplyPolicyConfig | null;
  greeting_message: string | null;
  fallback_message: string | null;
  offline_message: string | null;
  custom_variables: Record<string, string> | null;
  is_active: boolean;
  version: number;
}

export interface ActionPreconditions {
  required_params?: string[];
  required_collected_data?: string[];
  blocked_journey_stages?: string[];
}

export interface Action {
  id: number;
  action_type: string;
  label: string;
  enabled: boolean;
  params: Record<string, unknown> | null;
  custom_instructions: string | null;
  preconditions: ActionPreconditions | null;
  phase_scope: string[] | null;
  max_tool_steps: number;
  daily_limit: number;
  daily_count: number;
}

export interface Rule {
  id: number;
  type: 'schedule' | 'blacklist' | 'whitelist' | 'keyword' | 'rate_limit';
  config: Record<string, unknown>;
  enabled: boolean;
}

export interface ConversationJourneySnapshot {
  journey_stage: string;
  journey_status: string;
  collected_data: Record<string, unknown>;
  pending_requirements: string[];
  last_tool_name: string | null;
  last_tool_status: string | null;
  human_handoff_requested: boolean;
  customer_flags: Record<string, unknown>;
  context?: Record<string, unknown>;
}

export interface Conversation {
  id: number;
  contact_phone: string;
  contact_name: string | null;
  whatsapp_number: string | null;
  status: 'active' | 'archived' | 'blocked';
  journey_stage: string;
  journey_status: string;
  collected_data: Record<string, unknown> | null;
  pending_requirements: string[] | null;
  last_tool_name: string | null;
  last_tool_status: string | null;
  human_handoff_requested: boolean;
  customer_flags: Record<string, unknown> | null;
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
  context_data: ConversationJourneySnapshot | null;
  correlation_id: string | null;
  source_metadata: Record<string, unknown> | null;
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

export interface DeviceApp {
  id: number;
  user_id: number;
  device_type: string;
  app_name: string;
  app_code: string | null;
  app_url: string | null;
  ntdown: string | null;
  downloader: string | null;
  download_instructions: string | null;
  setup_instructions: string | null;
  agent_instructions: string | null;
  is_active: boolean;
  priority: number;
  created_at: string;
  updated_at: string;
}

export interface DeviceTypeInfo {
  [key: string]: string;
}
