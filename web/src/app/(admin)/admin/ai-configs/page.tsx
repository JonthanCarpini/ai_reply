"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Bot, Plus, Pencil, Trash2, Power } from "lucide-react";
import { toast } from "sonner";

interface AdminAiConfig {
  id: number;
  name: string;
  provider: string;
  model: string;
  temperature: number;
  max_tokens: number;
  is_active: boolean;
  has_api_key?: boolean;
  created_at: string;
}

const PROVIDERS = [
  { value: "openai", label: "OpenAI", models: ["gpt-4o", "gpt-4o-mini", "gpt-4-turbo", "gpt-3.5-turbo"] },
  { value: "anthropic", label: "Anthropic", models: ["claude-3-5-sonnet-20241022", "claude-3-haiku-20240307"] },
  { value: "google", label: "Google", models: ["gemini-1.5-pro", "gemini-1.5-flash"] },
  { value: "groq", label: "Groq", models: ["llama-3.3-70b-versatile", "llama-3.1-8b-instant", "mixtral-8x7b-32768"] },
  { value: "mistral", label: "Mistral", models: ["mistral-large-latest", "mistral-medium-latest", "mistral-small-latest"] },
];

const emptyForm = {
  name: "",
  provider: "openai",
  api_key: "",
  model: "gpt-4o-mini",
  temperature: 0.7,
  max_tokens: 4096,
};

export default function AiConfigsPage() {
  const [configs, setConfigs] = useState<AdminAiConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);

  const fetchConfigs = async () => {
    try {
      const res = await api.get("/admin/ai-configs");
      setConfigs(res.data.data);
    } catch {
      toast.error("Erro ao carregar configurações.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchConfigs(); }, []);

  const selectedProvider = PROVIDERS.find((p) => p.value === form.provider);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setDialogOpen(true);
  };

  const openEdit = (config: AdminAiConfig) => {
    setEditingId(config.id);
    setForm({
      name: config.name,
      provider: config.provider,
      api_key: "",
      model: config.model,
      temperature: config.temperature,
      max_tokens: config.max_tokens,
    });
    setDialogOpen(true);
  };

  const handleSave = async () => {
    try {
      const payload: Record<string, unknown> = { ...form };
      if (editingId && !form.api_key) delete payload.api_key;

      if (editingId) {
        await api.put(`/admin/ai-configs/${editingId}`, payload);
        toast.success("Provider atualizado.");
      } else {
        await api.post("/admin/ai-configs", payload);
        toast.success("Provider criado.");
      }
      setDialogOpen(false);
      fetchConfigs();
    } catch (e: any) {
      toast.error(e.response?.data?.message || "Erro ao salvar.");
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Excluir este provider?")) return;
    try {
      await api.delete(`/admin/ai-configs/${id}`);
      toast.success("Provider removido.");
      fetchConfigs();
    } catch (e: any) {
      toast.error(e.response?.data?.message || "Erro ao excluir.");
    }
  };

  const handleActivate = async (id: number) => {
    try {
      await api.post(`/admin/ai-configs/${id}/activate`);
      toast.success("Provider ativado.");
      fetchConfigs();
    } catch (e: any) {
      toast.error(e.response?.data?.message || "Erro ao ativar.");
    }
  };

  if (loading) {
    return <div className="flex items-center justify-center p-8"><div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" /></div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">AI Providers (Jarbs)</h1>
          <p className="text-muted-foreground">Configure os provedores de IA para o assistente Jarbs</p>
        </div>
        <Button onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Novo Provider</Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {configs.map((config) => (
          <Card key={config.id} className={config.is_active ? "border-green-500" : ""}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-base flex items-center gap-2">
                <Bot className="h-4 w-4" />
                {config.name}
              </CardTitle>
              {config.is_active && <Badge className="bg-green-600">Ativo</Badge>}
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="text-sm text-muted-foreground">
                <p><strong>Provider:</strong> {config.provider}</p>
                <p><strong>Modelo:</strong> {config.model}</p>
                <p><strong>Temperatura:</strong> {config.temperature}</p>
                <p><strong>Max Tokens:</strong> {config.max_tokens}</p>
              </div>
              <div className="flex gap-2 pt-2">
                {!config.is_active && (
                  <Button size="sm" variant="outline" onClick={() => handleActivate(config.id)}>
                    <Power className="mr-1 h-3 w-3" />Ativar
                  </Button>
                )}
                <Button size="sm" variant="outline" onClick={() => openEdit(config)}>
                  <Pencil className="mr-1 h-3 w-3" />Editar
                </Button>
                {!config.is_active && (
                  <Button size="sm" variant="destructive" onClick={() => handleDelete(config.id)}>
                    <Trash2 className="mr-1 h-3 w-3" />
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>
        ))}

        {configs.length === 0 && (
          <Card className="col-span-full">
            <CardContent className="flex flex-col items-center justify-center py-10">
              <Bot className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-muted-foreground">Nenhum provider configurado</p>
              <Button className="mt-4" onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Adicionar Provider</Button>
            </CardContent>
          </Card>
        )}
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{editingId ? "Editar Provider" : "Novo Provider"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Nome</Label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Ex: OpenAI GPT-4o" />
            </div>
            <div>
              <Label>Provider</Label>
              <Select value={form.provider} onValueChange={(v) => {
                const p = PROVIDERS.find((pr) => pr.value === v);
                setForm({ ...form, provider: v, model: p?.models[0] || "" });
              }}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {PROVIDERS.map((p) => (
                    <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>API Key {editingId && "(deixe vazio para manter a atual)"}</Label>
              <Input type="password" value={form.api_key} onChange={(e) => setForm({ ...form, api_key: e.target.value })} placeholder="sk-..." />
            </div>
            <div>
              <Label>Modelo</Label>
              <Select value={form.model} onValueChange={(v) => setForm({ ...form, model: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {selectedProvider?.models.map((m) => (
                    <SelectItem key={m} value={m}>{m}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Temperatura</Label>
                <Input type="number" step="0.1" min="0" max="2" value={form.temperature} onChange={(e) => setForm({ ...form, temperature: parseFloat(e.target.value) })} />
              </div>
              <div>
                <Label>Max Tokens</Label>
                <Input type="number" min="100" max="128000" value={form.max_tokens} onChange={(e) => setForm({ ...form, max_tokens: parseInt(e.target.value) })} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancelar</Button>
            <Button onClick={handleSave}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
