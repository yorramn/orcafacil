# CRM backend

## Estado

O backend mantém clientes, catálogo, orçamentos, snapshots, status, link público e aprovação existentes. Esta etapa adiciona os módulos operacionais sem reimplementar esses fluxos.

- Serviços: conversão transacional e idempotente de orçamento `approved`, snapshot do endereço do cliente, responsável, datas, tarefas, notas, ocorrências e anexos privados com MIME/tamanho/tenant. Todas as referências (`client_id`, `catalog_item_id`, `quote_id`, `assigned_to` e anexos) são revalidadas contra o tenant no Form Request, Policy e Action/Service.
- Tarefas: `PATCH /services/{service}/tasks/{task}/status` permite concluir e reabrir somente para owner/admin do tenant; preenche/limpa `completed_by` e `completed_at` e grava `ActivityLog`.
- Orçamentos: valores são inteiros em centavos; quantidades são strings normalizadas até três casas e o arredondamento da linha é feito por aritmética inteira, sem `float`. Há rotas para versões, revogação e reenvio do link público.
- Reenvio: `QuotePolicy` e `QuoteService` aceitam somente `draft`, `sent` e `viewed`; estados terminais nunca são reabertos. O envio dispara `QuoteSent` enfileirável para o e-mail do cliente com link público tokenizado.
- Anexos de orçamento: `POST /quotes/{quote}/attachments` e `DELETE /attachments/{attachment}` usam o disk privado, validação de MIME/tamanho, `QuotePolicy`/`AttachmentPolicy` e auditoria sem expor tokens.
- O contrato canônico de criação/edição usa `discount_cents`, `extra_cents` e `items.*.unit_price_cents` como inteiros. O request ainda normaliza os nomes legados da tela sem converter valores usando `float`; `tenant_id` nunca é aceito do request.
- A criação de quote fornece `clients` e `catalog` ao controller/view, e a abertura pública registra o primeiro `viewed` em `quote_events` dentro da mesma transação que altera o status.
- Recebíveis: valores em centavos, estados `pending/paid/overdue/canceled`, baixa com lock e idempotência, cancelamento histórico, resumo do dashboard e CSV filtrado pelo tenant.
- Billing: `BillingProvider`, `FakeBillingProvider`, planos Básico/Completo, trial, troca, cancelamento, limites server-side e webhook HMAC idempotente. Cancelamento nunca remove dados.
- Billing exige policy de gestão para owner/admin; Reports exige owner/admin com plano Completo ativo ou em trial. Activity usa policy própria de membro ativo.
- Billing retorna `users`, `clients`, `quotes` e `services` em `usage`, alinhado ao contrato consumido pela tela; `quotes` é o limite mensal e `services` o limite de serviços abertos.
- Equipe: convite com token aleatório armazenado somente por hash, expiração e notificação de e-mail enfileirável para qualquer endereço convidado; papéis owner/admin/member e remoção por status preservam autoria.
- Convites: `GET /invitations/accept?token=...` é autenticado, limitado por `throttle:invitations`, valida expiração e e-mail do usuário e retorna somente os dados mínimos para o `POST /invitations/accept`; usuários não autenticados seguem o redirecionamento padrão de login.
- Segurança: ativar 2FA exige `current_password` antes da geração do segredo e um código de seis dígitos no endpoint de confirmação; o segredo permanece criptografado pelo Fortify. Regenerar recovery codes exige novamente `current_password` e TOTP/recovery code anterior; códigos não são logados. Membros e convites são paginados, assim como os históricos de cliente.
- Relatórios: período, vendas aprovadas por data, status de propostas, conversão em basis points, previsto/recebido/vencido em centavos, serviços por status e clientes ordenados por volume. `GET /reports/export` é CSV em stream, protegido pela mesma policy/plano e limitado ao tenant atual, sem tokens ou IDs internos.
- Auditoria: `ActivityLog` e `QuoteEvent` preenchem `request_id` e `ip_hash` automaticamente; o IP nunca é armazenado em claro e nenhum segredo é gravado.
- Dados públicos de orçamento são um array mínimo, sem serialização do modelo `Client` completo.
- Link público: `GET /public/quotes/{token}` envia o token e estados `valid/expired/revoked/error` para `Public/Quote`; aprovação/recusa retornam a mesma página com `state=approved|rejected`. Anexos públicos expõem somente nome, MIME, tamanho e rota autorizada pelo token; o arquivo permanece no disk privado.
- Arquivos: o disk `private` usa `storage/app/private`, `visibility=private` e `url=null`; fotos e anexos não dependem do disk público. Uploads têm limite de MIME/tamanho e são testados com storage fake.
- Perfil e tenant: `GET /profile` renderiza `Settings/Profile`; `/onboarding/tenant` cria o primeiro tenant por Action e `/tenant/select/{tenant}` seleciona somente uma associação ativa autorizada. O endpoint diagnóstico `/tenant/context` foi removido.
- Dashboard: KPIs de propostas, aprovados, serviços e recebimentos; atividade recente e atalhos retornados como props Inertia.
- Jobs/notifications: atualização assíncrona de vencidos e convites são enfileirados. O envio de orçamento é síncrono para confirmar sucesso somente após a aceitação pelo SMTP; falhas preservam o orçamento sem alteração e exibem erro no formulário. Em produção, mantenha um worker ativo (`php artisan queue:work --sleep=3 --tries=3`) para os demais jobs e monitore jobs falhos.

## Tenancy e autorização

O tenant é resolvido pela associação ativa em `TenantContext`; valores enviados pelo navegador não definem o tenant. Controllers aplicam policies e actions consultam novamente `tenant_id` antes de locks ou mutações. A criação de quotes bloqueia a linha do tenant dentro da transação para serializar o limite mensal e o próximo número; a constraint única por tenant/número permanece a última barreira. Anexos ficam no disk privado e são removidos somente após ability `update` no serviço pai, com `ActivityLog`.

## Rotas principais

`/quotes`, `/quotes/{quote}/convert`, `/quotes/{quote}/versions`, `/quotes/{quote}/revoke-link`, `/quotes/{quote}/resend-link`, `/quotes/{quote}/attachments`, `/attachments/{attachment}`, `/services`, `/services/{service}/status`, `/services/{service}/tasks`, `/services/{service}/tasks/{task}/status`, `/services/{service}/notes`, `/services/{service}/occurrences`, `/services/{service}/attachments`, `/receivables`, `/receivables/{receivable}/payments`, `/receivables/{receivable}/cancel`, `/receivables/export`, `/reports`, `/reports/export`, `/activity`, `/billing`, `/billing/plan`, `/billing/cancel`, `/billing/webhook`, `/settings/security/two-factor`, `/settings/team` e `/settings/team/invitations`.

## Validação

As migrations `000004`, `000005` e `000006` executam em MySQL; `migrate:status` confirma todas como `Ran`. `route:list` expõe os caminhos de quotes, operações, recebíveis, reports, billing, equipe e 2FA. Pint e PHPUnit são executados com PHP 8.2.24 neste ambiente.

`TRUSTED_PROXIES` aceita somente IPs/CIDRs explícitos separados por vírgula. Vazio ou `*` não confia em nenhum proxy, evitando falsificação de IP em produção sem impedir deploys que configurem os proxies reais.

Em desenvolvimento, `.env.example` mantém `APP_DEBUG=true` e `SESSION_SECURE_COOKIE=false` para suportar HTTP local. Em produção, use `APP_DEBUG=false`, HTTPS e `SESSION_SECURE_COOKIE=true`, com `PRIVATE_FILESYSTEM_DISK=private` persistente.

`BILLING_WEBHOOK_SECRET` deve ser um segredo forte, armazenado somente no ambiente de produção e nunca publicado em logs, backups de código ou documentação.

Backups devem incluir banco e `storage/app/private`, ser criptografados, ter retenção definida e ser testados periodicamente com restauração em ambiente isolado. Mantenha `APP_KEY` estável; a perda da chave impede a leitura de dados criptografados legados.

## Próximos cuidados de produção

Configurar `BILLING_WEBHOOK_SECRET`, worker da fila, scheduler para `MarkOverdueReceivables`, storage privado com URLs temporárias, antivírus de upload e provider de cobrança real antes de produção. O job roda diariamente às 01:00, itera por tenant e usa transação; o webhook grava `event_id` único, lock e atualização transacional de plano/status. O provider fake é deliberadamente o único provider registrado nesta etapa.
