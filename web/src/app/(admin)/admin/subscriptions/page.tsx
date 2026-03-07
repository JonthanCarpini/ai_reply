"use client";

import { useEffect, useState, useCallback } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { toast } from "sonner";
import api from "@/lib/api";
import type { Subscription, PaginatedResponse } from "@/lib/types";

interface FullSubscription extends Subscription {
  user?: { id: number; name: string; email: string };
}

export default function AdminSubscriptionsPage() {
  const [subs, setSubs] = useState<FullSubscription[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [statusFilter, setStatusFilter] = useState("all");
  const [loading, setLoading] = useState(true);

  const fetchSubs = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page };
      if (statusFilter !== "all") params.status = statusFilter;
      const res = await api.get<PaginatedResponse<FullSubscription>>("/admin/subscriptions", { params });
      setSubs(res.data.data);
      setLastPage(res.data.last_page);
      setTotal(res.data.total);
    } catch {
      toast.error("Erro ao carregar assinaturas");
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter]);

  useEffect(() => {
    fetchSubs();
  }, [fetchSubs]);

  const deleteSub = async (id: number) => {
    if (!confirm("Tem certeza que deseja excluir esta assinatura?")) return;
    try {
      await api.delete(`/admin/subscriptions/${id}`);
      toast.success("Assinatura excluída");
      fetchSubs();
    } catch {
      toast.error("Erro ao excluir");
    }
  };

  const updateStatus = async (id: number, status: string) => {
    try {
      await api.put(`/admin/subscriptions/${id}`, { status });
      toast.success("Status atualizado");
      fetchSubs();
    } catch {
      toast.error("Erro ao atualizar");
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case "active": return "bg-green-500/10 text-green-400";
      case "trial": return "bg-blue-500/10 text-blue-400";
      case "canceled": return "bg-orange-500/10 text-orange-400";
      case "expired": return "bg-red-500/10 text-red-400";
      default: return "bg-slate-500/10 text-slate-400";
    }
  };

  const formatDate = (d: string | null) => {
    if (!d) return "Sem expiração";
    return new Date(d).toLocaleDateString("pt-BR");
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Assinaturas ({total})</h1>
        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
          <SelectTrigger className="w-40 border-slate-700 bg-slate-900">
            <SelectValue placeholder="Filtrar" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem value="active">Ativo</SelectItem>
            <SelectItem value="trial">Trial</SelectItem>
            <SelectItem value="canceled">Cancelado</SelectItem>
            <SelectItem value="expired">Expirado</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Card className="border-slate-800 bg-slate-900">
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Usuário</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Plano</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Status</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Expira em</th>
                  <th className="px-4 py-3 text-right text-xs font-medium uppercase text-slate-400">Ações</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Carregando...</td></tr>
                ) : subs.length === 0 ? (
                  <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Nenhuma assinatura encontrada</td></tr>
                ) : (
                  subs.map((sub) => (
                    <tr key={sub.id} className="border-b border-slate-800/50 hover:bg-slate-800/30">
                      <td className="px-4 py-3">
                        <div>
                          <p className="text-sm font-medium text-white">{sub.user?.name}</p>
                          <p className="text-xs text-slate-400">{sub.user?.email}</p>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-sm text-slate-300">{sub.plan?.name || "—"}</td>
                      <td className="px-4 py-3">
                        <Select value={sub.status} onValueChange={(v) => updateStatus(sub.id, v)}>
                          <SelectTrigger className="h-7 w-28 border-slate-700 bg-transparent text-xs">
                            <Badge className={statusColor(sub.status)}>{sub.status}</Badge>
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="active">Ativo</SelectItem>
                            <SelectItem value="trial">Trial</SelectItem>
                            <SelectItem value="canceled">Cancelado</SelectItem>
                            <SelectItem value="expired">Expirado</SelectItem>
                          </SelectContent>
                        </Select>
                      </td>
                      <td className="px-4 py-3 text-sm text-slate-300">{formatDate(sub.current_period_end)}</td>
                      <td className="px-4 py-3 text-right">
                        <Button size="icon" variant="ghost" onClick={() => deleteSub(sub.id)}>
                          <Trash2 className="h-4 w-4 text-red-400" />
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm text-slate-400">Página {page} de {lastPage}</span>
          <Button size="sm" variant="outline" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      )}
    </div>
  );
}
