<?php

return [
    [
        'date' => '2026-07-14',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Parcelamentos e experiência como aplicativo',
        'groups' => [
            [
                'title' => 'Parcelamentos',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi concluído o fluxo de transações parceladas.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Um lançamento parcelado agora gera automaticamente todas as parcelas mensais vinculadas ao mesmo grupo.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Os valores são distribuídos com precisão de centavos e as datas permanecem válidas mesmo em vencimentos no fim do mês.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'As transações passaram a exibir a identificação da parcela, como "3/12".',
                    ],
                ],
            ],
            [
                'title' => 'Experiência como aplicativo',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'O Finba.se passou a poder ser instalado como aplicativo no dispositivo.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado um fluxo guiado de instalação com acesso permanente pelo topo da aplicação.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foram incluídos suporte a atualizações e uma tela segura para momentos sem conexão.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-12',
        'version' => null,
        'title' => 'Onboarding, localização e changelog alfa',
        'groups' => [
            [
                'title' => 'Configuração inicial',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado um assistente de primeiro acesso para definir idioma, localização e recursos opcionais.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'O assistente volta a aparecer apenas enquanto a configuração inicial não for concluída.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'As preferências do perfil e do onboarding passaram a seguir as mesmas regras de localização e recursos.',
                    ],
                ],
            ],
            [
                'title' => 'Localização',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Agora é possível definir estado e cidade padrão no perfil, com catálogo brasileiro e cidades reutilizadas nos cadastros.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Cidades deixaram de ser gerenciadas em menu próprio e passaram a ser criadas durante o uso de pessoas e transações.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'Foi tomada a decisão de ocultar o país na interface e inferir o código interno apenas a partir do idioma, quando aplicável.',
                    ],
                ],
            ],
            [
                'title' => 'Changelog e fase alfa',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionada a página Changelog com o histórico do produto, agrupado por data e por tema.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foi incluído um aviso global de fase alfa com acesso rápido ao changelog.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'Foi decidido manter o Finba.se em fase alfa até a primeira release beta para testadores.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-06',
        'version' => null,
        'title' => 'Transações e empréstimos',
        'groups' => [
            [
                'title' => 'Transações',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'O cadastro de transações foi simplificado, com regras de validação reunidas em um único fluxo.',
                    ],
                    [
                        'type' => 'removed',
                        'text' => 'Foi removido código legado de pagamento e categorias que já não fazia parte do fluxo atual.',
                    ],
                ],
            ],
            [
                'title' => 'Empréstimos e contas a receber',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'A área de empréstimos e contas a receber ganhou cartões responsivos para leitura no celular.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-03',
        'version' => null,
        'title' => 'Contas a receber',
        'groups' => [
            [
                'title' => 'Receitas',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionada a opção de registrar receitas com recebimento imediato ou posterior.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Receitas com recebimento posterior passam a gerar contas a receber automaticamente.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Empréstimos e pessoas passaram a suportar o vínculo com vendas a prazo.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-02',
        'version' => null,
        'title' => 'Dashboard, dízimos e perfil avançado',
        'groups' => [
            [
                'title' => 'Dashboard',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado o Dashboard inicial com filtros por mês e ano.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Agora são exibidos receitas, despesas e saldo do período selecionado.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foi incluído o resumo por categoria, transações recentes e atalhos para a listagem.',
                    ],
                ],
            ],
            [
                'title' => 'Dízimos, ofertas e primícias',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado o resumo de dízimos no dashboard, com cálculo mensal e confirmação de entrega.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Agora é possível registrar a entrega de dízimo, oferta e primícia com base nas receitas do período.',
                    ],
                ],
            ],
            [
                'title' => 'Perfil',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado o modo avançado, desbloqueando pessoas, empréstimos, subcategorias e recursos extras.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Contas a receber passou a ser um recurso opcional, disponível apenas com o modo avançado ativo.',
                    ],
                ],
            ],
            [
                'title' => 'Infraestrutura',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi configurado o envio de e-mails transacionais da aplicação.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Foram ampliadas as classificações de finalidade e tipo de empréstimo no domínio financeiro.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-18',
        'version' => null,
        'title' => 'Domínio financeiro tipado',
        'groups' => [
            [
                'title' => 'Modelagem',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'Empréstimos, recorrências e transações passaram a usar tipos fixos em vez de valores soltos.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Formulários e listagens foram alinhados a esses tipos, reduzindo inconsistências entre telas.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-15',
        'version' => null,
        'title' => 'Detalhes de transações',
        'groups' => [
            [
                'title' => 'Transações',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'A visualização de transações foi reorganizada para destacar valor, data e informações complementares.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Também foram ajustados os cartões e o modal de detalhe para leitura confortável no celular.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-14',
        'version' => null,
        'title' => 'Cidades e navegação',
        'groups' => [
            [
                'title' => 'Localização',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Pessoas e transações passaram a aceitar vínculo com cidade.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'A cidade passou a aparecer nos cartões móveis de transações.',
                    ],
                ],
            ],
            [
                'title' => 'Navegação',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'A ordem e os ícones do menu lateral foram revisados para agrupar melhor as áreas do app.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'A busca na listagem de transações foi ajustada para ocupar melhor o espaço em telas pequenas.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-13',
        'version' => null,
        'title' => 'Transações no celular',
        'groups' => [
            [
                'title' => 'Transações',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'A listagem de transações passou a usar cartões no celular em vez de tabela.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Formulários e detalhes ganharam campos de categoria e finalidade mais completos.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foi incluído um modal de visualização com destaque para título e valor.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-12',
        'version' => null,
        'title' => 'Organização do código',
        'groups' => [
            [
                'title' => 'Arquitetura',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'A estrutura interna do projeto foi reorganizada antes da expansão da área de transações.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-06-11',
        'version' => null,
        'title' => 'Base de dados e lembretes',
        'groups' => [
            [
                'title' => 'Domínio financeiro',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foram criadas as bases de usuários, categorias, pessoas, empréstimos, transações, dízimos e lembretes.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foram definidos os tipos iniciais para recorrência, finalidade e canais de lembrete.',
                    ],
                ],
            ],
            [
                'title' => 'Interface',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'A área de transações recebeu estilos iniciais pensados para uso em dispositivos móveis.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'O perfil ganhou ajustes de espaçamento para telas pequenas.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-05-18',
        'version' => null,
        'title' => 'Painel administrativo',
        'groups' => [
            [
                'title' => 'Filament',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'O painel Filament foi ampliado com avisos de expiração e relacionamentos entre os primeiros recursos.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-05-16',
        'version' => null,
        'title' => 'Fundação do projeto Finba.se',
        'groups' => [
            [
                'title' => 'Início do projeto',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi iniciado o Finba.se com Laravel, Filament, autenticação e a estrutura inicial do controle financeiro pessoal.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foi publicada a documentação inicial com propósito, escopo e stack do projeto.',
                    ],
                ],
            ],
        ],
    ],
];
