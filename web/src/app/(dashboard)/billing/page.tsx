"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import type { Plan } from "@/lib/types";
import { useAuth } from "@/store/auth";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { CreditCard, Check, Crown, Loader2, ExternalLink } from "lucide-react";
import { toast } from "sonner";

export default function BillingPage() {
  const { user, fetchUser } = useAuth();
  const [plans, setPlans] = useState<Plan[]>([]);
  const [loading, setLoading] = useState(true);
  const [subscribing, setSubscribing] = useState<number | null>(null);
  const [cancelling, setCancelling] = useState(false);

  const currentPlan = user?.subscription?.plan;
  const subStatus = user?.subscription?.status;

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get("/billing/plans");
        setPlans(res.data.data);
      } catch { /* empty */ } finally { setLoading(false); }
    }
    load();
  }, []);

  async function handleSubscribe(planId: number) {
    setSubscribing(planId);
    try {
      const res = await api.post("/billing/subscribe", { plan_id: planId });
      if (res.data.checkout_url) {
        window.open(res.data.checkout_url, "_blank");
        toast.info("Redirecionado para pagamento. Conclua no Mercado Pago.");
      } else {
        toast.error("Erro ao gerar link de pagamento.");
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error.response?.data?.message || "Erro ao iniciar pagamento.");
    } finally { setSubscribing(null); }
  }

  async function handleCancel() {
    if (!confirm("Tem certeza que deseja cancelar sua assinatura?")) return;
    setCancelling(true);
    try {
      await api.post("/billing/cancel");
      toast.success("Assinatura cancelada.");
      fetchUser();
    } catch { toast.error("Erro ao cancelar."); } finally { setCancelling(false); }
  }

  const planFeatures: Record<string, string[]> = {
    starter: ["500 mensagens/mês", "1 número WhatsApp", "3 ações (teste, listar, status)", "OpenAI", "Histórico 7 dias", "Suporte email"],
    pro: ["3.000 mensagens/mês", "3 números WhatsApp", "Todas as ações", "OpenAI + Claude", "Histórico 30 dias", "Analytics completo", "Suporte WhatsApp"],
    business: ["Mensagens ilimitadas", "WhatsApp ilimitado", "Todas as ações", "Todos os provedores", "Histórico ilimitado", "Analytics + Exportar", "Suporte prioritário"],
  };

  if (loading) return <div className="h-96 animate-pulse rounded-lg bg-slate-900" />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-white">Plano e Faturamento</h1>
        {currentPlan && (
          <Badge className={subStatus === "active" ? "bg-green-600" : subStatus === "trialing" ? "bg-amber-600" : "bg-slate-600"}>
            {currentPlan.name} — {subStatus === "active" ? "Ativo" : subStatus === "trialing" ? "Trial" : "Inativo"}
          </Badge>
        )}
      </div>

      {user?.subscription?.current_period_end && (
        <p className="text-sm text-slate-400">
          {subStatus === "trialing" ? "Trial expira em: " : "Próxima cobrança: "}
          <strong className="text-white">{new Date(user.subscription.current_period_end).toLocaleDateString("pt-BR")}</strong>
        </p>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        {plans.map((plan) => {
          const isCurrent = currentPlan?.id === plan.id;
          const features = planFeatures[plan.slug] || [];
          const isPopular = plan.slug === "pro";

          return (
            <Card key={plan.id} className={`relative border-slate-800 bg-slate-900 ${isPopular ? "ring-2 ring-indigo-500" : ""}`}>
              {isPopular && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <Badge className="bg-indigo-600"><Crown className="mr-1 h-3 w-3" />Mais Popular</Badge>
                </div>
              )}
              <CardHeader className="text-center">
                <CardTitle className="text-xl text-white">{plan.name}</CardTitle>
                <CardDescription>
                  <span className="text-3xl font-bold text-white">R$ {Number(plan.price).toFixed(2).replace(".", ",")}</span>
                  <span className="text-slate-400">/mês</span>
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <ul className="space-y-2">
                  {features.map((f) => (
                    <li key={f} className="flex items-center gap-2 text-sm text-slate-300">
                      <Check className="h-4 w-4 text-green-400" />{f}
                    </li>
                  ))}
                </ul>
                {isCurrent ? (
                  <Button variant="outline" className="w-full border-green-600 text-green-400" disabled>
                    Plano Atual
                  </Button>
                ) : (
                  <Button className="w-full bg-indigo-600 hover:bg-indigo-700" onClick={() => handleSubscribe(plan.id)} disabled={subscribing === plan.id}>
                    {subscribing === plan.id ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <ExternalLink className="mr-2 h-4 w-4" />}
                    Assinar
                  </Button>
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>

      {subStatus === "active" && (
        <Card className="border-red-900/50 bg-slate-900">
          <CardContent className="flex items-center justify-between p-4">
            <div>
              <p className="font-medium text-white">Cancelar Assinatura</p>
              <p className="text-xs text-slate-500">Você perderá acesso ao serviço ao final do período.</p>
            </div>
            <Button variant="destructive" onClick={handleCancel} disabled={cancelling}>
              {cancelling ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Cancelar
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
