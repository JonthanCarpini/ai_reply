"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Action } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Zap, TestTube, RefreshCw, Search, ListOrdered, Wallet, UserRoundX, Loader2 } from "lucide-react";
import { toast } from "sonner";

const actionIcons: Record<string, React.ElementType> = {
  create_test: TestTube,
  renew_client: RefreshCw,
  check_status: Search,
  list_packages: ListOrdered,
  check_balance: Wallet,
  transfer_human: UserRoundX,
};

const actionDescriptions: Record<string, string> = {
  create_test: "Cria um teste/demonstração IPTV quando o cliente solicitar.",
  renew_client: "Renova a assinatura de um cliente após confirmação de pagamento.",
  check_status: "Consulta vencimento e status da conta do cliente.",
  list_packages: "Lista pacotes disponíveis com preços.",
  check_balance: "Consulta o saldo de créditos do revendedor.",
  transfer_human: "Transfere para atendimento humano quando a IA não resolve.",
};

export default function ActionsPage() {
  const [actions, setActions] = useState<Action[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<number | null>(null);

  useEffect(() => { loadActions(); }, []);

  async function loadActions() {
    try {
      const res = await api.get("/actions");
      setActions(res.data.data);
    } catch { /* empty */ } finally { setLoading(false); }
  }

  async function toggleAction(action: Action) {
    setSavingId(action.id);
    try {
      await api.put(`/actions/${action.id}`, { enabled: !action.enabled });
      toast.success(`${action.label} ${!action.enabled ? "ativada" : "desativada"}.`);
      loadActions();
    } catch { toast.error("Erro ao atualizar."); } finally { setSavingId(null); }
  }

  async function updateAction(id: number, data: Partial<Action>) {
    setSavingId(id);
    try {
      await api.put(`/actions/${id}`, data);
      toast.success("Ação atualizada!");
      loadActions();
    } catch { toast.error("Erro ao atualizar."); } finally { setSavingId(null); }
  }

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Ações da IA</h1>
        <Badge variant="secondary" className="text-xs">
          {actions.filter((a) => a.enabled).length} / {actions.length} ativas
        </Badge>
      </div>

      <p className="text-sm text-slate-400">
        Configure quais ações a IA pode executar automaticamente no seu painel IPTV.
      </p>

      <div className="space-y-4">
        {actions.map((action) => {
          const Icon = actionIcons[action.action_type] || Zap;
          return (
            <Card key={action.id} className="border-slate-800 bg-slate-900">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className={`flex h-9 w-9 items-center justify-center rounded-lg ${action.enabled ? "bg-indigo-500/20" : "bg-slate-800"}`}>
                      <Icon className={`h-5 w-5 ${action.enabled ? "text-indigo-400" : "text-slate-500"}`} />
                    </div>
                    <div>
                      <CardTitle className="text-base text-white">{action.label}</CardTitle>
                      <CardDescription className="text-xs text-slate-500">
                        {actionDescriptions[action.action_type] || action.action_type}
                      </CardDescription>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    {action.daily_limit > 0 && (
                      <Badge variant="secondary" className="text-xs">
                        {action.daily_count}/{action.daily_limit} hoje
                      </Badge>
                    )}
                    {savingId === action.id ? (
                      <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                    ) : (
                      <Switch checked={action.enabled} onCheckedChange={() => toggleAction(action)} />
                    )}
                  </div>
                </div>
              </CardHeader>
              {action.enabled && (
                <CardContent className="space-y-3 border-t border-slate-800 pt-3">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Limite diário (0 = sem limite)</Label>
                      <Input
                        type="number"
                        min="0"
                        value={action.daily_limit}
                        onChange={(e) => updateAction(action.id, { daily_limit: parseInt(e.target.value) || 0 })}
                        className="h-8 border-slate-700 bg-slate-800 text-sm text-white"
                      />
                    </div>
                  </div>
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-400">Instruções extras para a IA</Label>
                    <Textarea
                      placeholder="Ex: Sempre use o pacote de teste de 3h. Nunca crie mais de 1 teste por contato."
                      defaultValue={action.custom_instructions || ""}
                      onBlur={(e) => updateAction(action.id, { custom_instructions: e.target.value })}
                      rows={2}
                      className="border-slate-700 bg-slate-800 text-sm text-white placeholder:text-slate-500"
                    />
                  </div>
                </CardContent>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
