"use client";

import Link from "next/link";
import {
  Bot,
  Zap,
  Shield,
  BarChart3,
  MessageSquare,
  Smartphone,
  Check,
  ArrowRight,
  Crown,
  Brain,
  MonitorSmartphone,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";

const features = [
  { icon: Brain, title: "IA Inteligente", desc: "GPT-4o, Claude, Gemini — escolha seu provedor. A IA entende o contexto e executa ações automaticamente." },
  { icon: MonitorSmartphone, title: "Integração XUI", desc: "Conecta direto ao seu painel IPTV. Cria testes, renova, consulta status — tudo via WhatsApp." },
  { icon: Zap, title: "Function Calling", desc: "A IA decide qual ação executar: criar teste, renovar cliente, listar pacotes, consultar saldo." },
  { icon: MessageSquare, title: "WhatsApp Automático", desc: "Intercepta mensagens e responde em segundos. Funciona 24/7, sem precisar estar online." },
  { icon: Shield, title: "Regras Flexíveis", desc: "Horário de atendimento, lista negra/VIP, limite por contato, palavras-chave para transferir." },
  { icon: BarChart3, title: "Analytics Completo", desc: "Métricas de uso, performance da IA, ações executadas, tokens consumidos — tudo no dashboard." },
];

const plans = [
  {
    name: "Starter",
    price: "29,90",
    features: ["500 mensagens/mês", "1 número WhatsApp", "3 ações básicas", "OpenAI", "Suporte email"],
    popular: false,
  },
  {
    name: "Pro",
    price: "59,90",
    features: ["3.000 mensagens/mês", "3 números WhatsApp", "Todas as ações", "OpenAI + Claude", "Analytics completo", "Suporte WhatsApp"],
    popular: true,
  },
  {
    name: "Business",
    price: "99,90",
    features: ["Mensagens ilimitadas", "WhatsApp ilimitado", "Todas as ações", "Todos os provedores", "Analytics + Exportar", "Suporte prioritário"],
    popular: false,
  },
];

const steps = [
  { num: "1", title: "Crie sua conta", desc: "Cadastro em 30 segundos. 7 dias grátis para testar." },
  { num: "2", title: "Configure", desc: "Conecte seu painel XUI e escolha seu provedor de IA." },
  { num: "3", title: "Instale o app", desc: "Baixe o app Android e ative o serviço de notificações." },
  { num: "4", title: "Pronto!", desc: "A IA começa a responder seus clientes automaticamente." },
];

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-slate-950 text-white">
      {/* Nav */}
      <nav className="fixed top-0 z-50 w-full border-b border-slate-800/50 bg-slate-950/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
          <div className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600">
              <Bot className="h-5 w-5 text-white" />
            </div>
            <span className="text-lg font-bold">AI Auto Reply</span>
          </div>
          <div className="flex items-center gap-3">
            <Link href="/login">
              <Button variant="ghost" className="text-slate-300 hover:text-white">Entrar</Button>
            </Link>
            <Link href="/register">
              <Button className="bg-indigo-600 hover:bg-indigo-700">Começar Grátis</Button>
            </Link>
          </div>
        </div>
      </nav>

      {/* Hero */}
      <section className="relative overflow-hidden pt-32 pb-20">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.15),transparent_50%)]" />
        <div className="relative mx-auto max-w-4xl px-4 text-center">
          <Badge className="mb-6 bg-indigo-600/20 text-indigo-300">
            <Smartphone className="mr-1.5 h-3 w-3" />
            App Android + Painel Web
          </Badge>
          <h1 className="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
            Atendimento WhatsApp com{" "}
            <span className="bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
              Inteligência Artificial
            </span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-slate-400">
            Automatize o atendimento dos seus clientes IPTV. A IA cria testes, renova assinaturas,
            consulta status e responde dúvidas — tudo pelo WhatsApp, 24 horas por dia.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-4">
            <Link href="/register">
              <Button size="lg" className="bg-indigo-600 px-8 hover:bg-indigo-700">
                Testar 7 Dias Grátis <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
            </Link>
            <a href="#features">
              <Button size="lg" variant="outline" className="border-slate-700 px-8 text-slate-300 hover:bg-slate-800">
                Ver Recursos
              </Button>
            </a>
          </div>
          <p className="mt-4 text-sm text-slate-500">Sem cartão de crédito. Cancele quando quiser.</p>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="py-20">
        <div className="mx-auto max-w-6xl px-4">
          <h2 className="text-center text-3xl font-bold">Tudo que você precisa</h2>
          <p className="mt-3 text-center text-slate-400">Automatize seu atendimento em minutos, não em semanas.</p>
          <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {features.map((f) => (
              <Card key={f.title} className="border-slate-800 bg-slate-900/50 transition-colors hover:border-slate-700">
                <CardContent className="p-6">
                  <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/10">
                    <f.icon className="h-5 w-5 text-indigo-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-white">{f.title}</h3>
                  <p className="mt-2 text-sm text-slate-400">{f.desc}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* How it works */}
      <section className="border-y border-slate-800 bg-slate-900/30 py-20">
        <div className="mx-auto max-w-4xl px-4">
          <h2 className="text-center text-3xl font-bold">Como funciona</h2>
          <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            {steps.map((s) => (
              <div key={s.num} className="text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-xl font-bold">
                  {s.num}
                </div>
                <h3 className="mt-4 font-semibold text-white">{s.title}</h3>
                <p className="mt-2 text-sm text-slate-400">{s.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="py-20">
        <div className="mx-auto max-w-5xl px-4">
          <h2 className="text-center text-3xl font-bold">Planos simples, sem surpresas</h2>
          <p className="mt-3 text-center text-slate-400">Escolha o plano ideal para o tamanho da sua operação.</p>
          <div className="mt-12 grid gap-6 lg:grid-cols-3">
            {plans.map((plan) => (
              <Card key={plan.name} className={`relative border-slate-800 bg-slate-900 ${plan.popular ? "ring-2 ring-indigo-500" : ""}`}>
                {plan.popular && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge className="bg-indigo-600"><Crown className="mr-1 h-3 w-3" />Mais Popular</Badge>
                  </div>
                )}
                <CardContent className="p-6 pt-8 text-center">
                  <h3 className="text-xl font-bold text-white">{plan.name}</h3>
                  <div className="mt-4">
                    <span className="text-4xl font-extrabold text-white">R$ {plan.price}</span>
                    <span className="text-slate-400">/mês</span>
                  </div>
                  <ul className="mt-6 space-y-3 text-left">
                    {plan.features.map((f) => (
                      <li key={f} className="flex items-center gap-2 text-sm text-slate-300">
                        <Check className="h-4 w-4 shrink-0 text-green-400" />{f}
                      </li>
                    ))}
                  </ul>
                  <Link href="/register" className="mt-6 block">
                    <Button className={`w-full ${plan.popular ? "bg-indigo-600 hover:bg-indigo-700" : "bg-slate-800 hover:bg-slate-700"}`}>
                      Começar Agora
                    </Button>
                  </Link>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="border-t border-slate-800 py-20">
        <div className="mx-auto max-w-3xl px-4 text-center">
          <h2 className="text-3xl font-bold">Pronto para automatizar seu atendimento?</h2>
          <p className="mt-4 text-slate-400">
            Junte-se a revendedores que já economizam horas por dia com atendimento automático via IA.
          </p>
          <Link href="/register">
            <Button size="lg" className="mt-8 bg-indigo-600 px-10 hover:bg-indigo-700">
              Começar Grátis — 7 Dias <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-800 py-8">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 sm:flex-row">
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Bot className="h-4 w-4" /> AI Auto Reply
          </div>
          <p className="text-xs text-slate-600">&copy; 2026 AI Auto Reply. Todos os direitos reservados.</p>
        </div>
      </footer>
    </div>
  );
}
