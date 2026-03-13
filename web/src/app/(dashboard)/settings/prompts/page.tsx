"use client";

import { useEffect, useState, type ChangeEvent, type Dispatch, type FormEvent, type SetStateAction } from "react";
import api from "@/lib/api";
import type { Prompt } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
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

interface PromptFormState {
  name: string;
  system_prompt: string;
  greeting_message: string;
  fallback_message: string;
  offline_message: string;
  identity: string;
  tone: string;
  permanent_rules: string;
  automatic_triggers: string;
  phase_flow: string;
  response_policy: string;
  max_chars: number;
  max_tool_steps: number;
  enforce_short_reply: boolean;
  blocked_terms: string;
}

function createPromptFormState(prompt?: Prompt, generatedPrompt?: string): PromptFormState {
  const structuredPrompt = prompt?.structured_prompt || {};
  const replyPolicy = prompt?.reply_policy || {};

  return {
    name: prompt?.name || "",
    system_prompt: generatedPrompt || prompt?.system_prompt || defaultPrompt,
    greeting_message: prompt?.greeting_message || "",
    fallback_message: prompt?.fallback_message || "",
    offline_message: prompt?.offline_message || "",
    identity: structuredPrompt.identity || "",
    tone: structuredPrompt.tone || "",
    permanent_rules: structuredPrompt.permanent_rules || "",
    automatic_triggers: structuredPrompt.automatic_triggers || "",
    phase_flow: structuredPrompt.phase_flow || "",
    response_policy: structuredPrompt.response_policy || "",
    max_chars: replyPolicy.max_chars || 1200,
    max_tool_steps: replyPolicy.max_tool_steps || 2,
    enforce_short_reply: Boolean(replyPolicy.enforce_short_reply),
    blocked_terms: (replyPolicy.blocked_terms || []).join(", "),
  };
}

function buildPromptPayload(form: PromptFormState) {
  const blockedTerms = form.blocked_terms
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);

  return {
    name: form.name,
    system_prompt: form.system_prompt,
    greeting_message: form.greeting_message,
    fallback_message: form.fallback_message,
    offline_message: form.offline_message,
    structured_prompt: {
      identity: form.identity,
      tone: form.tone,
      permanent_rules: form.permanent_rules,
      automatic_triggers: form.automatic_triggers,
      phase_flow: form.phase_flow,
      response_policy: form.response_policy,
    },
    reply_policy: {
      max_chars: form.max_chars,
      max_tool_steps: form.max_tool_steps,
      enforce_short_reply: form.enforce_short_reply,
      blocked_terms: blockedTerms,
    },
  };
}

function countStructuredSections(prompt: Prompt): number {
  return Object.values(prompt.structured_prompt || {}).filter((value) => typeof value === "string" && value.trim() !== "").length;
}

function handleTextInput(
  setForm: Dispatch<SetStateAction<PromptFormState>>,
  field: keyof PromptFormState,
) {
  return (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const value = e.target.value;
    setForm((prev) => ({ ...prev, [field]: value }));
  };
}

export default function PromptsPage() {
  const [prompts, setPrompts] = useState<Prompt[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<PromptFormState>(createPromptFormState());

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
      setForm(createPromptFormState(undefined, previewPrompt));
      setForm((prev) => ({ ...prev, name: "Gerado por Jarbs" }));
      setEditingId(null);
      setDialogOpen(true);
    } else {
      try {
        const currentPrompt = prompts.find((prompt: Prompt) => prompt.id === previewTarget);
        if (!currentPrompt) {
          toast.error("Prompt não encontrado para atualização.");
          setPreviewOpen(false);
          return;
        }

        await api.put(`/prompts/${previewTarget}`, {
          ...buildPromptPayload(createPromptFormState(currentPrompt, previewPrompt)),
        });
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
    setForm(createPromptFormState());
    setDialogOpen(true);
  }

  function openEdit(prompt: Prompt) {
    setEditingId(prompt.id);
    setForm(createPromptFormState(prompt));
    setDialogOpen(true);
  }

  const onNameChange = handleTextInput(setForm, "name");
  const onSystemPromptChange = handleTextInput(setForm, "system_prompt");
  const onGreetingChange = handleTextInput(setForm, "greeting_message");
  const onFallbackChange = handleTextInput(setForm, "fallback_message");
  const onOfflineChange = handleTextInput(setForm, "offline_message");
  const onIdentityChange = handleTextInput(setForm, "identity");
  const onToneChange = handleTextInput(setForm, "tone");
  const onPermanentRulesChange = handleTextInput(setForm, "permanent_rules");
  const onAutomaticTriggersChange = handleTextInput(setForm, "automatic_triggers");
  const onPhaseFlowChange = handleTextInput(setForm, "phase_flow");
  const onResponsePolicyChange = handleTextInput(setForm, "response_policy");
  const onBlockedTermsChange = handleTextInput(setForm, "blocked_terms");

  async function handleSave(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSaving(true);
    const payload = buildPromptPayload(form);
    try {
      if (editingId) {
        await api.put(`/prompts/${editingId}`, payload);
        toast.success("Prompt atualizado!");
      } else {
        await api.post("/prompts", payload);
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
                <Input value={form.name} onChange={onNameChange} required placeholder="Ex: Atendimento Padrão" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">System Prompt (Persona)</Label>
                <Textarea value={form.system_prompt} onChange={onSystemPromptChange} required rows={10} className="border-slate-700 bg-slate-800 font-mono text-sm text-white placeholder:text-slate-500" />
                <p className="text-xs text-slate-500">Use {"{variavel}"} para variáveis dinâmicas.</p>
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem de Saudação</Label>
                <Textarea value={form.greeting_message} onChange={onGreetingChange} rows={2} placeholder="Olá! Sou o assistente da Loja X. Como posso ajudar?" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem Fallback (IA não sabe)</Label>
                <Textarea value={form.fallback_message} onChange={onFallbackChange} rows={2} placeholder="Desculpe, não entendi. Vou transferir para atendimento humano." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="space-y-2">
                <Label className="text-slate-300">Mensagem Offline (fora do horário)</Label>
                <Textarea value={form.offline_message} onChange={onOfflineChange} rows={2} placeholder="Estamos fora do horário. Retornamos amanhã às 8h." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label className="text-slate-300">Identidade</Label>
                  <Textarea value={form.identity} onChange={onIdentityChange} rows={3} placeholder="Quem é o assistente, contexto da operação, papel principal." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Tom</Label>
                  <Textarea value={form.tone} onChange={onToneChange} rows={3} placeholder="Objetivo, profissional, breve, cordial." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Regras permanentes</Label>
                  <Textarea value={form.permanent_rules} onChange={onPermanentRulesChange} rows={4} placeholder="Nunca inventar preços, nunca enviar credenciais sem confirmação." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Gatilhos automáticos</Label>
                  <Textarea value={form.automatic_triggers} onChange={onAutomaticTriggersChange} rows={4} placeholder="Se pedir teste, priorizar create_test. Se citar app, identificar device_type." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label className="text-slate-300">Fluxo por fase</Label>
                  <Textarea value={form.phase_flow} onChange={onPhaseFlowChange} rows={4} placeholder="Qualificação → coletar dados mínimos. Teste → agir rápido. Suporte → diagnosticar antes de escalar." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label className="text-slate-300">Política textual de resposta</Label>
                  <Textarea value={form.response_policy} onChange={onResponsePolicyChange} rows={4} placeholder="Evite blocos longos. Faça uma pergunta por vez. Cite próximo passo claramente." className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                </div>
              </div>
              <div className="rounded-lg border border-slate-800 bg-slate-950/60 p-4">
                <div className="mb-3 flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-white">Política operacional da resposta</p>
                    <p className="text-xs text-slate-500">Aplicada após o raciocínio do modelo para manter consistência operacional.</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Switch checked={form.enforce_short_reply} onCheckedChange={(checked: boolean) => setForm({ ...form, enforce_short_reply: checked })} />
                    <span className="text-xs text-slate-300">Forçar resposta curta</span>
                  </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label className="text-slate-300">Máximo de caracteres</Label>
                    <Input type="number" min="120" max="3000" value={form.max_chars} onChange={(e: ChangeEvent<HTMLInputElement>) => setForm({ ...form, max_chars: parseInt(e.target.value) || 1200 })} className="border-slate-700 bg-slate-800 text-white" />
                  </div>
                  <div className="space-y-2">
                    <Label className="text-slate-300">Máximo de passos de tools</Label>
                    <Input type="number" min="1" max="3" value={form.max_tool_steps} onChange={(e: ChangeEvent<HTMLInputElement>) => setForm({ ...form, max_tool_steps: Math.min(3, Math.max(1, parseInt(e.target.value) || 2)) })} className="border-slate-700 bg-slate-800 text-white" />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label className="text-slate-300">Termos bloqueados</Label>
                    <Input value={form.blocked_terms} onChange={onBlockedTermsChange} placeholder="senha, token, acesso root" className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500" />
                  </div>
                </div>
              </div>
              <Button type="submit" className="w-full bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                {editingId ? "Atualizar" : "Criar"} Prompt
              </Button>
            </form>
          </DialogContent>
        </Dialog>
        </div>
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
          {prompts.map((p: Prompt) => (
            <Card key={p.id} className="border-slate-800 bg-slate-900">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <CardTitle className="text-base text-white">{p.name}</CardTitle>
                    {p.is_active && <Badge className="bg-green-600">Ativo</Badge>}
                    <Badge variant="secondary">v{p.version}</Badge>
                    <Badge variant="outline" className="border-slate-700 text-slate-300">
                      {countStructuredSections(p)} seções estruturadas
                    </Badge>
                    <Badge variant="outline" className="border-slate-700 text-slate-300">
                      {p.reply_policy?.max_tool_steps || 2} passos de tool
                    </Badge>
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
                {!!p.reply_policy?.blocked_terms?.length && (
                  <div className="mt-2 flex flex-wrap gap-2">
                    {p.reply_policy.blocked_terms.slice(0, 4).map((term: string) => (
                      <Badge key={term} variant="outline" className="border-red-500/30 text-red-300">
                        bloqueado: {term}
                      </Badge>
                    ))}
                  </div>
                )}
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
