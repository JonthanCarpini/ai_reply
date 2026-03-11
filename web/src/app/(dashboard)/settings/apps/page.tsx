"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { DeviceApp, DeviceTypeInfo } from "@/lib/types";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Smartphone, Loader2, Plus, Pencil, Trash2, Tv, Monitor } from "lucide-react";
import { toast } from "sonner";

export default function DeviceAppsPage() {
  const [apps, setApps] = useState<DeviceApp[]>([]);
  const [deviceTypes, setDeviceTypes] = useState<DeviceTypeInfo>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
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
  });

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
    setForm({
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
    });
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

  async function handleSubmit(e: React.FormEvent) {
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

  const groupedApps = apps.reduce((acc, app) => {
    if (!acc[app.device_type]) acc[app.device_type] = [];
    acc[app.device_type].push(app);
    return acc;
  }, {} as Record<string, DeviceApp[]>);

  const getDeviceIcon = (type: string) => {
    if (type.includes("tv")) return <Tv className="h-4 w-4" />;
    if (type.includes("phone") || type.includes("iphone")) return <Smartphone className="h-4 w-4" />;
    return <Monitor className="h-4 w-4" />;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Aplicativos por Dispositivo</h1>
        <Button onClick={() => setShowForm(!showForm)} className="bg-indigo-600 hover:bg-indigo-700">
          <Plus className="mr-2 h-4 w-4" />
          {showForm ? "Cancelar" : "Adicionar App"}
        </Button>
      </div>

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
                    onChange={(e) => setForm({ ...form, device_type: e.target.value })}
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
                    onChange={(e) => setForm({ ...form, app_name: e.target.value })}
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
                    onChange={(e) => setForm({ ...form, app_code: e.target.value })}
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">ntdown (opcional)</Label>
                  <Input
                    placeholder="Ex: ntdown_code"
                    value={form.ntdown}
                    onChange={(e) => setForm({ ...form, ntdown: e.target.value })}
                    className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-slate-300">Downloader (opcional)</Label>
                  <Input
                    placeholder="Ex: downloader_url"
                    value={form.downloader}
                    onChange={(e) => setForm({ ...form, downloader: e.target.value })}
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
                  onChange={(e) => setForm({ ...form, app_url: e.target.value })}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <Label className="text-slate-300">Instruções de Download (opcional)</Label>
                <Textarea
                  placeholder="Como baixar o aplicativo..."
                  value={form.download_instructions}
                  onChange={(e) => setForm({ ...form, download_instructions: e.target.value })}
                  rows={3}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <Label className="text-slate-300">Instruções de Configuração (opcional)</Label>
                <Textarea
                  placeholder="Como configurar o aplicativo..."
                  value={form.setup_instructions}
                  onChange={(e) => setForm({ ...form, setup_instructions: e.target.value })}
                  rows={3}
                  className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
                />
              </div>

              <div className="space-y-2">
                <Label className="text-slate-300">Instruções para o Agente (opcional)</Label>
                <Textarea
                  placeholder="Orientações e instruções específicas para o agente de IA ao recomendar este aplicativo..."
                  value={form.agent_instructions}
                  onChange={(e) => setForm({ ...form, agent_instructions: e.target.value })}
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
                    onChange={(e) => setForm({ ...form, priority: parseInt(e.target.value) || 0 })}
                    className="border-slate-700 bg-slate-800 text-white"
                  />
                  <p className="text-xs text-slate-500">Apps com maior prioridade aparecem primeiro</p>
                </div>
                <div className="flex items-center space-x-2 pt-6">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
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
      ) : Object.keys(groupedApps).length === 0 ? (
        <Card className="border-slate-800 bg-slate-900">
          <CardContent className="py-12 text-center">
            <Smartphone className="mx-auto h-12 w-12 text-slate-600 mb-4" />
            <p className="text-slate-400">Nenhum aplicativo cadastrado ainda.</p>
            <p className="text-sm text-slate-500 mt-2">Clique em "Adicionar App" para começar.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {Object.entries(groupedApps).map(([deviceType, deviceApps]) => (
            <Card key={deviceType} className="border-slate-800 bg-slate-900">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-white">
                  {getDeviceIcon(deviceType)}
                  {deviceTypes[deviceType] || deviceType}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {deviceApps.map((app) => (
                    <div key={app.id} className="flex items-start justify-between rounded-lg border border-slate-800 bg-slate-800/50 p-4">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-2">
                          <p className="font-medium text-white">{app.app_name}</p>
                          {!app.is_active && <Badge variant="secondary" className="text-xs">Inativo</Badge>}
                          {app.priority > 0 && <Badge className="bg-indigo-600 text-xs">Prioridade {app.priority}</Badge>}
                        </div>
                        {app.app_url && (
                          <p className="text-sm text-slate-400 mb-2">
                            <a href={app.app_url} target="_blank" rel="noopener noreferrer" className="hover:text-indigo-400 underline">
                              {app.app_url}
                            </a>
                          </p>
                        )}
                        {(app.app_code || app.ntdown || app.downloader) && (
                          <div className="flex gap-4 text-xs text-slate-400 mb-2">
                            {app.app_code && (
                              <span>
                                <span className="font-medium text-slate-300">Código:</span> {app.app_code}
                              </span>
                            )}
                            {app.ntdown && (
                              <span>
                                <span className="font-medium text-slate-300">ntdown:</span> {app.ntdown}
                              </span>
                            )}
                            {app.downloader && (
                              <span>
                                <span className="font-medium text-slate-300">Downloader:</span> {app.downloader}
                              </span>
                            )}
                          </div>
                        )}
                        {app.download_instructions && (
                          <div className="text-sm text-slate-400 mb-2">
                            <p className="font-medium text-slate-300">Download:</p>
                            <p className="whitespace-pre-wrap">{app.download_instructions}</p>
                          </div>
                        )}
                        {app.setup_instructions && (
                          <div className="text-sm text-slate-400">
                            <p className="font-medium text-slate-300">Configuração:</p>
                            <p className="whitespace-pre-wrap">{app.setup_instructions}</p>
                          </div>
                        )}
                      </div>
                      <div className="flex gap-2 ml-4">
                        <Button variant="ghost" size="icon" onClick={() => handleEdit(app)} className="text-indigo-400 hover:text-indigo-300">
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => handleDelete(app.id)} className="text-red-400 hover:text-red-300">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
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
