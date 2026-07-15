# FINBA - PROJECT CONTEXT

## Visão

Finba.se é um sistema de controle financeiro pessoal desenvolvido em Laravel 13 + Filament 5.

Versão atual: **0.1.0-beta** (`v0.1.0-beta` na UI)  
Estágio: **Beta**  
Deploy: **Production** (`https://app.finba.se`)

Objetivo principal:

* Controle financeiro simples para usuários comuns.
* Recursos avançados opcionais.
* Forte foco em UX.
* Mobile-first via installable PWA (online-first; native packaging still deferred).
* Multi-idioma desde o início (pt-BR e en-US).

Foco atual do desenvolvimento:

* correção de bugs
* melhorias de UX
* desempenho
* feedback dos usuários

---

# Stack

* PHP 8.4
* Laravel 13
* Filament 5
* PostgreSQL (Supabase)
* Compute: Google Cloud Run (`southamerica-east1`), imagem FrankenPHP, host `https://app.finba.se`
* DNS/proxy: Cloudflare (domínio customizado preferencialmente via Load Balancer + serverless NEG)
* Email: Resend
* Armazenamento de arquivos: `FINBA_STORAGE_DISK` → disk `local` (dev) ou `finba` (produção, S3-compatível / Supabase Storage, bucket privado). Sem dependência de filesystem persistente do container. Ver `docs/supabase-storage.md`
* Sessões/cache compartilhados via driver `database` (Cloud Run stateless)
* Queue em produção: `sync` (sem worker dedicado nesta fase). Scheduler: não requerido ainda
* Migrations: Cloud Run Job `finba-migrate` (`php artisan migrate --force`), nunca no boot do serviço web
* Logs produção: `LOG_CHANNEL=stderr` → Cloud Logging
* Health: `GET /up`
* Deploy docs/scripts: `docs/deployment-gcp-cloud-run.md`, `scripts/deploy-cloud-run.sh`, `scripts/migrate-cloud-run.sh`
* UUIDs em todas as entidades
* Soft Deletes onde fizer sentido

---

# Conceitos de Domínio

## Categories

Categorias organizam receitas e despesas.

Suportam:

* Hierarquia pai/filho
* Tipos:

  * INCOME
  * EXPENSE
* Purpose opcional:

  * TITHE
  * OFFERING

Observações:

* Categories podem possuir ambos os tipos.
* Purpose exige que a categoria contenha EXPENSE.
* Se EXPENSE for removido, purpose deve ser limpo automaticamente.

---

## People

Representam:

* Empresas
* Pessoas
* Instituições
* Igrejas
* Bancos
* Clientes

Possuem:

* Nome
* Types (INCOME/EXPENSE)
* Relacionamento opcional com categorias

---

## Category <-> People

Relação many-to-many.

Tabela:

category_person

Campos:

* user_id
* category_id
* person_id

Regra de negócio:

Somente categorias pai podem ser vinculadas diretamente a pessoas.

Subcategorias herdam implicitamente o relacionamento da categoria pai.

Exemplo:

Pessoa:

* Igreja

Categoria:

* Oferta

Subcategorias:

* Construção
* Missões

A pessoa é vinculada apenas à categoria pai "Oferta".

---

## Transactions

Representam movimentações financeiras reais.

Campos principais:

* amount
* type
* status
* category
* person
* loan
* installment_group
* installment_number
* recurring_transaction

Tipos:

* INCOME
* EXPENSE

Status:

* PENDING
* PAID

Purpose:

* null
* TITHE
* OFFERING

Observação:

Purpose em transaction representa a entrega efetiva.

Exemplo:

Despesa:

* R$ 15,00
* purpose = TITHE

Isto significa que o usuário entregou R$ 15,00 de dízimo.

---

## Installment Groups

Representam um plano de parcelamento.

Regra atual:

* InstallmentGroup é o registro pai do plano.
* Cada parcela é uma Transaction real vinculada por `installment_group_id`.
* O fluxo básico de criação, numeração (ex.: 3/12), datas mensais e distribuição de centavos está concluído e utilizável ponta a ponta.

Adiado (fora do MVP):

* editar todas as parcelas de uma vez
* cancelar parcelas futuras
* quitação antecipada
* juros e faturas de cartão

---

## Tithe Calculations

Responsáveis pelos cálculos de:

* Dízimo
* Oferta
* Primícias

Tabela:

tithe_calculations

Campos:

* period_start
* period_end
* base_amount
* tithe_amount
* offering_target_amount
* offering_paid_amount
* firstfruits_amount

Regra:

Dízimos e ofertas são calculados sobre receitas elegíveis.

Primícias:

dias_do_ano / 12

Exemplo:

365 / 12

ou

366 / 12

em anos bissextos.

---

## Loans

Representam dinheiro emprestado ou recebido.

Tipos:

* LENT
* BORROWED

---

## Recurring Transactions

Representam compromissos financeiros recorrentes.

Exemplos:

* Internet
* Energia
* Netflix
* Salário
* Aluguel

Campos importantes:

* name
* type
* amount_mode
* amount
* frequency
* next_occurrence_at

amount_mode:

* FIXED
* VARIABLE

FIXED:

Exemplo:
Internet = R$ 99,90

VARIABLE:

Exemplo:
Conta de luz

A sugestão de pagamento deve usar a média dos últimos 3 pagamentos.

---

## Reminders

Responsáveis por avisos.

Tipos:

* ANNIVERSARY
* LOAN
* COMMITMENT
* CUSTOM

Canais:

* EMAIL
* WHATSAPP
* PUSH

Offsets:

Exemplo:

[
{ "value": 1, "unit": "MONTH" },
{ "value": 2, "unit": "WEEK" },
{ "value": 1, "unit": "DAY" },
{ "value": 0, "unit": "DAY" }
]

---

# Fluxo de Pagamento de Compromissos

RecurringTransaction
↓
Reminder
↓
Botão Pagar
↓
Transaction
↓
Atualizar next_occurrence_at
↓
Atualizar Reminder

O botão "Pagar" nunca cria diretamente.

Ele inicia um fluxo de confirmação.

---

# Interface

## Transaction Resource

Prioridade máxima atual.

Objetivos:

* UX excelente
* Criação rápida
* Poucos cliques
* Mobile-friendly

### Ideias futuras

Mobile:

Substituir tabela por cards.

Exemplo:

TESTE                     R$ 123,45
Despesa • Pago
Igreja
12/06/2026

[Editar] [Excluir]

Desktop continua usando tabela.

---

# Princípios

* Evitar complexidade desnecessária.
* Recursos avançados ficam atrás de is_advanced.
* Regras de negócio devem ficar em Services e Models, não em Resources.
* Preferir Enums ao invés de strings soltas.
* Preferir componentes reutilizáveis (ex: MoneyInput).
* Domínio primeiro, interface depois.

---

# PWA

Finba.se é online-first e instalável como Progressive Web App.

Comportamento atual:

* Manifesto em `/manifest.webmanifest`
* Service worker conservador em `/service-worker.js`
* Precache apenas de ativos públicos estáticos
* Navegação authenticated/HTML financeira NÃO é cacheada
* Sem mutações financeiras offline
* Offline fallback em `/offline.html`
* Botão permanente de instalação no topo (perto do avatar)
* Modal explicativo antes do prompt nativo do navegador
* Sugestão proativa no máximo uma vez por sessão
* iOS Safari recebe instruções manuais de “Adicionar à Tela de Início”
* Atualizações do service worker exigem confirmação do usuário

Storage permitido no navegador:

* flags de UI em `sessionStorage` (banner de release, instalação sugerida, update adiado)

Proibido:

* armazenar lançamentos financeiros em Cache Storage, IndexedDB ou Background Sync

Adiado:

* empacotamento nativo (Capacitor/NativePHP/lojas)
* push notifications
* sync offline

---

# Feedback e Transparência

Canal manual de feedback:

* Página autenticada `Feedback` (`/feedback`) no grupo Sistema
* Persistência na tabela `feedback` (UUID, protocolo `FDB-AAAA-XXXXXXXX`, tipo, status, assunto, mensagem, ação tentada, contexto JSON seguro, anexo opcional)
* Tipos: `BUG`, `SUGGESTION`, `OTHER`
* Status: `OPEN`, `REVIEWING`, `RESOLVED`, `DISMISSED`
* Anexos de feedback: caminhos de objeto no disk da aplicação (`FINBA_STORAGE_DISK` → `config('finba.storage.disk')`), nunca URLs assinadas/públicas nem caminhos absolutos locais. Convenção: `feedback/{feedback_uuid}/{arquivo-gerado.ext}`. Local: disk Laravel `local`. Produção: disk `finba` (S3-compatível → Supabase Storage, bucket privado). APIs agnósticas via `FileStorageService` / `Storage::disk(...)`. E-mail anexa via leitura server-side (`Attachment::fromStorageDisk`). Soft delete mantém o arquivo; `forceDelete` remove o objeto. Setup: `docs/supabase-storage.md`. Diagnóstico: `php artisan finba:storage-check`.
* Contexto técnico opcional (URL/path, UA, viewport/tela, locale, timezone). Sem senhas, tokens, cookies, payloads financeiros
* Metadados de build sempre anexados ao contexto via `App\Support\ApplicationBuild` / `config/finba.php`: `APP_VERSION` (default `0.1.0-beta`), `APP_STAGE` (default `Beta`), `APP_BUILD`, `GIT_SHA`. UI exibe `v0.1.0-beta`.
* E-mail via `FINBA_FEEDBACK_EMAIL` (`config/finba.php`). Se vazio: salva e registra warning. Se o envio falhar após persistir: mantém o registro e avisa o usuário
* Rate limit padrão: 8 envios/usuário/hora (`FINBA_FEEDBACK_RATE_LIMIT`)
* Envio de e-mail síncrono nesta fase (filas não exigidas)
* Sem Resource/admin de listagem neste momento; domínio preparado para CRUD interno futuro
* Sentry / monitoramento automático de exceções: **adiado**

Página `Sobre o Finba` (`/about`):

* Conteúdo institucional (produto em Beta, versão `v0.1.0-beta`, criador, AGPL, build in public)
* Metadados públicos do criador em `config/finba.creator` (GitHub/LinkedIn fixos; `url` null até o portfólio)

Rodapé autenticado:

* `Finba.se © {ano}` / `Desenvolvido por José Paulo Oliveira Filho` / `Beta · v0.1.0-beta · AGPL v3`
* Nome clicável somente quando `config('finba.creator.url')` estiver definido

Navegação Sistema: Changelog → Roadmap → Feedback → Sobre o Finba

# Situação Atual

Produto em **Beta** em produção (`v0.1.0-beta`).

Fluxos principais disponíveis:

* Transações à vista
* Contas a receber
* Parcelamentos básicos
* Dashboard mensal
* Onboarding e preferências
* PWA instalável (online-first)
* Canal de feedback e página Sobre o Finba

Foco atual:

1. Estabilização da versão beta (bugs, UX, desempenho, feedback)
2. Empréstimos e dívidas
3. Transações recorrentes
4. Lembretes e notificações
5. Monitoramento automático de erros (Sentry) — ainda não iniciado
