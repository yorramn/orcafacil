# OrçaFácil — fundação de autenticação e autorização

O projeto usa Laravel 12, PHP `^8.2` como mínimo oficial, sessão `web`, Fortify, Inertia/Vue 3, Pinia e Tailwind. O PHP 8.1 presente no host não é compatível com Laravel 12 e não deve ser usado para Composer, Artisan ou testes; o ambiente validado utiliza PHP 8.2.24.

## Setup local

1. Copie `.env.example` para `.env` e gere uma chave com `php artisan key:generate`.
2. Configure `DB_*`, `APP_URL`, SMTP e `SESSION_DRIVER=database` para ambientes persistentes.
3. Execute `php artisan migrate`.
4. Execute `npm install` e `npm run build`.

Nunca commite `.env`, `APP_KEY`, senha SMTP, segredo TOTP ou códigos de recuperação.

## Fluxo protegido

Cadastro cria o usuário e um tenant inicial com papel `owner`. A aplicação exige sessão autenticada, e-mail verificado, TOTP confirmado e desafio 2FA concluído antes de liberar rotas operacionais. O middleware `tenant` resolve o tenant pela associação ativa do usuário; `tenant_id` enviado pelo navegador não é usado para escolher o contexto.

`TenantContext` é singleton por request e é o ponto de extensão para recursos CRM futuros. Entidades operacionais devem usar `tenant_id`, a trait `BelongsToTenant`, consultas escopadas ao contexto e Policies; `user_id` continua representando autoria/ator e não substitui tenancy.

As configurações de segurança são acessíveis por `GET /settings/security/two-factor`. O contrato de desativação usa `current_password` e `code`; por compatibilidade com a tela existente, `password` é normalizado server-side para `current_password`. Nenhum formulário operacional expõe `tenant_id`.

## Validação

Use PHP 8.2.24 do Laragon neste ambiente, pois ele possui `pdo_sqlite`. Os comandos principais são `php artisan migrate:status`, `php artisan test --no-coverage`, `vendor/bin/pint --test` e `npm run build`.

O PHP 8.1 do host foi registrado como bloqueio de compatibilidade: executar Laravel 12 com ele falha na resolução de dependências. O PHP 8.2.35 disponível para Composer não possui `pdo_sqlite`; por isso os testes SQLite usam explicitamente o PHP 8.2.24.
