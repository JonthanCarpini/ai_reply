# PRD — Implantação do Agente Conversacional Completo

## 1. Objetivo

Implantar no projeto `ai_reply` um agente conversacional completo, capaz de conduzir atendimentos multiestágio com memória operacional, regras determinísticas, tool calling dinâmico, políticas de resposta e rastreabilidade ponta a ponta entre Android, backend Laravel e painel web.

## 2. Escopo

### 2.1 Incluído

- Estado conversacional estruturado por conversa e por mensagem
- Orquestrador híbrido por fases com loop curto de tools
- Guards determinísticos e validação de pré-condições
- Catálogo de tools configurável e auditável
- Prompt estruturado por blocos com compatibilidade retroativa
- Políticas de resposta pós-raciocínio
- UI operacional para conversas, prompts, ações e debug
- Enriquecimento do payload Android com correlação e rastreabilidade
- Validação e preparação de deploy para VPS

### 2.2 Não incluído neste ciclo

- Substituição completa do modelo atual de providers de IA
- Mudança de stack do frontend, backend ou Android
- Introdução de filas assíncronas complexas para cada mensagem antes de estabilizar o fluxo síncrono atual

## 3. Objetivos de negócio

- Reduzir dependência de atendimento humano repetitivo
- Aumentar taxa de resolução automática de ponta a ponta
- Melhorar auditabilidade das decisões do agente
- Diminuir fragilidade causada por prompt monolítico
- Permitir operação e ajuste do agente diretamente pela UI

## 4. Arquitetura alvo

### 4.1 Backend

O `AIEngine` permanece como coordenador, mas passa a delegar responsabilidades para serviços especializados:

- `ConversationContextService`
- `PhaseResolver`
- `PolicyGuard`
- `ToolExecutionOrchestrator`
- `ReplyPolicyService`
- `ConversationJourneyService`
- `ConversationPhaseOrchestrator`

### 4.2 Fases explícitas do atendimento

- `abertura`
- `identificacao`
- `diagnostico_intencao`
- `qualificacao`
- `recomendacao_app_dispositivo`
- `teste`
- `pagamento_renovacao`
- `suporte`
- `handoff_humano`
- `encerramento`

A persistência pode usar labels internas compatíveis com o fluxo atual desde que a semântica seja equivalente.

## 5. Fases de implantação

### Fase 1 — Estado conversacional estruturado

#### Backend

Persistir em `conversations`:

- `journey_stage`
- `journey_status`
- `collected_data`
- `pending_requirements`
- `last_tool_name`
- `last_tool_status`
- `human_handoff_requested`
- `customer_flags`

Persistir em `messages`:

- intenção detectada
- entidades extraídas
- fase antes/depois
- motivo de bloqueio/política
- contexto estruturado do turno

#### UI

- fase atual
- dados coletados
- pendências
- handoff humano

#### Status

- Implantado

### Fase 2 — Orquestrador híbrido por fases

#### Backend

- plano por fase
- tools permitidas por fase
- tools preferenciais por fase
- contexto de fase no prompt
- loop curto de tools com limite controlado

#### Regras de segurança

- bloquear tool sem pré-condição
- não repetir pergunta respondida
- tratar gatilhos determinísticos antes do LLM
- não reiniciar fluxo sem contexto

#### Status

- Parcialmente implantado

### Fase 3 — Plataforma de tools do MVP

#### Backend

- catálogo de tools mais rico e tipado
- separação entre definição, validação e execução
- respostas normalizadas
- pré-condições no backend

#### UI

- habilitar/desabilitar
- limites
- instruções extras
- pré-condições
- mapeamento por fluxo/fase

#### Status

- Em implantação

### Fase 4 — Prompting e políticas de resposta

#### Backend

Estruturar prompts em blocos:

- identidade
- tom
- regras permanentes
- política de resposta curta
- gatilhos automáticos
- fluxo por fase

Aplicar política pós-resposta:

- tamanho
- dados proibidos
- envio de credenciais
- tom adequado

#### UI

- edição estruturada por seções
- preview do prompt final
- compatibilidade com prompt livre atual

#### Status

- Em implantação

### Fase 5 — Frontend operacional do agente

#### UI

Conversas:

- fase atual
- timeline de tools
- dados coletados
- status do atendimento
- marcador de handoff

Prompts:

- seções estruturadas
- preview final

Ações:

- catálogo completo
- limites
- gatilhos
- pré-condições

Regras e operação:

- visão operacional do agente
- resumo de fases
- automações ativas
- estado do atendimento

Debug:

- fase detectada
- decisão
- tool chamada
- resultado
- bloqueios

#### Status

- Em implantação

### Fase 6 — Android e robustez fim a fim

#### Android

- identificadores estáveis por evento
- metadados de origem e lote
- idempotência reforçada
- rastreabilidade por evento

#### Backend

- deduplicação mais forte
- correlação entre mensagem recebida, decisão e reply

#### Status

- Pendente

### Fase 7 — Validação, rollout e deploy

#### Validação

- testes unitários do orquestrador e guards
- testes de integração das tools críticas
- cenários ponta a ponta no Android
- validação por logs estruturados

#### Publicação

- git
- push
- deploy na VPS com o fluxo operacional definido

#### Status

- Pendente

## 6. Requisitos funcionais

### 6.1 Atendimento multietapas

O agente deve conseguir:

- entender em que fase a conversa está
- conduzir a próxima ação apropriada
- usar tools somente quando fizer sentido
- pedir apenas os dados mínimos que faltam
- reaproveitar dados já coletados
- escalar para humano quando necessário

### 6.2 Segurança operacional

O sistema deve:

- validar pré-condições antes da execução de tool
- bloquear execuções inconsistentes
- registrar motivo de bloqueio
- aplicar limites de steps por mensagem
- evitar repetir perguntas já respondidas

### 6.3 Observabilidade

Cada mensagem processada deve poder expor:

- fase antes/depois
- plano de orquestração
- tool chamada
- parâmetros
- resultado
- política aplicada
- correlação com evento do Android

## 7. Critérios de aceite

### 7.1 Conversa estruturada

- uma conversa exibe fase, status, pendências e dados coletados
- o backend persiste contexto estruturado por turno

### 7.2 Orquestração

- o agente limita tools por fase
- o agente executa um loop curto controlado de tools quando necessário
- o agente respeita guards determinísticos antes do LLM

### 7.3 Tools

- ações podem ser configuradas com pré-condições e limites
- respostas das tools seguem formato previsível

### 7.4 Prompt e resposta

- prompts aceitam seções estruturadas
- a resposta final passa por política de validação

### 7.5 Debug e operação

- a UI mostra trilha operacional suficiente para auditoria

### 7.6 Android

- payload inclui correlação mínima por evento
- backend consegue logar o vínculo entre entrada e resposta

## 8. Decisões técnicas atuais

- Persistência inicial via campos e JSONs, evitando explosão de tabelas cedo demais
- `AIEngine` continua como coordenador principal
- serviços menores concentram políticas específicas
- compatibilidade retroativa com prompt livre e tool registry atual
- implantação incremental sem trocar a base tecnológica existente

## 9. Riscos

- Crescimento excessivo do `AIEngine` se a decomposição não for concluída
- prompts estruturados coexistindo com prompts livres sem regras claras de precedência
- payload Android sem correlação suficiente para debug de eco e duplicidade
- UI operacional sem filtros/sumarização podendo ficar ruidosa

## 10. Próximos passos imediatos

1. concluir decomposição do `AIEngine` nos serviços especializados
2. adicionar pré-condições e configuração de fluxo em `actions`
3. adicionar seções estruturadas e políticas de resposta em `prompts`
4. enriquecer logs/debug trail por mensagem
5. enriquecer payload Android com rastreabilidade
6. validar sintaxe, fluxo manual e preparar deploy
