"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Conversation } from "@/lib/types";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { MessageSquare, Archive, Ban, Trash2, Phone } from "lucide-react";
import { toast } from "sonner";

export default function ConversationsPage() {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { loadConversations(); }, []);

  async function loadConversations() {
    try {
      const res = await api.get("/conversations");
      setConversations(res.data.data || []);
    } catch { /* empty */ } finally { setLoading(false); }
  }

  async function archiveConversation(id: number) {
    try {
      await api.put(`/conversations/${id}/archive`);
      toast.success("Conversa arquivada.");
      loadConversations();
    } catch { toast.error("Erro."); }
  }

  async function blockConversation(id: number) {
    try {
      await api.put(`/conversations/${id}/block`);
      toast.success("Contato bloqueado.");
      loadConversations();
    } catch { toast.error("Erro."); }
  }

  async function deleteConversation(id: number) {
    try {
      await api.delete(`/conversations/${id}`);
      toast.success("Conversa excluída.");
      loadConversations();
    } catch { toast.error("Erro."); }
  }

  const statusColor = (status: string) => {
    switch (status) {
      case "active": return "bg-green-600";
      case "archived": return "bg-slate-600";
      case "blocked": return "bg-red-600";
      default: return "bg-slate-600";
    }
  };

  const statusLabel = (status: string) => {
    switch (status) {
      case "active": return "Ativa";
      case "archived": return "Arquivada";
      case "blocked": return "Bloqueado";
      default: return status;
    }
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-white">Conversas</h1>
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="h-20 animate-pulse rounded-lg bg-slate-900" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Conversas</h1>
        <Badge variant="secondary">{conversations.length} conversas</Badge>
      </div>

      {conversations.length === 0 ? (
        <Card className="border-slate-800 bg-slate-900">
          <CardContent className="flex flex-col items-center justify-center py-16">
            <MessageSquare className="mb-4 h-16 w-16 text-slate-600" />
            <p className="text-lg text-slate-400">Nenhuma conversa ainda</p>
            <p className="mt-1 text-sm text-slate-500">
              As conversas aparecerão aqui quando o app começar a responder mensagens.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {conversations.map((conv) => (
            <Card key={conv.id} className="border-slate-800 bg-slate-900 transition-colors hover:border-slate-700">
              <CardContent className="flex items-center justify-between p-4">
                <div className="flex items-center gap-4">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800">
                    <Phone className="h-5 w-5 text-slate-400" />
                  </div>
                  <div>
                    <p className="font-medium text-white">
                      {conv.contact_name || conv.contact_phone}
                    </p>
                    <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                      <span>{conv.contact_phone}</span>
                      <span>·</span>
                      <span>{conv.message_count} msgs</span>
                      {conv.actions_executed > 0 && (
                        <>
                          <span>·</span>
                          <span>{conv.actions_executed} ações</span>
                        </>
                      )}
                      {conv.last_message_at && (
                        <>
                          <span>·</span>
                          <span>{new Date(conv.last_message_at).toLocaleString("pt-BR")}</span>
                        </>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Badge className={statusColor(conv.status)}>{statusLabel(conv.status)}</Badge>
                  <Button variant="ghost" size="icon" onClick={() => archiveConversation(conv.id)} className="text-slate-400 hover:text-white" title="Arquivar">
                    <Archive className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="icon" onClick={() => blockConversation(conv.id)} className="text-orange-400 hover:text-orange-300" title="Bloquear">
                    <Ban className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="icon" onClick={() => deleteConversation(conv.id)} className="text-red-400 hover:text-red-300" title="Excluir">
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
