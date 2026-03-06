"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { BarChart3, Zap, Brain, Clock } from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  PieChart,
  Pie,
  Cell,
  LineChart,
  Line,
} from "recharts";

const COLORS = ["#6366f1", "#f59e0b", "#10b981", "#ef4444", "#8b5cf6", "#06b6d4"];

interface ActionStat { action_type: string; total: number; success_count: number; avg_latency: number }
interface DailyStat { date: string; total: number; success_count: number }
interface ProviderStat { ai_provider: string; ai_model: string; total: number; avg_latency: number; total_tokens: number }
interface DailyUsage { date: string; tokens_used: number; messages_sent: number }

export default function AnalyticsPage() {
  const [actionStats, setActionStats] = useState<{ by_type: ActionStat[]; daily: DailyStat[]; total: number; success_rate: number } | null>(null);
  const [aiStats, setAiStats] = useState<{ by_provider: ProviderStat[]; daily_usage: DailyUsage[] } | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const [actionsRes, aiRes] = await Promise.all([
          api.get("/analytics/actions?days=30"),
          api.get("/analytics/ai-performance?days=30"),
        ]);
        setActionStats(actionsRes.data.data);
        setAiStats(aiRes.data.data);
      } catch { /* empty */ } finally { setLoading(false); }
    }
    load();
  }, []);

  if (loading) return <div className="space-y-4">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="h-48 animate-pulse rounded-lg bg-slate-900" />)}</div>;

  const actionTypeLabels: Record<string, string> = {
    create_test: "Criar Teste", renew_client: "Renovar", check_status: "Consultar Status",
    list_packages: "Listar Pacotes", check_balance: "Consultar Saldo", transfer_human: "Transferir Humano",
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-white">Analytics</h1>

      <Tabs defaultValue="actions">
        <TabsList className="bg-slate-800">
          <TabsTrigger value="actions"><Zap className="mr-1.5 h-3.5 w-3.5" />Ações</TabsTrigger>
          <TabsTrigger value="ai"><Brain className="mr-1.5 h-3.5 w-3.5" />Performance IA</TabsTrigger>
        </TabsList>

        <TabsContent value="actions" className="mt-4 space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <Card className="border-slate-800 bg-slate-900">
              <CardContent className="p-5">
                <p className="text-xs text-slate-400">Total (30 dias)</p>
                <p className="mt-1 text-3xl font-bold text-white">{actionStats?.total ?? 0}</p>
              </CardContent>
            </Card>
            <Card className="border-slate-800 bg-slate-900">
              <CardContent className="p-5">
                <p className="text-xs text-slate-400">Taxa de Sucesso</p>
                <p className="mt-1 text-3xl font-bold text-green-400">{actionStats?.success_rate ?? 0}%</p>
              </CardContent>
            </Card>
            <Card className="border-slate-800 bg-slate-900">
              <CardContent className="p-5">
                <p className="text-xs text-slate-400">Tipos Usados</p>
                <p className="mt-1 text-3xl font-bold text-indigo-400">{actionStats?.by_type?.length ?? 0}</p>
              </CardContent>
            </Card>
          </div>

          {(actionStats?.by_type?.length ?? 0) > 0 && (
            <div className="grid gap-4 lg:grid-cols-2">
              <Card className="border-slate-800 bg-slate-900">
                <CardHeader><CardTitle className="text-sm text-white">Por Tipo de Ação</CardTitle></CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={250}>
                    <PieChart>
                      <Pie data={actionStats!.by_type.map((a) => ({ name: actionTypeLabels[a.action_type] || a.action_type, value: a.total }))} cx="50%" cy="50%" outerRadius={80} dataKey="value" label={({ name, percent }: { name: string; percent?: number }) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`}>
                        {actionStats!.by_type.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                      </Pie>
                      <Tooltip contentStyle={{ backgroundColor: "#1e293b", border: "1px solid #334155", borderRadius: "8px", color: "#fff" }} />
                    </PieChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>

              <Card className="border-slate-800 bg-slate-900">
                <CardHeader><CardTitle className="text-sm text-white">Detalhes por Ação</CardTitle></CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {actionStats!.by_type.map((a, i) => (
                      <div key={a.action_type} className="flex items-center justify-between rounded-lg bg-slate-800/50 p-3">
                        <div className="flex items-center gap-3">
                          <div className="h-3 w-3 rounded-full" style={{ backgroundColor: COLORS[i % COLORS.length] }} />
                          <span className="text-sm text-white">{actionTypeLabels[a.action_type] || a.action_type}</span>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-slate-400">
                          <span>{a.total}x</span>
                          <Badge className="bg-green-600/20 text-green-400">{a.success_count}/{a.total}</Badge>
                          <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{Math.round(a.avg_latency)}ms</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </div>
          )}
        </TabsContent>

        <TabsContent value="ai" className="mt-4 space-y-4">
          {(aiStats?.by_provider?.length ?? 0) > 0 && (
            <div className="space-y-3">
              {aiStats!.by_provider.map((p) => (
                <Card key={`${p.ai_provider}-${p.ai_model}`} className="border-slate-800 bg-slate-900">
                  <CardContent className="flex items-center justify-between p-4">
                    <div>
                      <p className="font-medium text-white">{p.ai_provider} / {p.ai_model}</p>
                      <p className="text-xs text-slate-500">{p.total} chamadas</p>
                    </div>
                    <div className="flex gap-4 text-xs text-slate-400">
                      <span>Latência: <strong className="text-white">{Math.round(p.avg_latency)}ms</strong></span>
                      <span>Tokens: <strong className="text-white">{(p.total_tokens ?? 0).toLocaleString("pt-BR")}</strong></span>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {(aiStats?.daily_usage?.length ?? 0) > 0 && (
            <Card className="border-slate-800 bg-slate-900">
              <CardHeader><CardTitle className="text-sm text-white">Tokens Usados (30 dias)</CardTitle></CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={250}>
                  <LineChart data={aiStats!.daily_usage.map((d) => ({ date: d.date.slice(5), tokens: d.tokens_used, msgs: d.messages_sent }))}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                    <XAxis dataKey="date" stroke="#94a3b8" fontSize={11} />
                    <YAxis stroke="#94a3b8" fontSize={11} />
                    <Tooltip contentStyle={{ backgroundColor: "#1e293b", border: "1px solid #334155", borderRadius: "8px", color: "#fff" }} />
                    <Line type="monotone" dataKey="tokens" stroke="#8b5cf6" name="Tokens" dot={false} />
                    <Line type="monotone" dataKey="msgs" stroke="#6366f1" name="Mensagens" dot={false} />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          )}

          {(aiStats?.by_provider?.length ?? 0) === 0 && (
            <Card className="border-slate-800 bg-slate-900">
              <CardContent className="flex flex-col items-center py-12">
                <Brain className="mb-4 h-12 w-12 text-slate-600" />
                <p className="text-slate-400">Nenhum dado de performance ainda.</p>
              </CardContent>
            </Card>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
