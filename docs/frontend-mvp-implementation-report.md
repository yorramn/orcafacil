# Orca Fácil CRM — relatório frontend do MVP

Data: 23/10/2026

Este relatório compara a implementação frontend com o blueprint `docs/prototypes/orca-facil-mvp-ux-blueprint.md`. O escopo ficou limitado a `resources/js/`, `resources/css/` e esta documentação; nenhuma rota, controller, migration, teste backend, protótipo ou arquivo do design system foi alterado.

## Cobertura por tela

| Blueprint | Implementação frontend | Estado |
| --- | --- | --- |
| A01–A06 | Fluxos existentes de login, registro, verificação, recuperação e 2FA; novo suporte de configurações 2FA | Coberto visualmente; endpoints seguem contratos existentes |
| A07 | `Pages/Settings/Profile.vue` | Coberto |
| B01 | `Pages/Onboarding/Tenant.vue` | Coberto |
| B02 | `Pages/Settings/Team.vue` | Coberto com bloqueio do plano Básico |
| C01 | `Pages/Dashboard.vue` | Props Inertia reais opcionais, loading, vazio, erro/retry, KPIs, atividade e atalhos |
| C02 | `Pages/Clients/Index.vue` | Lista desktop/cards mobile, busca, filtros, estados e arquivamento representado |
| C03 | `Pages/Clients/Show.vue` | Contato, histórico, atividade, estado não encontrado e ações |
| C04 | `Pages/Catalog/Index.vue`, `Edit.vue` | CRUD visual, ativo/inativo e preço em BRL |
| D01 | `Pages/Quotes/Index.vue` | Lista, filtros, cards mobile, estados e link para criação/versões |
| D02 | `Pages/Quotes/Create.vue`, `Edit.vue`, `Show.vue` | Itens dinâmicos, snapshot informativo, notas, anexos, resumo, revisão e envio |
| D03 | `Pages/Quotes/Versions.vue` | Versões, eventos, copiar/revogar/reenviar com token mascarado |
| D04 | `Pages/Public/Quote.vue` | Shell público sem sidebar, validade/revogação/erro, nome, aprovar/recusar e idempotência visual |
| E01 | `Pages/Services/Index.vue`, `Create.vue` | Lista, status, prazo, responsável e criação |
| E02 | `Pages/Services/Show.vue` | Status, endereço snapshot, checklist, notas, anexos/fotos e histórico |
| F01 | `Pages/Receivables/Index.vue` | KPIs, filtros, cards mobile e baixa via endpoint do backend |
| F02 | `Pages/Reports/Index.vue` | Bloqueio por plano, loading/erro/vazio, métricas e tabela alternativa acessível |
| G01 | `Pages/Settings/Billing.vue` | Plano, status, uso, limites, escolha/cancelamento representados |
| G02 | `Pages/Activity/Index.vue` | Timeline/lista, filtros, não lida/lida e proteção contra segredos |

## Decisões aplicadas

- Poppins foi mantida para títulos e labels principais; Inter para leitura, tabelas, datas e valores.
- O shell operacional usa sidebar persistente em desktop, menu acessível no mobile e barra inferior para navegação rápida.
- Tabelas viram cards em telas estreitas; os breakpoints de 360/390px usam gutter de 16px e não dependem de scroll horizontal.
- Os estados compartilhados ficam em `Components/DataState.vue`, `StatusBadge.vue` e `ResourceList.vue`.
- Pinia foi inicializado no bootstrap existente e limitado a UI, notificações, onboarding e progresso de 2FA; os registros permanecem nas props Inertia.
- Formulários usam `useForm`, labels explícitos, `autocomplete`, foco visível, estados pendentes e mensagens de erro do servidor.
- A tela pública de orçamento não renderiza o `AppShell` autenticado e não ecoa o token.

## Integração com o backend

As páginas usam os caminhos da especificação (`/clients`, `/quotes`, `/services`, `/receivables`, `/reports`, `/activity`, `/settings/*` e `/public/quotes/{token}`). O backend agora expõe também as mutações operacionais de versões/revogação/reenvio, tarefas/notas/ocorrências/anexos e cancelamento de recebíveis, além dos aliases `/catalog*` usados pela tela de catálogo. Nenhum dado falso foi introduzido.

## Validação

`npm run build` foi executado com sucesso após a implementação. O primeiro disparo encontrou `esbuild spawn EPERM` no sandbox do Windows; a segunda execução, com a permissão de build necessária, transformou 1490 módulos e concluiu o bundle Vite.

A validação integrada no navegador deve verificar especificamente 360px, 390px, desktop, foco após navegação, filtros preservados na URL, confirmação de arquivamento/baixa/revogação, aliases de catálogo e estados de rate limit do link público. A validação de backend confirmou `route:list`, migrations e Pint; os testes Feature continuam condicionados à habilitação de `pdo_sqlite` ou à execução com uma configuração de banco de testes MySQL.
