# FINBA - PROJECT CONTEXT

## Visão

Finba.se é um sistema de controle financeiro pessoal desenvolvido em Laravel 13 + Filament 5.

Objetivo principal:

* Controle financeiro simples para usuários comuns.
* Recursos avançados opcionais.
* Forte foco em UX.
* Mobile-first no futuro (PWA e NativePHP Mobile estão sendo avaliados).
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

# Situação Atual

Banco estruturado.

Migrations funcionando.

Próximo foco:

1. Transaction Resource
2. Dashboard
3. Tithe Calculations
4. Recurring Transactions
5. Reminders
6. Notifications
7. NativePHP Mobile
