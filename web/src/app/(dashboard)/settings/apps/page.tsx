"use client";

import { useEffect, useState, type ChangeEvent, type FormEvent } from "react";
import api from "@/lib/api";
import type { DeviceApp, DeviceTypeInfo } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Smartphone, Loader2, Plus, Pencil, Trash2, Tv, Monitor, Sparkles } from "lucide-react";
import { toast } from "sonner";

type DeviceAppForm = {
  device_type: string;
  app_name: string;
  app_code: string;
  app_url: string;
  ntdown: string;
  downloader: string;
  download_instructions: string;
  setup_instructions: string;
  agent_instructions: string;
  is_active: boolean;
  priority: number;
};

const initialForm: DeviceAppForm = {
  device_type: "",
  app_name: "",
  app_code: "",
  app_url: "",
  ntdown: "",
  downloader: "",
  download_instructions: "",
  setup_instructions: "",
  agent_instructions: "",
  is_active: true,
  priority: 0,
};

export default function DeviceAppsPage() {
  const [apps, setApps] = useState<DeviceApp[]>([]);
  const [deviceTypes, setDeviceTypes] = useState<DeviceTypeInfo>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [filterDevice, setFilterDevice] = useState<string>("");
  const [generatingInstructions, setGeneratingInstructions] = useState<string | null>(null);
  const [form, setForm] = useState<DeviceAppForm>(initialForm);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      const [appsRes, typesRes] = await Promise.all([
        api.get("/device-apps"),
        api.get("/device-apps/types"),
      ]);
      setApps(appsRes.data.data);
      setDeviceTypes(typesRes.data.data);
    } catch {
      toast.error("Erro ao carregar dados.");
    } finally {
      setLoading(false);
    }
  }

  function resetForm() {
    setForm(initialForm);
    setEditingId(null);
    setShowForm(false);
  }

  function handleEdit(app: DeviceApp) {
    setForm({
      device_type: app.device_type,
      app_name: app.app_name,
      app_code: app.app_code || "",
      app_url: app.app_url || "",
      ntdown: app.ntdown || "",
      downloader: app.downloader || "",
      download_instructions: app.download_instructions || "",
      setup_instructions: app.setup_instructions || "",
      agent_instructions: app.agent_instructions || "",
      is_active: app.is_active,
      priority: app.priority,
    });
    setEditingId(app.id);
    setShowForm(true);
  }

  function updateFormField<K extends keyof DeviceAppForm>(field: K, value: DeviceAppForm[K]) {
    setForm((current: DeviceAppForm) => ({ ...current, [field]: value }));
  }

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSaving(true);
    try {
      if (editingId) {
        await api.put(`/device-apps/${editingId}`, form);
        toast.success("Aplicativo atualizado!");
      } else {
        await api.post("/device-apps", form);
        toast.success("Aplicativo cadastrado!");
      }
      resetForm();
      loadData();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao salvar.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm("Deseja realmente remover este aplicativo?")) return;
    try {
      await api.delete(`/device-apps/${id}`);
      toast.success("Aplicativo removido.");
      loadData();
    } catch {
      toast.error("Erro ao remover.");
    }
  }

  async function generateInstructions(type: "download" | "setup" | "agent") {
    if (!form.app_name || !form.device_type) {
      toast.error("Preencha o nome do app e tipo de dispositivo primeiro.");
      return;
    }

    setGeneratingInstructions(type);
    try {
      const response = await api.post("/device-apps/generate-instructions", {
        app_name: form.app_name,
        device_type: form.device_type,
        app_code: form.app_code,
        app_url: form.app_url,
        instruction_type: type,
      });

      const instructions = response.data.instructions;

      if (type === "download") {
        updateFormField("download_instructions", instructions);
      } else if (type === "setup") {
        updateFormField("setup_instructions", instructions);
      } else if (type === "agent") {
        updateFormField("agent_instructions", instructions);
      }

      toast.success("Instruções geradas com sucesso!");
    } catch (error: unknown) {
      const typedError = error as { response?: { data?: { error?: string; message?: string } } };
      console.error("Erro ao gerar instruções:", error);
      const errorMsg = typedError.response?.data?.error || typedError.response?.data?.message || "Erro ao gerar instruções.";
      toast.error(errorMsg);
    } finally {
      setGeneratingInstructions(null);
    }
  }

  const filteredApps = filterDevice ? apps.filter((app: DeviceApp) => app.device_type === filterDevice) : apps;

  const groupedApps = filteredApps.reduce<Record<string, DeviceApp[]>>((acc, app: DeviceApp) => {
    if (!acc[app.device_type]) acc[app.device_type] = [];
    acc[app.device_type].push(app);
    return acc;
  }, {});
  const groupedAppEntries = Object.entries(groupedApps) as [string, DeviceApp[]][];

  const getDeviceIcon = (type: string) => {
    if (type.includes("tv")) return <Tv className="h-4 w-4" />;
    if (type.includes("phone") || type.includes("iphone")) return <Smartphone className="h-4 w-4" />;
    return <Monitor className="h-4 w-4" />;
  };

  const getPreviewText = (value?: string | null, maxLength = 140) => {
    if (!value) return "";
    const normalized = value.replace(/\s+/g, " ").trim();
    if (normalized.length <= maxLength) return normalized;
    return `${normalized.slice(0, maxLength)}...`;
  };

  const getConfigItems = (app: DeviceApp) => {
    return [
      app.app_code ? { label: "Código", value: app.app_code } : null,
      app.ntdown ? { label: "ntdown", value: app.ntdown } : null,
      app.downloader ? { label: "Downloader", value: app.downloader } : null,
    ].filter(Boolean) as { label: string; value: string }[];
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Aplicativos por Dispositivo</h1>
          <p className="text-sm text-slate-400 mt-1">
            {filterDevice ? `Filtrando: ${deviceTypes[filterDevice] || filterDevice}` : `${apps.length} aplicativos cadastrados`}
          </p>
        </div>
        <Button onClick={() => setShowForm(!showForm)} className="bg-indigo-600 hover:bg-indigo-700">
          <Plus className="mr-2 h-4 w-4" />
          {showForm ? "Cancelar" : "Adicionar App"}
        </Button>
      </div>

      <Card className="border-slate-800 bg-slate-900">
        <CardContent className="pt-6">
          <div className="flex items-center gap-4">
            <Label className="text-slate-300 whitespace-nowrap">Filtrar por dispositivo:</Label>
            <select
              value={filterDevice}
              onChange={(e: ChangeEvent<HTMLSelectElement>) => setFilterDevice(e.target.value)}
              className="flex-1 rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-white"
            >
              <option value="">Todos os dispositivos</option>
              {Object.entries(deviceTypes).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
            {filterDevice && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setFilterDevice("")}
                className="border-slate-700 text-slate-300 hover:bg-slate-800"
              >
                Limpar filtro
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {showForm && (
        <Card className="border-slate-800 bg-slate-900">
          <CardHeader>
            <CardTitle className="text-white">
              {editingId ? "Editar Aplicativo" : "Novo Aplicativo"}
            </CardTitle>
            <CardDescription className="text-slate-400">
              Configure os aplicativos que a IA deve recomendar para cada tipo de dispositivo.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label className="text-slate-300">Tipo de Dispositivo</Label>
                  <select
                    value={form.device_type}
                    onChange={(e: ChangeEvent<HTMLSelectElement>) => updateFormField("device_type", e.target.value)}
                    required
                    className="w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-white"
                  >
                    <option value="">Selecione...</option>
                    {Object.entries(deviceTypes).map(([key, label]) => (
                      <option key={key} value={key}>{label}</option>
                    ))}
                  </select>
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Nome do Aplicativo</Label>
                  <Input
                    placeholder="Ex: XCIPTV Player"
                    value={form.app_name}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("app_name", e.target.value)}
                    required
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-2">
                  <Label className="text-slate-300">Código App (opcional)</Label>
                  <Input
                    placeholder="Ex: com.xciptv.player"
                    value={form.app_code}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("app_code", e.target.value)}
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">ntdown (opcional)</Label>
                  <Input
                    placeholder="Ex: ntdown_code"
                    value={form.ntdown}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("ntdown", e.target.value)}
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Downloader (opcional)</Label>
                  <Input
                    placeholder="Ex: downloader_url"
                    value={form.downloader}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("downloader", e.target.value)}
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label className="text-slate-300">URL do App (opcional)</Label>
                <Input
                  type="url"
                  placeholder="https://..."
                  value={form.app_url}
                  onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("app_url", e.target.value)}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-slate-300">Instruções de Download (opcional)</Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => generateInstructions('download')}
                    disabled={generatingInstructions === 'download' || !form.app_name || !form.device_type}
                    className="border-indigo-600 text-indigo-400 hover:bg-indigo-600/10"
                  >
                    {generatingInstructions === 'download' ? (
                      <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                    ) : (
                      <Sparkles className="mr-2 h-3 w-3" />
                    )}
                    Gerar com IA
                  </Button>
                </div>
                <Textarea
                  placeholder="Como baixar o aplicativo..."
                  value={form.download_instructions}
                  onChange={(e: ChangeEvent<HTMLTextAreaElement>) => updateFormField("download_instructions", e.target.value)}
                  rows={3}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-slate-300">Instruções de Configuração (opcional)</Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => generateInstructions('setup')}
                    disabled={generatingInstructions === 'setup' || !form.app_name || !form.device_type}
                    className="border-indigo-600 text-indigo-400 hover:bg-indigo-600/10"
                  >
                    {generatingInstructions === 'setup' ? (
                      <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                    ) : (
                      <Sparkles className="mr-2 h-3 w-3" />
                    )}
                    Gerar com IA
                  </Button>
                </div>
                <Textarea
                  placeholder="Como configurar o aplicativo..."
                  value={form.setup_instructions}
                  onChange={(e: ChangeEvent<HTMLTextAreaElement>) => updateFormField("setup_instructions", e.target.value)}
                  rows={3}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-slate-300">Instruções para o Agente (opcional)</Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => generateInstructions('agent')}
                    disabled={generatingInstructions === 'agent' || !form.app_name || !form.device_type}
                    className="border-indigo-600 text-indigo-400 hover:bg-indigo-600/10"
                  >
                    {generatingInstructions === 'agent' ? (
                      <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                    ) : (
                      <Sparkles className="mr-2 h-3 w-3" />
                    )}
                    Gerar com IA
                  </Button>
                </div>
                <Textarea
                  placeholder="Orientações e instruções específicas para o agente de IA ao recomendar este aplicativo..."
                  value={form.agent_instructions}
                  onChange={(e: ChangeEvent<HTMLTextAreaElement>) => updateFormField("agent_instructions", e.target.value)}
                  rows={3}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
                <p className="text-xs text-slate-500">Informações adicionais que o agente deve considerar ao recomendar este app</p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label className="text-slate-300">Prioridade (0-100)</Label>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    value={form.priority}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("priority", Number.parseInt(e.target.value, 10) || 0)}
                    className="border-slate-700 bg-slate-800 text-white"
                  />
                  <p className="text-xs text-slate-500">Apps com maior prioridade aparecem primeiro</p>
                </div>
                <div className="flex items-center space-x-2 pt-6">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={form.is_active}
                    onChange={(e: ChangeEvent<HTMLInputElement>) => updateFormField("is_active", e.target.checked)}
                    className="h-4 w-4 rounded border-slate-700 bg-slate-800"
                  />
                  <Label htmlFor="is_active" className="text-slate-300 cursor-pointer">
                    Ativo (visível para a IA)
                  </Label>
                </div>
              </div>

              <div className="flex gap-3">
                <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700" disabled={saving}>
                  {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  {editingId ? "Atualizar" : "Salvar"}
                </Button>
                <Button type="button" variant="outline" onClick={resetForm} className="border-slate-700 text-slate-300 hover:bg-slate-800">
                  Cancelar
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-indigo-400" />
        </div>
      ) : groupedAppEntries.length === 0 ? (
        <Card className="border-slate-800 bg-slate-900">
          <CardContent className="py-12 text-center">
            <Smartphone className="mx-auto h-12 w-12 text-slate-600 mb-4" />
            <p className="text-slate-400">Nenhum aplicativo cadastrado ainda.</p>
            <p className="text-sm text-slate-500 mt-2">Clique em "Adicionar App" para começar.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {groupedAppEntries.map(([deviceType, deviceApps]) => (
            <Card key={deviceType} className="border-slate-800 bg-slate-900">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-white">
                  {getDeviceIcon(deviceType)}
                  {deviceTypes[deviceType] || deviceType}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {deviceApps.map((app) => (
                    <Card key={app.id} className="border-slate-800 bg-slate-950/60">
                      <CardHeader className="space-y-3 pb-4">
                        <div className="flex items-start justify-between gap-3">
                          <div className="space-y-2">
                            <CardTitle className="text-base text-white">{app.app_name}</CardTitle>
                            <div className="flex flex-wrap gap-2">
                              <Badge variant={app.is_active ? "default" : "secondary"} className={app.is_active ? "bg-emerald-600" : ""}>
                                {app.is_active ? "Ativo" : "Inativo"}
                              </Badge>
                              {app.priority > 0 && <Badge className="bg-indigo-600 text-xs">Prioridade {app.priority}</Badge>}
                              {app.app_url && <Badge variant="outline">Com link</Badge>}
                            </div>
                          </div>
                          <div className="flex gap-1">
                            <Button variant="ghost" size="icon" onClick={() => handleEdit(app)} className="text-indigo-400 hover:text-indigo-300">
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" onClick={() => handleDelete(app.id)} className="text-red-400 hover:text-red-300">
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        {app.app_url && (
                          <a href={app.app_url} target="_blank" rel="noopener noreferrer" className="block truncate text-sm text-indigo-400 hover:text-indigo-300 hover:underline">
                            {app.app_url}
                          </a>
                        )}

                        {getConfigItems(app).length > 0 && (
                          <div className="space-y-2 rounded-lg border border-slate-800 bg-slate-900/70 p-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Configuração</p>
                            <div className="space-y-1.5 text-sm text-slate-300">
                              {getConfigItems(app).map((item) => (
                                <div key={`${app.id}-${item.label}`} className="flex gap-2">
                                  <span className="min-w-20 text-slate-500">{item.label}:</span>
                                  <span className="truncate">{item.value}</span>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        <div className="space-y-3">
                          {app.download_instructions && (
                            <div className="rounded-lg border border-slate-800 bg-slate-900/70 p-3">
                              <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Download</p>
                              <p className="text-sm text-slate-300">{getPreviewText(app.download_instructions)}</p>
                            </div>
                          )}
                          {app.setup_instructions && (
                            <div className="rounded-lg border border-slate-800 bg-slate-900/70 p-3">
                              <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Configuração</p>
                              <p className="text-sm text-slate-300">{getPreviewText(app.setup_instructions)}</p>
                            </div>
                          )}
                          {app.agent_instructions && (
                            <div className="rounded-lg border border-slate-800 bg-slate-900/70 p-3">
                              <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Agente</p>
                              <p className="text-sm text-slate-300">{getPreviewText(app.agent_instructions)}</p>
                            </div>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
