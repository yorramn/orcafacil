# Protótipos navegáveis do OrçaFácil

Catálogo original de revisão visual em `/prototypes?version=original`, construída a partir da especificação CRM v1.0 de 23/10/2026. O inventário executável está em `resources/js/Prototypes/screens.ts`.

São **93 telas e estados**, todos responsivos. Consulte também [o inventário com endereços diretos](INVENTARIO-DE-TELAS.md).

O piloto Taildash de 26/10/2026 está em `/prototypes`. Seu design system está em `design-system/taildash/MASTER.md`.

## Como revisar

1. Abra `/prototypes?version=original` com Laravel e Vite em execução.
2. Use o catálogo de telas para escolher uma funcionalidade. Cada tela possui um endereço com fragmento, por exemplo `/prototypes?version=original#login`.
3. Siga os botões para percorrer os fluxos demonstrativos ou abra diretamente outra tela pelo catálogo.
4. Redimensione o navegador para conferir a composição em desktop e em 390 ou 360 px.

## Cobertura

- Acesso: login, cadastro, recuperação e redefinição de senha, confirmação de senha, verificação de e-mail e link expirado.
- Autenticação em duas etapas: ativação, confirmação, desafio, recuperação, códigos e desativação.
- Onboarding e dashboard.
- Clientes: listagem, cadastro, edição, detalhe e histórico, arquivamento.
- Catálogo: listagem, cadastro, edição e inativação.
- Orçamentos: listagem, criação, edição, detalhe, revisão, envio, versões, nova versão, duplicação, cancelamento, link e revogação, conversão e identidade da proposta.
- Proposta pública: apresentação, aprovação, recusa, respostas registradas, expiração, revogação e excesso de tentativas.
- Serviços: listagem, criação, detalhe, checklist, notas, ocorrências, fotos, anexos e alteração de status.
- Financeiro: listagem de recebimentos, criação, baixa e cancelamento de parcela.
- Relatórios e exportação CSV.
- Equipe: listagem, convite, aceite, convite expirado, papéis e remoção.
- Assinatura: plano, uso, troca, cancelamento, avaliação, pagamento pendente, cancelado, expirado, limite e recurso premium.
- Conta: perfil, preferências, senha, alteração e confirmação de e-mail, segurança, privacidade, cópia dos dados e solicitação de exclusão.
- Atividade, lembretes, solicitação de suporte, autorização e histórico de acesso do suporte.

## Decisões de prototipação

O layout preserva a identidade navy/azul e as fontes do projeto. A referência Cleopatra orienta a organização de um painel administrativo compacto; não foi adicionada uma dependência do template. Componentes por domínio compartilham elementos visuais e apresentam conteúdo específico para cada tela.

Valores, pessoas, contatos, prazos de tolerância, validade de convites, quantidades disponíveis no Completo, datas, códigos e política operacional do suporte são exemplos de apresentação. A especificação não fixa todos esses valores; devem ser definidos e validados antes de implementar regras reais. As telas de privacidade representam o pedido e sua confirmação, sem determinar prazos legais de retenção.

## Limites

Esta área usa dados fictícios e interações locais. Não autentica, não envia e-mails, não cobra, não altera registros da aplicação e não implementa as garantias de segurança ou de isolamento entre empresas descritas na especificação. O código de autenticação e os códigos de recuperação são ilustrativos. Uma atualização da página reinicia a demonstração.

O rascunho do orçamento, as tarefas, as notas e os nomes dos anexos permanecem na memória durante a navegação entre as telas. A revisão utiliza os valores editados, enquanto o detalhe da proposta aprovada apresenta o exemplo original preservado. Nenhuma senha ou código é persistido no navegador.

A rota é acessível sem login para permitir a revisão das telas. Ela não deve receber dados reais.

## Verificação realizada em 23/10/2026

- Compilação de produção concluída, incluindo a folha de estilos da galeria.
- 93 telas verificadas no navegador em 1440, 390 e 360 px: 279 combinações, todas com título principal único, sem rolagem horizontal e sem erros de execução de JavaScript.
- Capturas visuais revisadas para login desktop e mobile, dashboard desktop, clientes mobile, criação de orçamento mobile e proposta pública mobile.
- 16 verificações de interação concluídas: campos obrigatórios; resumo de erros; login e desafio 2FA ilustrativo; busca e ausência de resultados; checklist; nova tarefa; nova nota; persistência entre abas; edição e revisão de valores; preservação da proposta aprovada; aprovação pública; CSV; busca da galeria; menu mobile; recuperação após erro.
- Verificações básicas existentes de inicialização da aplicação: 2 testes passaram.

Essas verificações comprovam o comportamento do protótipo. Não constituem auditoria de segurança, teste de autenticação real, integração financeira ou validação completa de acessibilidade.
