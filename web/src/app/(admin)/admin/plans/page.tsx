"use client";

import { useEffect, useState, useCallback } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Plus, Pencil, Trash2 } from "lucide-react";
import { toast } from "sonner";
import api from "@/lib/api";
import type { Plan } from "@/lib/types";

interface PlanWithCount extends Plan {
  subscriptions_count?: number;
}

const emptyForm = {
  name: "",
  slug: "",
  price: "0",
  messages_limit: "0",
  whatsapp_limit: "0",
  actions_limit: "0",
  ai_generation_limit: "5",
  analytics_enabled: true,
  priority_support: false,
  is_active: true,
};

export default function AdminPlansPage() {
  const [plans, setPlans] = useState<PlanWithCount[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);

  const fetchPlans = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get("/admin/plans");
      setPlans(res.data);
    } catch {
      toast.error("Erro ao carregar planos");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPlans();
  }, [fetchPlans]);

  const openNew = () => {
    setEditId(null);
    setForm(emptyForm);
    setDialogOpen(true);
  };

  const openEdit = (plan: PlanWithCount) => {
    setEditId(plan.id);
    setForm({
      name: plan.name,
      slug: plan.slug,
      price: plan.price.toString(),
      messages_limit: plan.messages_limit.toString(),
      whatsapp_limit: plan.whatsapp_limit.toString(),
      actions_limit: plan.actions_limit.toString(),
      ai_generation_limit: (plan.ai_generation_limit ?? 5).toString(),
      analytics_enabled: plan.analytics_enabled,
      priority_support: plan.priority_support,
      is_active: true,
    });
    setDialogOpen(true);
  };

  const savePlan = async () => {
    try {
      const payload = {
        name: form.name,
        slug: form.slug,
        price: parseFloat(form.price),
        messages_limit: parseInt(form.messages_limit),
        whatsapp_limit: parseInt(form.whatsapp_limit),
        actions_limit: parseInt(form.actions_limit),
        ai_generation_limit: parseInt(form.ai_generation_limit),
        analytics_enabled: form.analytics_enabled,
        priority_support: form.priority_support,
        is_active: form.is_active,
      };

      if (editId) {
        await api.put(`/admin/plans/${editId}`, payload);
        toast.success("Plano atualizado");
      } else {
        await api.post("/admin/plans", payload);
        toast.success("Plano criado");
      }

      setDialogOpen(false);
      fetchPlans();
    } catch {
      toast.error("Erro ao salvar plano");
    }
  };

  const deletePlan = async (id: number) => {
    if (!confirm("Tem certeza que deseja excluir este plano?")) return;
    try {
      await api.delete(`/admin/plans/${id}`);
      toast.success("Plano excluído");
      fetchPlans();
    } catch (err: unknown) {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || "Erro ao excluir";
      toast.error(message);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Planos</h1>
        <Button onClick={openNew}>
          <Plus className="mr-2 h-4 w-4" /> Novo Plano
        </Button>
      </div>

      {loading ? (
        <div className="text-center text-slate-400">Carregando...</div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {plans.map((plan) => (
            <Card key={plan.id} className="border-slate-800 bg-slate-900">
              <CardContent className="p-6">
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-white">{plan.name}</h3>
                    <p className="text-sm text-slate-400">{plan.slug}</p>
                  </div>
                  <div className="flex gap-1">
                    <Button size="icon" variant="ghost" onClick={() => openEdit(plan)}>
                      <Pencil className="h-4 w-4 text-slate-400" />
                    </Button>
                    <Button size="icon" variant="ghost" onClick={() => deletePlan(plan.id)}>
                      <Trash2 className="h-4 w-4 text-red-400" />
                    </Button>
                  </div>
                </div>

                <p className="mt-4 text-3xl font-bold text-white">
                  R$ {Number(plan.price).toFixed(2)}
                  <span className="text-sm font-normal text-slate-400">/mês</span>
                </p>

                <div className="mt-4 space-y-2 text-sm text-slate-300">
                  <div className="flex justify-between">
                    <span>Mensagens</span>
                    <span className="font-medium">{plan.messages_limit === 0 ? "Ilimitado" : plan.messages_limit.toLocaleString("pt-BR")}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>WhatsApp</span>
                    <span className="font-medium">{plan.whatsapp_limit === 0 ? "Ilimitado" : plan.whatsapp_limit}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Ações</span>
                    <span className="font-medium">{plan.actions_limit === 0 ? "Ilimitado" : plan.actions_limit}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Gerações IA (Jarbs)</span>
                    <span className="font-medium">{plan.ai_generation_limit === 0 ? "Ilimitado" : plan.ai_generation_limit ?? 5}</span>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-1">
                  {plan.analytics_enabled && <Badge variant="secondary">Analytics</Badge>}
                  {plan.priority_support && <Badge variant="secondary">Suporte Prioritário</Badge>}
                </div>

                {plan.subscriptions_count !== undefined && (
                  <p className="mt-3 text-xs text-slate-500">
                    {plan.subscriptions_count} assinante(s)
                  </p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="border-slate-800 bg-slate-900 text-white sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{editId ? "Editar Plano" : "Novo Plano"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Nome</Label>
                <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="border-slate-700 bg-slate-800" />
              </div>
              <div>
                <Label>Slug</Label>
                <Input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} className="border-slate-700 bg-slate-800" />
              </div>
            </div>
            <div>
              <Label>Preço (R$)</Label>
              <Input type="number" step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} className="border-slate-700 bg-slate-800" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Mensagens</Label>
                <Input type="number" value={form.messages_limit} onChange={(e) => setForm({ ...form, messages_limit: e.target.value })} className="border-slate-700 bg-slate-800" />
                <p className="mt-1 text-[10px] text-slate-500">0 = ilimitado</p>
              </div>
              <div>
                <Label>WhatsApp</Label>
                <Input type="number" value={form.whatsapp_limit} onChange={(e) => setForm({ ...form, whatsapp_limit: e.target.value })} className="border-slate-700 bg-slate-800" />
              </div>
              <div>
                <Label>Ações</Label>
                <Input type="number" value={form.actions_limit} onChange={(e) => setForm({ ...form, actions_limit: e.target.value })} className="border-slate-700 bg-slate-800" />
              </div>
              <div>
                <Label>Gerações IA (Jarbs)</Label>
                <Input type="number" value={form.ai_generation_limit} onChange={(e) => setForm({ ...form, ai_generation_limit: e.target.value })} className="border-slate-700 bg-slate-800" />
                <p className="mt-1 text-[10px] text-slate-500">0 = ilimitado</p>
              </div>
            </div>
            <div className="flex items-center justify-between">
              <Label>Analytics</Label>
              <Switch checked={form.analytics_enabled} onCheckedChange={(v) => setForm({ ...form, analytics_enabled: v })} />
            </div>
            <div className="flex items-center justify-between">
              <Label>Suporte Prioritário</Label>
              <Switch checked={form.priority_support} onCheckedChange={(v) => setForm({ ...form, priority_support: v })} />
            </div>
            <div className="flex items-center justify-between">
              <Label>Ativo</Label>
              <Switch checked={form.is_active} onCheckedChange={(v) => setForm({ ...form, is_active: v })} />
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancelar</Button>
              <Button onClick={savePlan}>Salvar</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
