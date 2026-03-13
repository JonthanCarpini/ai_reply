"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Conversation } from "@/lib/types";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { MessageSquare, Archive, Ban, Trash2, Phone } from "lucide-react";
import { toast } from "sonner";

const stageLabels: Record<string, string> = {
  new_contact: "Novo contato",
  qualification: "Qualificação",
  app_recommendation: "Recomendação de app",
  trial_request: "Solicitação de teste",
  test_created: "Teste criado",
  customer_lookup: "Consulta cliente",
  payment_or_renewal: "Pagamento/Renovação",
  renewal_completed: "Renovação concluída",
  plan_presentation: "Apresentação de planos",
  support: "Suporte",
  human_handoff: "Encaminhado ao humano",
};

const journeyStatusLabels: Record<string, string> = {
  open: "Em andamento",
  fulfilled: "Concluído",
  awaiting_retry: "Aguardando nova tentativa",
  handoff_pending: "Aguardando humano",
};

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

  const journeyStatusColor = (status: string) => {
    switch (status) {
      case "fulfilled": return "bg-emerald-600";
      case "awaiting_retry": return "bg-amber-600";
      case "handoff_pending": return "bg-fuchsia-700";
      default: return "bg-sky-700";
    }
  };

  const formatStageLabel = (stage: string) => stageLabels[stage] || stage;

  const formatJourneyStatusLabel = (status: string) => journeyStatusLabels[status] || status;

  const normalizeCollectedData = (conversation: Conversation): Array<[string, unknown]> => {
    const entries = Object.entries(conversation.collected_data ?? {});

    return entries.filter(([, value]) => value !== null && value !== "");
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
          {conversations.map((conv: Conversation) => (
            <Card key={conv.id} className="border-slate-800 bg-slate-900 transition-colors hover:border-slate-700">
              <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 items-start gap-4">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-800">
                    <Phone className="h-5 w-5 text-slate-400" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-white">
                      {conv.contact_name || conv.contact_phone}
                    </p>
                    <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
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

                    <div className="mt-3 flex flex-wrap gap-2">
                      <Badge className={statusColor(conv.status)}>{statusLabel(conv.status)}</Badge>
                      <Badge className="bg-indigo-700">{formatStageLabel(conv.journey_stage)}</Badge>
                      <Badge className={journeyStatusColor(conv.journey_status)}>
                        {formatJourneyStatusLabel(conv.journey_status)}
                      </Badge>
                      {conv.human_handoff_requested && (
                        <Badge className="bg-fuchsia-700">Handoff humano</Badge>
                      )}
                      {conv.last_tool_name && (
                        <Badge className="bg-slate-700 text-slate-100">
                          Tool: {conv.last_tool_name}
                          {conv.last_tool_status ? ` (${conv.last_tool_status})` : ""}
                        </Badge>
                      )}
                    </div>

                    {(conv.pending_requirements?.length || normalizeCollectedData(conv).length) ? (
                      <div className="mt-3 grid gap-3 md:grid-cols-2">
                        {conv.pending_requirements && conv.pending_requirements.length > 0 && (
                          <div className="rounded-lg border border-amber-900/60 bg-amber-950/30 p-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-300">
                              Pendências
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                              {conv.pending_requirements.map((item: string) => (
                                <Badge key={item} variant="outline" className="border-amber-700 text-amber-200">
                                  {item}
                                </Badge>
                              ))}
                            </div>
                          </div>
                        )}

                        {normalizeCollectedData(conv).length > 0 && (
                          <div className="rounded-lg border border-slate-700 bg-slate-950/40 p-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-300">
                              Dados coletados
                            </p>
                            <div className="mt-2 space-y-1 text-xs text-slate-300">
                              {normalizeCollectedData(conv).map(([key, value]: [string, unknown]) => (
                                <p key={key}>
                                  <span className="text-slate-500">{key}:</span>{" "}
                                  <span>{String(value)}</span>
                                </p>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    ) : null}
                  </div>
                </div>
                <div className="flex items-center gap-2 self-end lg:self-center">
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
