"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { DashboardStats, ChartData } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  MessageSquare,
  Zap,
  TestTube,
  RefreshCw,
  AlertTriangle,
  Users,
  TrendingUp,
  Coins,
} from "lucide-react";
import {
  ResponsiveContainer,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from "recharts";

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [chart, setChart] = useState<ChartData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const [statsRes, chartRes] = await Promise.all([
          api.get("/dashboard/stats"),
          api.get("/dashboard/charts?days=7"),
        ]);
        setStats(statsRes.data.data);
        setChart(chartRes.data.data);
      } catch {
        // Silently fail — empty state
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  const statCards = [
    {
      title: "Mensagens Hoje",
      value: stats?.today.messages_sent ?? 0,
      icon: MessageSquare,
      color: "text-blue-400",
      bg: "bg-blue-500/10",
    },
    {
      title: "Ações Executadas",
      value: stats?.today.actions_executed ?? 0,
      icon: Zap,
      color: "text-amber-400",
      bg: "bg-amber-500/10",
    },
    {
      title: "Testes Criados",
      value: stats?.today.tests_created ?? 0,
      icon: TestTube,
      color: "text-green-400",
      bg: "bg-green-500/10",
    },
    {
      title: "Renovações",
      value: stats?.today.renewals_done ?? 0,
      icon: RefreshCw,
      color: "text-purple-400",
      bg: "bg-purple-500/10",
    },
    {
      title: "Erros",
      value: stats?.today.errors_count ?? 0,
      icon: AlertTriangle,
      color: "text-red-400",
      bg: "bg-red-500/10",
    },
    {
      title: "Conversas Ativas",
      value: stats?.conversations_active ?? 0,
      icon: Users,
      color: "text-cyan-400",
      bg: "bg-cyan-500/10",
    },
    {
      title: "Tokens Usados",
      value: stats?.today.tokens_used ?? 0,
      icon: Coins,
      color: "text-orange-400",
      bg: "bg-orange-500/10",
    },
    {
      title: "Uso Mensal",
      value: `${stats?.month.usage_percent ?? 0}%`,
      icon: TrendingUp,
      color: "text-indigo-400",
      bg: "bg-indigo-500/10",
      subtitle: `${stats?.month.messages_sent ?? 0} / ${stats?.month.messages_limit === 0 ? "∞" : stats?.month.messages_limit ?? 0}`,
    },
  ];

  const chartData = chart
    ? chart.labels.map((label, i) => ({
        name: label,
        mensagens: chart.messages[i],
        acoes: chart.actions[i],
      }))
    : [];

  if (loading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-white">Dashboard</h1>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <Card key={i} className="animate-pulse border-slate-800 bg-slate-900">
              <CardContent className="p-6">
                <div className="h-16" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Dashboard</h1>
        {stats?.subscription && (
          <Badge
            variant={stats.subscription.status === "active" ? "default" : "secondary"}
            className={stats.subscription.status === "active" ? "bg-green-600" : "bg-amber-600"}
          >
            {stats.subscription.plan_name} — {stats.subscription.status === "active" ? "Ativo" : "Trial"}
          </Badge>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {statCards.map((card) => (
          <Card key={card.title} className="border-slate-800 bg-slate-900">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs font-medium text-slate-400">{card.title}</p>
                  <p className="mt-1 text-2xl font-bold text-white">{card.value}</p>
                  {card.subtitle && (
                    <p className="mt-0.5 text-xs text-slate-500">{card.subtitle}</p>
                  )}
                </div>
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${card.bg}`}>
                  <card.icon className={`h-5 w-5 ${card.color}`} />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {chartData.length > 0 && (
        <Card className="border-slate-800 bg-slate-900">
          <CardHeader>
            <CardTitle className="text-lg text-white">Atividade — Últimos 7 dias</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorMsgs" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="colorAcoes" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#f59e0b" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#f59e0b" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                <XAxis dataKey="name" stroke="#94a3b8" fontSize={12} />
                <YAxis stroke="#94a3b8" fontSize={12} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#1e293b",
                    border: "1px solid #334155",
                    borderRadius: "8px",
                    color: "#fff",
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="mensagens"
                  stroke="#6366f1"
                  fillOpacity={1}
                  fill="url(#colorMsgs)"
                  name="Mensagens"
                />
                <Area
                  type="monotone"
                  dataKey="acoes"
                  stroke="#f59e0b"
                  fillOpacity={1}
                  fill="url(#colorAcoes)"
                  name="Ações"
                />
              </AreaChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
