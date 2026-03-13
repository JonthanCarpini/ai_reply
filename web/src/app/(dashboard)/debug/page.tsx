"use client";

import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RefreshCw, Bug, ArrowDown, ArrowUp, Filter } from "lucide-react";

interface LogEntry {
  timestamp: string;
  level: string;
  type: string;
  data: Record<string, unknown> | string;
}

type LogDataObject = Record<string, unknown>;

const TYPE_COLORS: Record<string, string> = {
  APP_NOTIF: "bg-blue-500/20 text-blue-400 border-blue-500/30",
  PROCESS_REQUEST: "bg-yellow-500/20 text-yellow-400 border-yellow-500/30",
  PROCESS_REPLY: "bg-green-500/20 text-green-400 border-green-500/30",
  ORCHESTRATION_PLAN: "bg-indigo-500/20 text-indigo-400 border-indigo-500/30",
  TOOL_STEP_START: "bg-cyan-500/20 text-cyan-400 border-cyan-500/30",
  TOOL_STEP_RESULT: "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
  TOOL_BLOCKED: "bg-rose-500/20 text-rose-400 border-rose-500/30",
  TOOL_LOOP_LIMIT_REACHED: "bg-orange-500/20 text-orange-400 border-orange-500/30",
  SKIP_FROM_ME: "bg-purple-500/20 text-purple-400 border-purple-500/30",
  SKIP_ECHO: "bg-red-500/20 text-red-400 border-red-500/30",
  ECHO_MATCH_EXACT: "bg-red-500/20 text-red-400 border-red-500/30",
};

function getLogDataObject(data: LogEntry["data"]): LogDataObject | null {
  if (typeof data !== "object" || data === null || Array.isArray(data)) {
    return null;
  }

  return data as LogDataObject;
}

function getStringValue(data: LogDataObject | null, key: string): string | null {
  const value = data?.[key];
  return typeof value === "string" && value.length > 0 ? value : null;
}

function getNumberValue(data: LogDataObject | null, key: string): number | null {
  const value = data?.[key];
  return typeof value === "number" ? value : null;
}

export default function DebugPage() {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [filterType, setFilterType] = useState<string>("ALL");
  const [expanded, setExpanded] = useState<Set<number>>(new Set());

  const fetchLogs = useCallback(async () => {
    try {
      const res = await api.get("/messages/debug-logs?limit=200");
      setLogs(res.data.logs || []);
    } catch {
      // silently fail
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  useEffect(() => {
    if (!autoRefresh) return;
    const interval = setInterval(fetchLogs, 5000);
    return () => clearInterval(interval);
  }, [autoRefresh, fetchLogs]);

  const toggleExpand = (index: number) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(index)) next.delete(index);
      else next.add(index);
      return next;
    });
  };

  const filteredLogs =
    filterType === "ALL"
      ? logs
      : logs.filter((l: LogEntry) => l.type === filterType);

  const uniqueTypes = Array.from(new Set(logs.map((l: LogEntry) => l.type)));

  const stats = {
    total: logs.length,
    notifications: logs.filter((l: LogEntry) => l.type === "APP_NOTIF").length,
    processed: logs.filter((l: LogEntry) => l.type === "PROCESS_REQUEST").length,
    replies: logs.filter((l: LogEntry) => l.type === "PROCESS_REPLY").length,
    orchestrations: logs.filter((l: LogEntry) => l.type === "ORCHESTRATION_PLAN").length,
    toolSteps: logs.filter((l: LogEntry) => l.type === "TOOL_STEP_START" || l.type === "TOOL_STEP_RESULT").length,
    blockedTools: logs.filter((l: LogEntry) => l.type === "TOOL_BLOCKED" || l.type === "TOOL_LOOP_LIMIT_REACHED").length,
    skipped: logs.filter((l: LogEntry) => l.type.startsWith("SKIP_")).length,
    echos: logs.filter(
      (l: LogEntry) => l.type === "SKIP_ECHO" || l.type === "ECHO_MATCH_EXACT"
    ).length,
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-2">
            <Bug className="h-6 w-6 text-indigo-400" />
            Debug Logs
          </h1>
          <p className="text-sm text-slate-400 mt-1">
            Logs em tempo real do app Android e processamento de mensagens
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant={autoRefresh ? "default" : "outline"}
            size="sm"
            onClick={() => setAutoRefresh(!autoRefresh)}
            className={autoRefresh ? "bg-green-600 hover:bg-green-700" : ""}
          >
            <RefreshCw
              className={`h-4 w-4 mr-1 ${autoRefresh ? "animate-spin" : ""}`}
            />
            {autoRefresh ? "Auto: ON" : "Auto: OFF"}
          </Button>
          <Button variant="outline" size="sm" onClick={fetchLogs}>
            <RefreshCw className="h-4 w-4 mr-1" />
            Atualizar
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-white">{stats.total}</p>
            <p className="text-xs text-slate-400">Total</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-blue-400">
              {stats.notifications}
            </p>
            <p className="text-xs text-slate-400">Notificações</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-yellow-400">
              {stats.processed}
            </p>
            <p className="text-xs text-slate-400">Processadas</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-green-400">
              {stats.replies}
            </p>
            <p className="text-xs text-slate-400">Respostas</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-indigo-400">{stats.orchestrations}</p>
            <p className="text-xs text-slate-400">Planos</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-cyan-400">{stats.toolSteps}</p>
            <p className="text-xs text-slate-400">Steps Tool</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-rose-400">{stats.blockedTools}</p>
            <p className="text-xs text-slate-400">Bloqueios</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-purple-400">
              {stats.skipped}
            </p>
            <p className="text-xs text-slate-400">Ignoradas</p>
          </CardContent>
        </Card>
        <Card className="bg-slate-900 border-slate-800">
          <CardContent className="p-3 text-center">
            <p className="text-2xl font-bold text-red-400">{stats.echos}</p>
            <p className="text-xs text-slate-400">Ecos Detectados</p>
          </CardContent>
        </Card>
      </div>

      <div className="flex gap-2 flex-wrap">
        <Button
          variant={filterType === "ALL" ? "default" : "outline"}
          size="sm"
          onClick={() => setFilterType("ALL")}
        >
          <Filter className="h-3 w-3 mr-1" />
          Todos
        </Button>
        {uniqueTypes.map((type) => (
          <Button
            key={type}
            variant={filterType === type ? "default" : "outline"}
            size="sm"
            onClick={() => setFilterType(type)}
          >
            {type}
          </Button>
        ))}
      </div>

      <Card className="bg-slate-900 border-slate-800">
        <CardHeader className="pb-2">
          <CardTitle className="text-white text-sm">
            {filteredLogs.length} entradas
            {filterType !== "ALL" && ` (filtro: ${filterType})`}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {loading ? (
            <div className="p-8 text-center text-slate-400">Carregando...</div>
          ) : filteredLogs.length === 0 ? (
            <div className="p-8 text-center text-slate-400">
              Nenhum log encontrado. Envie uma mensagem no WhatsApp para gerar
              logs.
            </div>
          ) : (
            <div className="divide-y divide-slate-800 max-h-[600px] overflow-y-auto">
              {[...filteredLogs].reverse().map((log, i) => {
                const colorClass =
                  TYPE_COLORS[log.type] ||
                  "bg-slate-500/20 text-slate-400 border-slate-500/30";
                const data =
                  typeof log.data === "string"
                    ? log.data
                    : JSON.stringify(log.data, null, 2);
                const isExpanded = expanded.has(i);
                const dataObj = getLogDataObject(log.data);
                const correlationId = getStringValue(dataObj, "correlation_id");
                const resolvedPhase = getStringValue(dataObj, "resolved_phase") || getStringValue(dataObj, "journey_stage");
                const toolName = getStringValue(dataObj, "tool") || getStringValue(dataObj, "action_type") || getStringValue(dataObj, "remaining_tool");
                const step = getNumberValue(dataObj, "step") || getNumberValue(dataObj, "steps_executed");

                return (
                  <div
                    key={i}
                    className="px-4 py-2 hover:bg-slate-800/50 cursor-pointer transition-colors"
                    onClick={() => toggleExpand(i)}
                  >
                    <div className="flex items-center gap-3">
                      <span className="text-xs text-slate-500 font-mono whitespace-nowrap">
                        {log.timestamp}
                      </span>
                      <Badge
                        variant="outline"
                        className={`text-[10px] px-1.5 py-0 ${colorClass}`}
                      >
                        {log.type}
                      </Badge>
                      {correlationId && (
                        <Badge variant="outline" className="border-indigo-500/30 text-indigo-300 text-[10px] px-1.5 py-0 font-mono">
                          {correlationId.slice(0, 18)}
                        </Badge>
                      )}
                      {resolvedPhase && (
                        <Badge variant="outline" className="border-cyan-500/30 text-cyan-300 text-[10px] px-1.5 py-0">
                          fase: {resolvedPhase}
                        </Badge>
                      )}
                      {toolName && (
                        <Badge variant="outline" className="border-emerald-500/30 text-emerald-300 text-[10px] px-1.5 py-0">
                          {toolName}
                        </Badge>
                      )}
                      {step !== null && (
                        <Badge variant="outline" className="border-amber-500/30 text-amber-300 text-[10px] px-1.5 py-0">
                          step {step}
                        </Badge>
                      )}
                      {dataObj && (
                        <>
                          {dataObj.contact && (
                            <span className="text-xs text-slate-300 font-medium">
                              {String(dataObj.contact)}
                            </span>
                          )}
                          {dataObj.from_me !== undefined && (
                            <Badge
                              variant="outline"
                              className={`text-[10px] px-1 py-0 ${
                                dataObj.from_me
                                  ? "bg-orange-500/20 text-orange-400 border-orange-500/30"
                                  : "bg-cyan-500/20 text-cyan-400 border-cyan-500/30"
                              }`}
                            >
                              {dataObj.from_me ? "FROM_ME" : "FROM_CONTACT"}
                            </Badge>
                          )}
                          {(dataObj.message || dataObj.text || dataObj.reply) && (
                            <span className="text-xs text-slate-400 truncate max-w-[300px]">
                              {String(
                                dataObj.message ||
                                  dataObj.text ||
                                  dataObj.reply ||
                                  ""
                              ).slice(0, 80)}
                            </span>
                          )}
                        </>
                      )}
                      <span className="ml-auto">
                        {isExpanded ? (
                          <ArrowUp className="h-3 w-3 text-slate-500" />
                        ) : (
                          <ArrowDown className="h-3 w-3 text-slate-500" />
                        )}
                      </span>
                    </div>
                    {isExpanded && (
                      <pre className="mt-2 text-xs text-slate-400 bg-slate-950 p-3 rounded overflow-x-auto font-mono">
                        {data}
                      </pre>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
