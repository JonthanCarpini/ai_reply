"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/store/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Bot, Loader2 } from "lucide-react";
import { toast } from "sonner";

export default function RegisterPage() {
  const router = useRouter();
  const { register } = useAuth();
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
  });

  function updateField(field: string, value: string) {
    setForm((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (form.password !== form.password_confirmation) {
      toast.error("As senhas não coincidem.");
      return;
    }
    setLoading(true);
    try {
      await register(form.name, form.email, form.phone, form.password, form.password_confirmation);
      toast.success("Conta criada com sucesso! Aproveite o trial de 7 dias.");
      router.push("/dashboard");
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const firstError = error.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : error.response?.data?.message;
      toast.error(firstError || "Erro ao criar conta.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-4">
      <Card className="w-full max-w-md border-slate-800 bg-slate-900/80 backdrop-blur-sm">
        <CardHeader className="text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-600">
            <Bot className="h-8 w-8 text-white" />
          </div>
          <CardTitle className="text-2xl text-white">Criar Conta</CardTitle>
          <CardDescription className="text-slate-400">
            7 dias grátis para testar. Sem cartão de crédito.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name" className="text-slate-300">Nome</Label>
              <Input
                id="name"
                placeholder="Seu nome"
                value={form.name}
                onChange={(e) => updateField("name", e.target.value)}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="email" className="text-slate-300">Email</Label>
              <Input
                id="email"
                type="email"
                placeholder="seu@email.com"
                value={form.email}
                onChange={(e) => updateField("email", e.target.value)}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="phone" className="text-slate-300">WhatsApp</Label>
              <Input
                id="phone"
                placeholder="(11) 99999-9999"
                value={form.phone}
                onChange={(e) => updateField("phone", e.target.value)}
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password" className="text-slate-300">Senha</Label>
              <Input
                id="password"
                type="password"
                placeholder="Mínimo 8 caracteres"
                value={form.password}
                onChange={(e) => updateField("password", e.target.value)}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password_confirmation" className="text-slate-300">Confirmar Senha</Label>
              <Input
                id="password_confirmation"
                type="password"
                placeholder="Repita a senha"
                value={form.password_confirmation}
                onChange={(e) => updateField("password_confirmation", e.target.value)}
                required
                className="border-slate-700 bg-slate-800 text-white placeholder:text-slate-500"
              />
            </div>
            <Button type="submit" className="w-full bg-indigo-600 hover:bg-indigo-700" disabled={loading}>
              {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Criar Conta Grátis
            </Button>
          </form>
          <p className="mt-4 text-center text-sm text-slate-400">
            Já tem conta?{" "}
            <Link href="/login" className="text-indigo-400 hover:text-indigo-300">
              Fazer login
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
