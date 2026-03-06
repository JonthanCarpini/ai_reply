"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Rule } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Shield, Plus, Clock, Ban, UserCheck, MessageSquare, Gauge, Trash2, Loader2 } from "lucide-react";
import { toast } from "sonner";

const ruleTypes = [
  { value: "schedule", label: "Horário de Atendimento", icon: Clock, description: "Defina o horário em que a IA responde." },
  { value: "blacklist", label: "Lista Negra", icon: Ban, description: "Contatos que a IA deve ignorar." },
  { value: "whitelist", label: "Lista VIP", icon: UserCheck, description: "Responder apenas esses contatos." },
  { value: "keyword", label: "Palavra-chave", icon: MessageSquare, description: "Ação especial quando detectar palavras." },
  { value: "rate_limit", label: "Limite por Contato", icon: Gauge, description: "Máximo de mensagens por contato/hora." },
];

export default function RulesPage() {
  const [rules, setRules] = useState<Rule[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [formType, setFormType] = useState("schedule");
  const [formConfig, setFormConfig] = useState<Record<string, unknown>>({});

  useEffect(() => { loadRules(); }, []);

  async function loadRules() {
    try {
      const res = await api.get("/rules");
      setRules(res.data.data);
    } catch { /* empty */ } finally { setLoading(false); }
  }

  function openNew(type: string) {
    setFormType(type);
    switch (type) {
      case "schedule": setFormConfig({ start: "08:00", end: "22:00", days: [1, 2, 3, 4, 5, 6] }); break;
      case "blacklist": setFormConfig({ phones: "" }); break;
      case "whitelist": setFormConfig({ phones: "" }); break;
      case "keyword": setFormConfig({ keywords: "", action: "transfer_human" }); break;
      case "rate_limit": setFormConfig({ max_per_contact: 10, period: "hour" }); break;
    }
    setDialogOpen(true);
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      let config = { ...formConfig };
      if (formType === "blacklist" || formType === "whitelist") {
        config = { phones: (config.phones as string).split(",").map((p: string) => p.trim()).filter(Boolean) };
      }
      if (formType === "keyword") {
        config = { ...config, keywords: (config.keywords as string).split(",").map((k: string) => k.trim()).filter(Boolean) };
      }
      await api.post("/rules", { type: formType, config, enabled: true });
      toast.success("Regra criada!");
      setDialogOpen(false);
      loadRules();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao salvar.");
    } finally { setSaving(false); }
  }

  async function toggleRule(rule: Rule) {
    try {
      await api.put(`/rules/${rule.id}`, { enabled: !rule.enabled });
      loadRules();
    } catch { toast.error("Erro ao atualizar."); }
  }

  async function deleteRule(id: number) {
    try {
      await api.delete(`/rules/${id}`);
      toast.success("Regra removida.");
      loadRules();
    } catch { toast.error("Erro ao remover."); }
  }

  const getRuleIcon = (type: string) => {
    const found = ruleTypes.find((r) => r.value === type);
    return found?.icon || Shield;
  };

  const getRuleLabel = (type: string) => ruleTypes.find((r) => r.value === type)?.label || type;

  const formatConfig = (rule: Rule): string => {
    const c = rule.config;
    switch (rule.type) {
      case "schedule": return `${c.start} - ${c.end}`;
      case "blacklist": return `${(c.phones as string[])?.length || 0} contatos bloqueados`;
      case "whitelist": return `${(c.phones as string[])?.length || 0} contatos VIP`;
      case "keyword": return `Palavras: ${(c.keywords as string[])?.join(", ")}`;
      case "rate_limit": return `Máx ${c.max_per_contact}/${c.period}`;
      default: return JSON.stringify(c);
    }
  };

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Regras</h1>
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-indigo-600 hover:bg-indigo-700">
              <Plus className="mr-2 h-4 w-4" />Nova Regra
            </Button>
          </DialogTrigger>
          <DialogContent className="border-slate-800 bg-slate-900">
            <DialogHeader>
              <DialogTitle className="text-white">Nova Regra</DialogTitle>
            </DialogHeader>
            {!dialogOpen ? null : (
              <form onSubmit={handleSave} className="space-y-4">
                <div className="space-y-2">
                  <Label className="text-slate-300">Tipo</Label>
                  <Select value={formType} onValueChange={(v) => openNew(v)}>
                    <SelectTrigger className="border-slate-700 bg-slate-800 text-white"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {ruleTypes.map((r) => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>

                {formType === "schedule" && (
                  <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Início</Label>
                      <Input type="time" value={formConfig.start as string} onChange={(e) => setFormConfig({ ...formConfig, start: e.target.value })} className="border-slate-700 bg-slate-800 text-white" />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Fim</Label>
                      <Input type="time" value={formConfig.end as string} onChange={(e) => setFormConfig({ ...formConfig, end: e.target.value })} className="border-slate-700 bg-slate-800 text-white" />
                    </div>
                  </div>
                )}

                {(formType === "blacklist" || formType === "whitelist") && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-400">Números (separados por vírgula)</Label>
                    <Input value={formConfig.phones as string} onChange={(e) => setFormConfig({ ...formConfig, phones: e.target.value })} placeholder="5511999887766, 5521988776655" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                  </div>
                )}

                {formType === "keyword" && (
                  <>
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Palavras-chave (separadas por vírgula)</Label>
                      <Input value={formConfig.keywords as string} onChange={(e) => setFormConfig({ ...formConfig, keywords: e.target.value })} placeholder="urgente, problema, reclamação" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Ação</Label>
                      <Select value={formConfig.action as string} onValueChange={(v) => setFormConfig({ ...formConfig, action: v })}>
                        <SelectTrigger className="border-slate-700 bg-slate-800 text-white"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="transfer_human">Transferir p/ humano</SelectItem>
                          <SelectItem value="priority">Prioridade alta</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </>
                )}

                {formType === "rate_limit" && (
                  <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Máximo por contato</Label>
                      <Input type="number" min="1" value={formConfig.max_per_contact as number} onChange={(e) => setFormConfig({ ...formConfig, max_per_contact: parseInt(e.target.value) })} className="border-slate-700 bg-slate-800 text-white" />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-slate-400">Período</Label>
                      <Select value={formConfig.period as string} onValueChange={(v) => setFormConfig({ ...formConfig, period: v })}>
                        <SelectTrigger className="border-slate-700 bg-slate-800 text-white"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="hour">Por hora</SelectItem>
                          <SelectItem value="day">Por dia</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                )}

                <Button type="submit" className="w-full bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                  {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  Criar Regra
                </Button>
              </form>
            )}
          </DialogContent>
        </Dialog>
      </div>

      {rules.length === 0 ? (
        <Card className="border-slate-800 bg-slate-900">
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Shield className="mb-4 h-12 w-12 text-slate-600" />
            <p className="text-slate-400">Nenhuma regra criada.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {rules.map((rule) => {
            const Icon = getRuleIcon(rule.type);
            return (
              <Card key={rule.id} className="border-slate-800 bg-slate-900">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <Icon className={`h-5 w-5 ${rule.enabled ? "text-indigo-400" : "text-slate-500"}`} />
                      <div>
                        <CardTitle className="text-sm text-white">{getRuleLabel(rule.type)}</CardTitle>
                        <CardDescription className="text-xs text-slate-500">{formatConfig(rule)}</CardDescription>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Switch checked={rule.enabled} onCheckedChange={() => toggleRule(rule)} />
                      <Button variant="ghost" size="icon" onClick={() => deleteRule(rule.id)} className="text-red-400 hover:text-red-300">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </CardHeader>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
