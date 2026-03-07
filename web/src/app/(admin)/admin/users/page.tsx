"use client";

import { useEffect, useState, useCallback } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
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
import { Label } from "@/components/ui/label";
import { Search, Pencil, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { toast } from "sonner";
import api from "@/lib/api";
import type { AdminUser, Plan, PaginatedResponse } from "@/lib/types";

export default function AdminUsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);

  const [editUser, setEditUser] = useState<AdminUser | null>(null);
  const [editForm, setEditForm] = useState({ name: "", email: "", phone: "", status: "", is_admin: false, password: "" });
  const [plans, setPlans] = useState<Plan[]>([]);
  const [selectedPlan, setSelectedPlan] = useState<string>("");

  const fetchUsers = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<PaginatedResponse<AdminUser>>("/admin/users", {
        params: { page, search: search || undefined },
      });
      setUsers(res.data.data);
      setLastPage(res.data.last_page);
      setTotal(res.data.total);
    } catch {
      toast.error("Erro ao carregar usuários");
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  useEffect(() => {
    api.get("/admin/plans").then((res) => setPlans(res.data)).catch(() => {});
  }, []);

  const openEdit = (user: AdminUser) => {
    setEditUser(user);
    setEditForm({
      name: user.name,
      email: user.email,
      phone: user.phone || "",
      status: user.status,
      is_admin: user.is_admin,
      password: "",
    });
    setSelectedPlan(user.subscription?.plan_id?.toString() || "");
  };

  const saveUser = async () => {
    if (!editUser) return;
    try {
      const payload: Record<string, unknown> = {
        name: editForm.name,
        email: editForm.email,
        phone: editForm.phone || null,
        status: editForm.status,
        is_admin: editForm.is_admin,
      };
      if (editForm.password) payload.password = editForm.password;

      await api.put(`/admin/users/${editUser.id}`, payload);

      if (selectedPlan) {
        await api.post("/admin/subscriptions", {
          user_id: editUser.id,
          plan_id: parseInt(selectedPlan),
          status: "active",
        });
      }

      toast.success("Usuário atualizado");
      setEditUser(null);
      fetchUsers();
    } catch {
      toast.error("Erro ao salvar");
    }
  };

  const deleteUser = async (id: number) => {
    if (!confirm("Tem certeza que deseja excluir este usuário?")) return;
    try {
      await api.delete(`/admin/users/${id}`);
      toast.success("Usuário excluído");
      fetchUsers();
    } catch {
      toast.error("Erro ao excluir");
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case "active": return "bg-green-500/10 text-green-400";
      case "inactive": return "bg-gray-500/10 text-gray-400";
      case "suspended": return "bg-red-500/10 text-red-400";
      default: return "bg-slate-500/10 text-slate-400";
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Usuários ({total})</h1>
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <Input
          placeholder="Buscar por nome, email ou telefone..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="border-slate-700 bg-slate-900 pl-10 text-white"
        />
      </div>

      <Card className="border-slate-800 bg-slate-900">
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Nome</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Email</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Plano</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Msgs/Mês</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Status</th>
                  <th className="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Admin</th>
                  <th className="px-4 py-3 text-right text-xs font-medium uppercase text-slate-400">Ações</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Carregando...</td></tr>
                ) : users.length === 0 ? (
                  <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Nenhum usuário encontrado</td></tr>
                ) : (
                  users.map((user) => (
                    <tr key={user.id} className="border-b border-slate-800/50 hover:bg-slate-800/30">
                      <td className="px-4 py-3 text-sm font-medium text-white">{user.name}</td>
                      <td className="px-4 py-3 text-sm text-slate-300">{user.email}</td>
                      <td className="px-4 py-3 text-sm text-slate-300">
                        {user.subscription?.plan?.name || <span className="text-slate-500">Sem plano</span>}
                      </td>
                      <td className="px-4 py-3 text-sm text-slate-300">
                        {user.messages_this_month?.toLocaleString("pt-BR") ?? 0}
                      </td>
                      <td className="px-4 py-3">
                        <Badge className={statusColor(user.status)}>{user.status}</Badge>
                      </td>
                      <td className="px-4 py-3">
                        {user.is_admin && <Badge variant="destructive">Admin</Badge>}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-1">
                          <Button size="icon" variant="ghost" onClick={() => openEdit(user)}>
                            <Pencil className="h-4 w-4 text-slate-400" />
                          </Button>
                          <Button size="icon" variant="ghost" onClick={() => deleteUser(user.id)}>
                            <Trash2 className="h-4 w-4 text-red-400" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm text-slate-400">Página {page} de {lastPage}</span>
          <Button size="sm" variant="outline" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      )}

      <Dialog open={!!editUser} onOpenChange={() => setEditUser(null)}>
        <DialogContent className="border-slate-800 bg-slate-900 text-white sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Editar Usuário</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Nome</Label>
              <Input value={editForm.name} onChange={(e) => setEditForm({ ...editForm, name: e.target.value })} className="border-slate-700 bg-slate-800" />
            </div>
            <div>
              <Label>Email</Label>
              <Input value={editForm.email} onChange={(e) => setEditForm({ ...editForm, email: e.target.value })} className="border-slate-700 bg-slate-800" />
            </div>
            <div>
              <Label>Telefone</Label>
              <Input value={editForm.phone} onChange={(e) => setEditForm({ ...editForm, phone: e.target.value })} className="border-slate-700 bg-slate-800" />
            </div>
            <div>
              <Label>Nova Senha (opcional)</Label>
              <Input type="password" value={editForm.password} onChange={(e) => setEditForm({ ...editForm, password: e.target.value })} className="border-slate-700 bg-slate-800" placeholder="Deixe vazio para manter" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Status</Label>
                <Select value={editForm.status} onValueChange={(v) => setEditForm({ ...editForm, status: v })}>
                  <SelectTrigger className="border-slate-700 bg-slate-800"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Ativo</SelectItem>
                    <SelectItem value="inactive">Inativo</SelectItem>
                    <SelectItem value="suspended">Suspenso</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Plano</Label>
                <Select value={selectedPlan} onValueChange={setSelectedPlan}>
                  <SelectTrigger className="border-slate-700 bg-slate-800"><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>
                    {plans.map((p) => (
                      <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={editForm.is_admin}
                onChange={(e) => setEditForm({ ...editForm, is_admin: e.target.checked })}
                className="h-4 w-4 rounded border-slate-700"
              />
              <Label>Administrador</Label>
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setEditUser(null)}>Cancelar</Button>
              <Button onClick={saveUser}>Salvar</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
