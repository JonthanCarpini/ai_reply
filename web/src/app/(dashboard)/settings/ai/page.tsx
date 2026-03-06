"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { AiConfig } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Brain, Loader2, CheckCircle } from "lucide-react";
import { toast } from "sonner";

const providers = [
  { value: "openai", label: "OpenAI", models: ["gpt-4o-mini", "gpt-4o", "gpt-4-turbo"] },
  { value: "anthropic", label: "Anthropic (Claude)", models: ["claude-3-haiku-20240307", "claude-3-5-sonnet-20241022"] },
  { value: "google", label: "Google (Gemini)", models: ["gemini-1.5-flash", "gemini-1.5-pro"] },
];

export default function AiSettingsPage() {
  const [config, setConfig] = useState<AiConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [form, setForm] = useState({
    provider: "openai",
    api_key: "",
    model: "gpt-4o-mini",
    temperature: 0.7,
    max_tokens: 500,
  });

  useEffect(() => {
    loadConfig();
  }, []);

  async function loadConfig() {
    try {
      const res = await api.get("/ai-config");
      if (res.data.data) {
        setConfig(res.data.data);
        setForm((prev) => ({
          ...prev,
          provider: res.data.data.provider,
          model: res.data.data.model,
          temperature: res.data.data.temperature,
          max_tokens: res.data.data.max_tokens,
        }));
      }
    } catch { /* empty */ } finally {
      setLoading(false);
    }
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post("/ai-config", form);
      toast.success("Configuração de IA salva!");
      loadConfig();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao salvar.");
    } finally {
      setSaving(false);
    }
  }

  async function handleTest() {
    setTesting(true);
    try {
      const res = await api.post("/ai-config/test");
      if (res.data.success) toast.success(res.data.message);
      else toast.error(res.data.message);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao testar.");
    } finally {
      setTesting(false);
    }
  }

  const currentProvider = providers.find((p) => p.value === form.provider);

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-white">Inteligência Artificial</h1>

      <Card className="border-slate-800 bg-slate-900">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-white">
            <Brain className="h-5 w-5 text-purple-400" />
            Configurar Provedor de IA
          </CardTitle>
          <CardDescription className="text-slate-400">
            Escolha o provedor, modelo e insira sua API key. O custo da IA é por sua conta.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSave} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label className="text-slate-300">Provedor</Label>
                <Select value={form.provider} onValueChange={(v) => {
                  const prov = providers.find((p) => p.value === v);
                  setForm({ ...form, provider: v, model: prov?.models[0] || "" });
                }}>
                  <SelectTrigger className="border-slate-700 bg-slate-800 text-white">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {providers.map((p) => (
                      <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Modelo</Label>
                <Select value={form.model} onValueChange={(v) => setForm({ ...form, model: v })}>
                  <SelectTrigger className="border-slate-700 bg-slate-800 text-white">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {currentProvider?.models.map((m) => (
                      <SelectItem key={m} value={m}>{m}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label className="text-slate-300">API Key</Label>
              <Input
                type="password"
                placeholder={config?.has_api_key ? "••••••••• (já configurada)" : "sk-..."}
                value={form.api_key}
                onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                required={!config?.has_api_key}
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label className="text-slate-300">Temperatura ({form.temperature})</Label>
                <Input
                  type="range"
                  min="0"
                  max="2"
                  step="0.1"
                  value={form.temperature}
                  onChange={(e) => setForm({ ...form, temperature: parseFloat(e.target.value) })}
                  className="accent-indigo-600"
                />
                <p className="text-xs text-slate-500">0 = determinístico, 2 = criativo</p>
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Max Tokens</Label>
                <Input
                  type="number"
                  min="50"
                  max="4096"
                  value={form.max_tokens}
                  onChange={(e) => setForm({ ...form, max_tokens: parseInt(e.target.value) })}
                  className="border-slate-700 bg-slate-800 text-white"
                />
              </div>
            </div>

            <div className="flex gap-3">
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Salvar
              </Button>
              {config && (
                <Button type="button" variant="outline" onClick={handleTest} disabled={testing} className="border-slate-700 text-slate-300 hover:bg-slate-800">
                  {testing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle className="mr-2 h-4 w-4" />}
                  Testar Conexão
                </Button>
              )}
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
