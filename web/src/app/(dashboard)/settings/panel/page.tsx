"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { PanelConfig } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { MonitorSmartphone, Loader2, CheckCircle, XCircle, Trash2 } from "lucide-react";
import { toast } from "sonner";

export default function PanelSettingsPage() {
  const [configs, setConfigs] = useState<PanelConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [form, setForm] = useState({ panel_name: "", panel_url: "", api_key: "" });

  useEffect(() => {
    loadConfigs();
  }, []);

  async function loadConfigs() {
    try {
      const res = await api.get("/panel");
      setConfigs(res.data.data);
    } catch { /* empty */ } finally {
      setLoading(false);
    }
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post("/panel", form);
      toast.success("Painel configurado!");
      setForm({ panel_name: "", panel_url: "", api_key: "" });
      loadConfigs();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao salvar.");
    } finally {
      setSaving(false);
    }
  }

  async function handleTest() {
    if (!form.panel_url || !form.api_key) {
      toast.error("Preencha URL e API Key para testar.");
      return;
    }
    setTesting(true);
    try {
      const res = await api.post("/panel/test", { panel_url: form.panel_url, api_key: form.api_key });
      if (res.data.success) {
        toast.success(res.data.message);
        loadConfigs();
      } else {
        toast.error(res.data.message);
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao testar conexão.");
    } finally {
      setTesting(false);
    }
  }

  async function handleDelete(id: number) {
    try {
      await api.delete(`/panel/${id}`);
      toast.success("Configuração removida.");
      loadConfigs();
    } catch { toast.error("Erro ao remover."); }
  }

  const statusBadge = (status: string) => {
    switch (status) {
      case "connected": return <Badge className="bg-green-600"><CheckCircle className="mr-1 h-3 w-3" />Conectado</Badge>;
      case "error": return <Badge variant="destructive"><XCircle className="mr-1 h-3 w-3" />Erro</Badge>;
      default: return <Badge variant="secondary">Não testado</Badge>;
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-white">Configurar Painel XUI</h1>

      <Card className="border-slate-800 bg-slate-900">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-white">
            <MonitorSmartphone className="h-5 w-5 text-indigo-400" />
            Adicionar Painel
          </CardTitle>
          <CardDescription className="text-slate-400">
            Conecte seu painel IPTV para que a IA possa executar ações automaticamente.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSave} className="space-y-4">
            <div className="space-y-2">
              <Label className="text-slate-300">Nome do Painel</Label>
              <Input
                placeholder="Meu Painel"
                value={form.panel_name}
                onChange={(e) => setForm({ ...form, panel_name: e.target.value })}
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label className="text-slate-300">URL do Painel</Label>
              <Input
                type="url"
                placeholder="https://seupainel.com"
                value={form.panel_url}
                onChange={(e) => setForm({ ...form, panel_url: e.target.value })}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label className="text-slate-300">API Key</Label>
              <Input
                type="password"
                placeholder="Sua chave de API do painel"
                value={form.api_key}
                onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="flex gap-3">
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Salvar
              </Button>
              <Button type="button" variant="outline" onClick={handleTest} disabled={testing} className="border-slate-700 text-slate-300 hover:bg-slate-800">
                {testing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle className="mr-2 h-4 w-4" />}
                Testar Conexão
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      {!loading && configs.length > 0 && (
        <Card className="border-slate-800 bg-slate-900">
          <CardHeader>
            <CardTitle className="text-white">Painéis Configurados</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {configs.map((c) => (
                <div key={c.id} className="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-800/50 p-4">
                  <div>
                    <p className="font-medium text-white">{c.panel_name}</p>
                    <p className="text-sm text-slate-400">{c.panel_url}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    {statusBadge(c.status)}
                    <Button variant="ghost" size="icon" onClick={() => handleDelete(c.id)} className="text-red-400 hover:text-red-300">
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
