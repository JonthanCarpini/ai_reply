"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import api from "@/lib/api";
import type { Conversation, Message } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ArrowLeft, Bot, User, Zap } from "lucide-react";

export default function ConversationDetailPage() {
  const params = useParams();
  const router = useRouter();
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const [convRes, msgsRes] = await Promise.all([
          api.get(`/conversations/${params.id}`),
          api.get(`/conversations/${params.id}/messages`),
        ]);
        setConversation(convRes.data.data);
        setMessages(msgsRes.data.data || []);
      } catch { /* empty */ } finally { setLoading(false); }
    }
    load();
  }, [params.id]);

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;
  if (!conversation) return <p className="text-slate-400">Conversa não encontrada.</p>;

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col">
      <div className="flex items-center gap-3 border-b border-slate-800 pb-4">
        <Button variant="ghost" size="icon" onClick={() => router.push("/conversations")} className="text-slate-400">
          <ArrowLeft className="h-5 w-5" />
        </Button>
        <div>
          <h1 className="text-lg font-bold text-white">{conversation.contact_name || conversation.contact_phone}</h1>
          <p className="text-xs text-slate-500">{conversation.contact_phone} · {conversation.message_count} msgs · {conversation.actions_executed} ações</p>
        </div>
        <Badge className={conversation.status === "active" ? "ml-auto bg-green-600" : "ml-auto bg-slate-600"}>
          {conversation.status === "active" ? "Ativa" : conversation.status === "blocked" ? "Bloqueado" : "Arquivada"}
        </Badge>
      </div>

      <div className="flex-1 space-y-3 overflow-y-auto py-4">
        {messages.map((msg) => (
          <div key={msg.id} className={`flex ${msg.role === "user" ? "justify-end" : "justify-start"}`}>
            <div className={`max-w-[75%] rounded-2xl px-4 py-2.5 ${
              msg.role === "user"
                ? "bg-indigo-600 text-white"
                : "bg-slate-800 text-slate-200"
            }`}>
              <div className="mb-1 flex items-center gap-1.5">
                {msg.role === "user" ? <User className="h-3 w-3" /> : <Bot className="h-3 w-3" />}
                <span className="text-[10px] font-medium opacity-70">
                  {msg.role === "user" ? "Cliente" : "IA"}
                  {msg.ai_model ? ` (${msg.ai_model})` : ""}
                </span>
              </div>
              <p className="whitespace-pre-wrap text-sm">{msg.content}</p>
              {msg.action_type && (
                <div className="mt-2 flex items-center gap-1 rounded bg-black/20 px-2 py-1 text-[10px]">
                  <Zap className="h-3 w-3 text-amber-400" />
                  <span>{msg.action_type}</span>
                  {msg.action_success !== null && (
                    <Badge className={`ml-1 text-[9px] ${msg.action_success ? "bg-green-600" : "bg-red-600"}`}>
                      {msg.action_success ? "OK" : "Erro"}
                    </Badge>
                  )}
                </div>
              )}
              <p className="mt-1 text-right text-[10px] opacity-50">
                {new Date(msg.created_at).toLocaleString("pt-BR")}
                {msg.latency_ms > 0 && ` · ${msg.latency_ms}ms`}
              </p>
            </div>
          </div>
        ))}
        {messages.length === 0 && (
          <p className="text-center text-sm text-slate-500">Nenhuma mensagem nesta conversa.</p>
        )}
      </div>
    </div>
  );
}
