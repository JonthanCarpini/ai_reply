"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { MessageSquare, Search, User, Bot, ChevronLeft, ChevronRight } from "lucide-react";
import { toast } from "sonner";

interface ConversationItem {
  id: number;
  contact_name: string | null;
  contact_phone: string;
  status: string;
  message_count: number;
  messages_count: number;
  last_message_at: string | null;
  user: { id: number; name: string; email: string } | null;
}

interface MessageItem {
  id: number;
  role: string;
  content: string;
  action_type: string | null;
  action_success: boolean | null;
  created_at: string;
}

export default function ConversationsPage() {
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedConv, setSelectedConv] = useState<ConversationItem | null>(null);
  const [messages, setMessages] = useState<MessageItem[]>([]);
  const [messagesLoading, setMessagesLoading] = useState(false);

  const fetchConversations = async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, per_page: 20 };
      if (search) params.search = search;
      if (statusFilter !== "all") params.status = statusFilter;

      const res = await api.get("/admin/conversations", { params });
      setConversations(res.data.data);
      setTotalPages(res.data.last_page || 1);
    } catch {
      toast.error("Erro ao carregar conversas.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchConversations(); }, [page, statusFilter]);

  const handleSearch = () => {
    setPage(1);
    fetchConversations();
  };

  const openMessages = async (conv: ConversationItem) => {
    setSelectedConv(conv);
    setMessagesLoading(true);
    try {
      const res = await api.get(`/admin/conversations/${conv.id}/messages`);
      setMessages(res.data.data);
    } catch {
      toast.error("Erro ao carregar mensagens.");
    } finally {
      setMessagesLoading(false);
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case "active": return "bg-green-600";
      case "archived": return "bg-gray-500";
      case "blocked": return "bg-red-600";
      default: return "bg-gray-500";
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Conversas</h1>
        <p className="text-muted-foreground">Visualize todas as conversas dos agentes dos usuários</p>
      </div>

      <div className="flex gap-4">
        <div className="flex flex-1 gap-2">
          <Input
            placeholder="Buscar por contato, telefone, usuário..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && handleSearch()}
          />
          <Button variant="outline" onClick={handleSearch}>
            <Search className="h-4 w-4" />
          </Button>
        </div>
        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem value="active">Ativo</SelectItem>
            <SelectItem value="archived">Arquivado</SelectItem>
            <SelectItem value="blocked">Bloqueado</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {loading ? (
        <div className="flex items-center justify-center p-8">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" />
        </div>
      ) : (
        <div className="space-y-2">
          {conversations.map((conv) => (
            <Card
              key={conv.id}
              className="cursor-pointer hover:bg-muted/50 transition-colors"
              onClick={() => openMessages(conv)}
            >
              <CardContent className="flex items-center justify-between py-3 px-4">
                <div className="flex items-center gap-3">
                  <MessageSquare className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="font-medium text-sm">
                      {conv.contact_name || conv.contact_phone}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {conv.contact_phone} · Agente: {conv.user?.name || "N/A"} ({conv.user?.email || ""})
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Badge variant="outline">{conv.messages_count} msgs</Badge>
                  <Badge className={statusColor(conv.status)}>{conv.status}</Badge>
                  {conv.last_message_at && (
                    <span className="text-xs text-muted-foreground">
                      {new Date(conv.last_message_at).toLocaleDateString("pt-BR")}
                    </span>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}

          {conversations.length === 0 && (
            <div className="text-center py-10 text-muted-foreground">
              Nenhuma conversa encontrada.
            </div>
          )}

          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-4 pt-4">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <span className="text-sm text-muted-foreground">
                Página {page} de {totalPages}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => setPage(page + 1)}
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          )}
        </div>
      )}

      <Dialog open={!!selectedConv} onOpenChange={(open) => !open && setSelectedConv(null)}>
        <DialogContent className="sm:max-w-2xl max-h-[80vh] flex flex-col">
          <DialogHeader>
            <DialogTitle>
              Conversa: {selectedConv?.contact_name || selectedConv?.contact_phone}
              <span className="text-sm font-normal text-muted-foreground ml-2">
                (Agente: {selectedConv?.user?.name})
              </span>
            </DialogTitle>
          </DialogHeader>
          <div className="flex-1 overflow-y-auto space-y-3 py-4">
            {messagesLoading ? (
              <div className="flex items-center justify-center p-8">
                <div className="animate-spin h-6 w-6 border-4 border-primary border-t-transparent rounded-full" />
              </div>
            ) : (
              messages.map((msg) => (
                <div
                  key={msg.id}
                  className={`flex gap-2 ${msg.role === "user" ? "" : "flex-row-reverse"}`}
                >
                  <div className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${
                    msg.role === "user" ? "bg-blue-600" : "bg-green-600"
                  }`}>
                    {msg.role === "user" ? (
                      <User className="h-3.5 w-3.5 text-white" />
                    ) : (
                      <Bot className="h-3.5 w-3.5 text-white" />
                    )}
                  </div>
                  <div className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                    msg.role === "user"
                      ? "bg-blue-600/10 text-foreground"
                      : "bg-green-600/10 text-foreground"
                  }`}>
                    <p className="whitespace-pre-wrap">{msg.content}</p>
                    {msg.action_type && (
                      <Badge variant="outline" className="mt-1 text-[10px]">
                        {msg.action_type} {msg.action_success ? "✓" : "✗"}
                      </Badge>
                    )}
                    <p className="text-[10px] text-muted-foreground mt-1">
                      {new Date(msg.created_at).toLocaleString("pt-BR")}
                    </p>
                  </div>
                </div>
              ))
            )}
            {!messagesLoading && messages.length === 0 && (
              <p className="text-center text-muted-foreground">Nenhuma mensagem.</p>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
