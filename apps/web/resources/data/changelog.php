<?php

return [
    [
        'date' => '2026-07-21',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Reorganização do repositório em monorepo',
        'groups' => [
            [
                'title' => 'Estrutura do repositório',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'O repositório foi reorganizado em monorepo: a aplicação Laravel passou para apps/web e a API Geo para apps/geo.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'A raiz do projeto foi limpa e padronizada, com documentação central descrevendo o layout e a relação entre os serviços.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'A organização passa a favorecer crescimento de longo prazo e a inclusão de novos serviços, com fronteiras claras de build, teste e deploy.',
                    ],
                ],
            ],
            [
                'title' => 'CI, Docker e deploy',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'Scripts de deploy, contextos Docker e workflows de CI foram atualizados para a nova estrutura de diretórios.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'A documentação operacional de web e geo foi alinhada aos caminhos do monorepo.',
                    ],
                    [
                        'type' => 'internal',
                        'visibility' => 'authenticated',
                        'text' => 'A validação pós-migração confirmou que as suítes de testes de apps/web e apps/geo passam de forma independente.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-20',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Infraestrutura e automação de deploy',
        'groups' => [
            [
                'title' => 'Caminho para deploys automatizados',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi preparada a base de GitHub Actions, autenticação OIDC e publicação em Artifact Registry para deploys automatizados.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Scripts e documentação de deploy na nuvem foram refinados para reduzir passos manuais e padronizar o caminho até produção.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'A infraestrutura passa a priorizar deploy contínuo com identidade federada, sem depender de chaves de serviço de longa duração.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-19',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'featured' => true,
        'featured_label' => 'Marco arquitetural',
        'featured_summary' => 'Este é o ponto em que o Finba.se evoluiu de uma aplicação Laravel única para uma plataforma multi-serviço.',
        'title' => 'Marco arquitetural: extração da plataforma geográfica',
        'groups' => [
            [
                'title' => 'Da aplicação única à plataforma multi-serviço',
                'items' => [
                    [
                        'type' => 'decision',
                        'text' => 'O subsistema geográfico foi extraído do Laravel para um serviço independente — o ponto em que o Finba.se deixou de ser uma aplicação única e passou a ser uma plataforma multi-serviço.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Foi criada a API Geo em Go, com catálogo SQLite próprio, contrato REST estável e deploy independente do aplicativo web.',
                    ],
                ],
            ],
            [
                'title' => 'API, acesso e operação',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'O serviço expõe países, regiões e cidades, com endpoints de health e version, empacotamento Docker e preparação para Cloud Run.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Foram introduzidos os níveis de acesso Public, Trusted e Internal, com autenticação por API key, pipeline de middleware e rate limiting por cliente.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Documentação completa e suíte automatizada de testes acompanham o serviço, preservando um contrato de API estável para o Laravel consumir.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-18',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Geo API pronta para produção',
        'groups' => [
            [
                'title' => 'Modelo de execução no Cloud Run',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'A imagem de produção passou a usar base distroless, com encerramento gracioso, timeouts HTTP configuráveis e endpoints dedicados de health e version.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'O empacotamento Docker, o endurecimento de runtime e a documentação de deploy foram alinhados ao modelo operacional do Cloud Run.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'O serviço geográfico passa a ser tratado como componente de produção: empacotamento imutável e superfície operacional explícita.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-17',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Abstração Geo no Laravel',
        'groups' => [
            [
                'title' => 'Camada Support/Geo',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi introduzida a arquitetura Support/Geo no Laravel, com manager, facade, camada de DTOs, contratos, exceções tipadas e normalização de respostas.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Uma camada de cache e um fluxo simplificado de localização do usuário reduziram a complexidade geográfica dentro da aplicação.',
                    ],
                    [
                        'type' => 'removed',
                        'text' => 'Foi removido o acoplamento desnecessário a um dataset geográfico local, preparando o consumo de um serviço externo dedicado.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'A aplicação web deixa de estar fortemente acoplada a um catálogo geográfico embutido e passa a depender de um contrato Geo explícito.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-16',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Arquitetura e preparação geográfica',
        'groups' => [
            [
                'title' => 'Refinos estruturais',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'Seguiram os refactors estruturais, com melhorias no modelo de localização e nos locales, além de limpeza interna da aplicação e continuidade do ajuste ao Laravel 13.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'O domínio, a documentação e a experiência de desenvolvimento avançaram em direção a um serviço geográfico externo, reduzindo dependências de catálogo local.',
                    ],
                    [
                        'type' => 'internal',
                        'visibility' => 'public',
                        'text' => 'A organização do código foi preparada para a próxima etapa: a extração da plataforma Geo.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-15',
        'version' => '0.1.0-beta',
        'badge' => 'Beta',
        'title' => 'Lançamento da primeira versão beta',
        'groups' => [
            [
                'title' => 'Lançamento',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'A primeira versão beta do Finba.se foi publicada.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'A aplicação passou a operar em produção.',
                    ],
                    [
                        'type' => 'fixed',
                        'text' => 'Corrigido o login com Google atrás do proxy de produção, para abrir a autorização com navegação completa do navegador.',
                    ],
                    [
                        'type' => 'fixed',
                        'text' => 'Corrigido o catálogo de países em produção, carregado em memória sem depender de tabela no PostgreSQL.',
                    ],
                    [
                        'type' => 'decision',
                        'text' => 'O foco do desenvolvimento passa a ser estabilidade, refinamentos de experiência e evolução baseada no feedback dos usuários.',
                    ],
                ],
            ],
        ],
    ],
    [
        'date' => '2026-07-14',
        'version' => null,
        'badge' => 'Em desenvolvimento',
        'title' => 'Parcelamentos, PWA e moeda por país',
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
            [
                'title' => 'Moeda e interface',
                'items' => [
                    [
                        'type' => 'changed',
                        'text' => 'Valores monetários passaram a seguir a moeda do país configurado no perfil, em vez de ficarem fixos em real.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'Dashboard, transações, contas a receber e campos de valor usam a mesma formatação de moeda e locale do usuário.',
                    ],
                    [
                        'type' => 'fixed',
                        'text' => 'Foi ajustado o contraste dos botões verdes e o estado desabilitado do resumo de dízimos.',
                    ],
                    [
                        'type' => 'fixed',
                        'text' => 'Foi removido o indicador de seta que aparecia ao passar o mouse nos cards de receitas e despesas.',
                    ],
                ],
            ],
            [
                'title' => 'Comunicação e transparência',
                'items' => [
                    [
                        'type' => 'added',
                        'text' => 'Foi adicionado um canal interno para envio de problemas e sugestões.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Os relatos passaram a ser armazenados com informações técnicas opcionais e notificação por e-mail.',
                    ],
                    [
                        'type' => 'added',
                        'text' => 'Também foi criada uma página sobre o projeto com autoria, status e links públicos.',
                    ],
                    [
                        'type' => 'changed',
                        'text' => 'O armazenamento de anexos foi preparado para utilizar um serviço persistente fora do servidor da aplicação.',
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
