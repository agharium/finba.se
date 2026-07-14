# FINBA - PROJECT CONTEXT

## Visão

Finba.se é um sistema de controle financeiro pessoal desenvolvido em Laravel 13 + Filament 5.

Objetivo principal:

* Controle financeiro simples para usuários comuns.
* Recursos avançados opcionais.
* Forte foco em UX.
* Mobile-first via installable PWA (online-first; native packaging still deferred).
* Multi-idioma desde o início (pt-BR e en-US).

---

# Stack

* PHP 8.4
* Laravel 13
* Filament 5
* PostgreSQL (Supabase)
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

* flags de UI em `sessionStorage` (banner alfa, instalação sugerida, update adiado)

Proibido:

* armazenar lançamentos financeiros em Cache Storage, IndexedDB ou Background Sync

Adiado:

* empacotamento nativo (Capacitor/NativePHP/lojas)
* push notifications
* sync offline

---

# Situação Atual

Fluxos principais já utilizáveis:

* Transações à vista
* Contas a receber
* Parcelamentos básicos
* Dashboard mensal
* Onboarding e preferências
* PWA instalável (online-first)

Próximo foco:

1. Empréstimos e dívidas
2. Transações recorrentes
3. Lembretes e notificações
4. Preparação da primeira versão beta
