"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Prompt } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { FileText, Plus, Loader2, Star, Pencil, Trash2, Sparkles, Wand2 } from "lucide-react";
import { toast } from "sonner";

const defaultPrompt = `Você é o assistente virtual da {loja_nome}. Seu nome é {assistente_nome}.

REGRAS:
- Seja educado, objetivo e profissional
- Responda em português brasileiro
- Use emojis moderadamente
- NUNCA invente informações sobre preços ou pacotes — sempre consulte via ferramenta
- Quando o cliente pedir teste, crie usando a ferramenta criar_teste
- Quando o cliente confirmar pagamento, peça o username para renovar
- Se não souber algo, transfira para atendimento humano`;

interface JarbsStatus {
  available: boolean;
  limit: number;
  used: number;
  remaining: number;
}

export default function PromptsPage() {
  const [prompts, setPrompts] = useState<Prompt[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState({
    name: "",
    system_prompt: defaultPrompt,
    greeting_message: "",
    fallback_message: "",
    offline_message: "",
  });

  const [jarbsStatus, setJarbsStatus] = useState<JarbsStatus | null>(null);
  const [generating, setGenerating] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewPrompt, setPreviewPrompt] = useState("");
  const [previewTarget, setPreviewTarget] = useState<"new" | number>("new");

  useEffect(() => { loadPrompts(); loadJarbsStatus(); }, []);

  async function loadJarbsStatus() {
    try {
      const res = await api.get("/jarbs/status");
      setJarbsStatus(res.data);
    } catch { /* Jarbs indisponível */ }
  }

  async function handleGenerate() {
    setGenerating(true);
    try {
      const res = await api.post("/jarbs/generate", { business_description: "Revenda de IPTV com painel XUI" });
      setPreviewPrompt(res.data.prompt);
      setPreviewTarget("new");
      setPreviewOpen(true);
      loadJarbsStatus();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao gerar prompt.");
    } finally { setGenerating(false); }
  }

  async function handleImprove(promptId: number, currentPrompt: string) {
    setGenerating(true);
    try {
      const res = await api.post("/jarbs/improve", { current_prompt: currentPrompt });
      setPreviewPrompt(res.data.prompt);
      setPreviewTarget(promptId);
      setPreviewOpen(true);
      loadJarbsStatus();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao melhorar prompt.");
    } finally { setGenerating(false); }
  }

  async function handleAcceptPreview() {
    if (previewTarget === "new") {
      setForm({ name: "Gerado por Jarbs", system_prompt: previewPrompt, greeting_message: "", fallback_message: "", offline_message: "" });
      setEditingId(null);
      setDialogOpen(true);
    } else {
      try {
        await api.put(`/prompts/${previewTarget}`, { system_prompt: previewPrompt });
        toast.success("Prompt atualizado com sucesso!");
        loadPrompts();
      } catch { toast.error("Erro ao atualizar prompt."); }
    }
    setPreviewOpen(false);
  }

  async function loadPrompts() {
    try {
      const res = await api.get("/prompts");
      setPrompts(res.data.data);
    } catch { /* empty */ } finally { setLoading(false); }
  }

  function openNew() {
    setEditingId(null);
    setForm({ name: "", system_prompt: defaultPrompt, greeting_message: "", fallback_message: "", offline_message: "" });
    setDialogOpen(true);
  }

  function openEdit(prompt: Prompt) {
    setEditingId(prompt.id);
    setForm({
      name: prompt.name,
      system_prompt: prompt.system_prompt,
      greeting_message: prompt.greeting_message || "",
      fallback_message: prompt.fallback_message || "",
      offline_message: prompt.offline_message || "",
    });
    setDialogOpen(true);
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      if (editingId) {
        await api.put(`/prompts/${editingId}`, form);
        toast.success("Prompt atualizado!");
      } else {
        await api.post("/prompts", form);
        toast.success("Prompt criado!");
      }
      setDialogOpen(false);
      loadPrompts();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao salvar.");
    } finally { setSaving(false); }
  }

  async function handleActivate(id: number) {
    try {
      await api.post(`/prompts/${id}/activate`);
      toast.success("Prompt ativado!");
      loadPrompts();
    } catch { toast.error("Erro ao ativar."); }
  }

  async function handleDelete(id: number) {
    try {
      await api.delete(`/prompts/${id}`);
      toast.success("Prompt removido.");
      loadPrompts();
    } catch { toast.error("Erro ao remover."); }
  }

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Prompts / Persona</h1>
          {jarbsStatus && jarbsStatus.available && (
            <p className="text-xs text-slate-500 mt-1">
              Jarbs: {jarbsStatus.remaining === -1 ? "Ilimitado" : `${jarbsStatus.remaining} gerações restantes este mês`}
            </p>
          )}
        </div>
        <div className="flex gap-2">
          {jarbsStatus?.available && (
            <Button
              onClick={handleGenerate}
              disabled={generating || (jarbsStatus.remaining === 0)}
              className="bg-purple-600 hover:bg-purple-700"
            >
              {generating ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Sparkles className="mr-2 h-4 w-4" />}
              Gerar Prompt (IA)
            </Button>
          )}
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={openNew} className="bg-indigo-600 hover:bg-indigo-700">
                <Plus className="mr-2 h-4 w-4" />Novo Prompt
              </Button>
            </DialogTrigger>
          <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto border-slate-800 bg-slate-900">
            <DialogHeader>
              <DialogTitle className="text-white">{editingId ? "Editar" : "Novo"} Prompt</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleSave} className="space-y-4">
              <div className="space-y-2">
                <Label className="text-slate-300">Nome</Label>
                <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required placeholder="Ex: Atendimento Padrão" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">System Prompt (Persona)</Label>
                <Textarea value={form.system_prompt} onChange={(e) => setForm({ ...form, system_prompt: e.target.value })} required rows={10} className="border-slate-700 bg-slate-800 font-mono text-sm text-white placeholder:text-slate-500" />
                <p className="text-xs text-slate-500">Use {"{variavel}"} para variáveis dinâmicas.</p>
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem de Saudação</Label>
                <Textarea value={form.greeting_message} onChange={(e) => setForm({ ...form, greeting_message: e.target.value })} rows={2} placeholder="Olá! Sou o assistente da Loja X. Como posso ajudar?" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem Fallback (IA não sabe)</Label>
                <Textarea value={form.fallback_message} onChange={(e) => setForm({ ...form, fallback_message: e.target.value })} rows={2} placeholder="Desculpe, não entendi. Vou transferir para atendimento humano." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem Offline (fora do horário)</Label>
                <Textarea value={form.offline_message} onChange={(e) => setForm({ ...form, offline_message: e.target.value })} rows={2} placeholder="Estamos fora do horário. Retornamos amanhã às 8h." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <Button type="submit" className="w-full bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                {editingId ? "Atualizar" : "Criar"} Prompt
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {prompts.length === 0 ? (
        <Card className="border-slate-800 bg-slate-900">
          <CardContent className="flex flex-col items-center justify-center py-12">
            <FileText className="mb-4 h-12 w-12 text-slate-600" />
            <p className="text-slate-400">Nenhum prompt criado ainda.</p>
            <Button onClick={openNew} variant="outline" className="mt-4 border-slate-700 text-slate-300">Criar primeiro prompt</Button>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {prompts.map((p) => (
            <Card key={p.id} className="border-slate-800 bg-slate-900">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <CardTitle className="text-base text-white">{p.name}</CardTitle>
                    {p.is_active && <Badge className="bg-green-600">Ativo</Badge>}
                    <Badge variant="secondary">v{p.version}</Badge>
                  </div>
                  <div className="flex gap-2">
                    {!p.is_active && (
                      <Button variant="ghost" size="sm" onClick={() => handleActivate(p.id)} className="text-amber-400 hover:text-amber-300">
                        <Star className="mr-1 h-3 w-3" />Ativar
                      </Button>
                    )}
                    {jarbsStatus?.available && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleImprove(p.id, p.system_prompt)}
                        disabled={generating || (jarbsStatus.remaining === 0)}
                        className="text-purple-400 hover:text-purple-300"
                      >
                        {generating ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Wand2 className="mr-1 h-3 w-3" />}
                        Melhorar
                      </Button>
                    )}
                    <Button variant="ghost" size="icon" onClick={() => openEdit(p)} className="text-slate-400 hover:text-white">
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => handleDelete(p.id)} className="text-red-400 hover:text-red-300">
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
                <CardDescription className="mt-1 line-clamp-2 text-slate-500">{p.system_prompt}</CardDescription>
              </CardHeader>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto border-slate-800 bg-slate-900">
          <DialogHeader>
            <DialogTitle className="text-white flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-purple-400" />
              {previewTarget === "new" ? "Prompt Gerado pelo Jarbs" : "Prompt Melhorado pelo Jarbs"}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="rounded-lg border border-slate-700 bg-slate-800 p-4">
              <pre className="whitespace-pre-wrap text-sm text-slate-200 font-mono">{previewPrompt}</pre>
            </div>
            <p className="text-xs text-slate-500">{previewPrompt.length} caracteres</p>
            <div className="flex gap-3">
              <Button onClick={handleAcceptPreview} className="flex-1 bg-green-600 hover:bg-green-700">
                Aceitar e Aplicar
              </Button>
              <Button onClick={() => setPreviewOpen(false)} variant="outline" className="flex-1 border-slate-700 text-slate-300">
                Rejeitar
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
