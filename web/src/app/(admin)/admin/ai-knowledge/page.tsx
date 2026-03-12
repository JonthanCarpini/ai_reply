"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Brain, Plus, Pencil, Trash2, Power } from "lucide-react";
import { toast } from "sonner";

interface AdminAiKnowledge {
  id: number;
  name: string;
  system_prompt: string;
  apps_knowledge: string | null;
  description: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

const emptyForm = {
  name: "",
  system_prompt: "",
  apps_knowledge: "",
  description: "",
};

export default function AiKnowledgePage() {
  const [items, setItems] = useState<AdminAiKnowledge[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);

  const fetchItems = async () => {
    try {
      const res = await api.get("/admin/ai-knowledge");
      setItems(res.data.data);
    } catch {
      toast.error("Erro ao carregar knowledge base.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchItems(); }, []);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setDialogOpen(true);
  };

  const openEdit = (item: AdminAiKnowledge) => {
    setEditingId(item.id);
    setForm({
      name: item.name,
      system_prompt: item.system_prompt,
      apps_knowledge: item.apps_knowledge || "",
      description: item.description || "",
    });
    setDialogOpen(true);
  };

  const handleSave = async () => {
    try {
      if (editingId) {
        await api.put(`/admin/ai-knowledge/${editingId}`, form);
        toast.success("Knowledge base atualizado.");
      } else {
        await api.post("/admin/ai-knowledge", form);
        toast.success("Knowledge base criado.");
      }
      setDialogOpen(false);
      fetchItems();
    } catch (e: any) {
      toast.error(e.response?.data?.message || "Erro ao salvar.");
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Excluir este knowledge base?")) return;
    try {
      await api.delete(`/admin/ai-knowledge/${id}`);
      toast.success("Knowledge base removido.");
      fetchItems();
    } catch (e: any) {
      toast.error(e.response?.data?.message || "Erro ao excluir.");
    }
  };

  const handleActivate = async (id: number) => {
    try {
      await api.post(`/admin/ai-knowledge/${id}/activate`);
      toast.success("Knowledge base ativado.");
      fetchItems();
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
          <h1 className="text-2xl font-bold">Knowledge Base (Jarbs)</h1>
          <p className="text-muted-foreground">Defina o conhecimento e persona do assistente Jarbs</p>
        </div>
        <Button onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Novo Knowledge Base</Button>
      </div>

      <div className="grid gap-4">
        {items.map((item) => (
          <Card key={item.id} className={item.is_active ? "border-green-500" : ""}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-base flex items-center gap-2">
                <Brain className="h-4 w-4" />
                {item.name}
              </CardTitle>
              <div className="flex items-center gap-2">
                {item.is_active && <Badge className="bg-green-600">Ativo</Badge>}
                <Badge variant="outline">{item.system_prompt.length} chars</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-2">
              {item.description && (
                <p className="text-sm text-muted-foreground">{item.description}</p>
              )}
              {item.apps_knowledge && (
                <div className="rounded-md border border-slate-800 bg-slate-950/40 p-3">
                  <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Knowledge de aplicativos</p>
                  <p className="line-clamp-4 whitespace-pre-wrap text-xs text-slate-300">{item.apps_knowledge}</p>
                </div>
              )}
              <pre className="max-h-40 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap">
                {item.system_prompt.substring(0, 500)}
                {item.system_prompt.length > 500 && "..."}
              </pre>
              <div className="flex gap-2 pt-2">
                {!item.is_active && (
                  <Button size="sm" variant="outline" onClick={() => handleActivate(item.id)}>
                    <Power className="mr-1 h-3 w-3" />Ativar
                  </Button>
                )}
                <Button size="sm" variant="outline" onClick={() => openEdit(item)}>
                  <Pencil className="mr-1 h-3 w-3" />Editar
                </Button>
                {!item.is_active && (
                  <Button size="sm" variant="destructive" onClick={() => handleDelete(item.id)}>
                    <Trash2 className="mr-1 h-3 w-3" />Excluir
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>
        ))}

        {items.length === 0 && (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-10">
              <Brain className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-muted-foreground">Nenhum knowledge base configurado</p>
              <Button className="mt-4" onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Criar Knowledge Base</Button>
            </CardContent>
          </Card>
        )}
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingId ? "Editar Knowledge Base" : "Novo Knowledge Base"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Nome</Label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Ex: Jarbs v1" />
            </div>
            <div>
              <Label>Descrição (opcional)</Label>
              <Input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Breve descrição do knowledge base" />
            </div>
            <div>
              <Label>System Prompt (Knowledge Base)</Label>
              <Textarea
                value={form.system_prompt}
                onChange={(e) => setForm({ ...form, system_prompt: e.target.value })}
                placeholder="Insira todo o conhecimento do Jarbs aqui: IPTV, painel XUI, ações, testes, apps..."
                rows={20}
                className="font-mono text-sm"
              />
              <p className="text-xs text-muted-foreground mt-1">{form.system_prompt.length} / 50000 caracteres</p>
            </div>
            <div>
              <Label>Knowledge de Aplicativos</Label>
              <Textarea
                value={form.apps_knowledge}
                onChange={(e) => setForm({ ...form, apps_knowledge: e.target.value })}
                placeholder="Adicione conhecimento específico sobre aplicativos: quais apps recomendar por dispositivo, diferenças entre apps, vantagens, limitações, códigos, downloader, fluxo de instalação e regras para o Jarbs considerar ao gerar instruções e prompts."
                rows={12}
                className="font-mono text-sm"
              />
              <p className="text-xs text-muted-foreground mt-1">{form.apps_knowledge.length} / 50000 caracteres</p>
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
