# OrçaFácil — TailAdmin Vue Design System

Design system do produto baseado no TailAdmin Vue Free incluído em
`resources/js/TailAdminSource`. A aplicação usa Tailwind CSS 4 e conserva
Vue, Inertia, rotas, dados, validações e fluxos existentes. As classes e os
tokens originais são a referência visual; os componentes de negócio continuam
adaptados ao conteúdo e aos contratos do OrçaFácil.

## Fundamentos visuais

- Fonte: Outfit (100–900), carregada pelo stylesheet do modelo.
- Primária: `brand-500` `#465FFF`; variações oficiais `brand-25` a `brand-950`.
- Fundo claro: `gray-50` `#F9FAFB`; superfícies brancas com borda `gray-200`.
- Texto: títulos `gray-900`; texto secundário `gray-500`; rótulos discretos `gray-400`.
- Sidebar fixa de 290px em desktop, header de 72px; em telas menores, drawer acessível.
- Cantos TailAdmin (`rounded-lg`/`rounded-[10px]` para controles, `rounded-xl` para painéis),
  bordas discretas e sombras `shadow-theme-sm` / `shadow-theme-lg`.
- Dark mode utiliza variantes `dark:` e classe `.dark`.

## Tokens e semântica

Usar os nomes do Tailwind/TailAdmin no markup (`bg-brand-500`, `text-gray-900`,
`border-gray-200`, `shadow-theme-sm`). Tokens `td-*` permanecem como aliases de
compatibilidade durante a migração de telas preexistentes; novos componentes
devem preferir tokens TailAdmin.

| Papel | Token | Valor |
|---|---|---|
| Ação, link e seleção | `brand-500` | `#465FFF` |
| Hover de ação | `brand-600` | `#3641F5` |
| Tinta escura de marca | `brand-950` | `#161950` |
| Canvas claro | `gray-50` | `#F9FAFB` |
| Texto principal | `gray-900` | `#101828` |
| Texto auxiliar | `gray-500` | `#667085` |
| Borda padrão | `gray-200` | `#E4E7EC` |
| Sucesso | `success-500` | consultar `@theme` de `tailadmin-source.css` |
| Atenção | `warning-500` | consultar `@theme` de `tailadmin-source.css` |
| Erro | `error-500` | consultar `@theme` de `tailadmin-source.css` |

As escalas completas de cor, tipografia, radius e sombra residem no
`resources/css/tailadmin-source.css`; não duplicar nem redefinir valores locais.

## Layout e responsividade

- Breakpoints padrão Tailwind: `sm`, `md`, `lg`, `xl`, `2xl`.
- Conteúdo deve encolher com `min-w-0`; tabelas recebem `overflow-x-auto`.
- Desktop: sidebar persistente e header alinhado após a sidebar.
- Mobile: drawer com foco contido, fechamento por Escape e botão de abertura.
- Controles de ação são compactos (44px de alvo mínimo, `text-sm`, `px-4 py-2.5`);
  não usar botões primários de 50px com fonte de corpo.
- Formulários e autenticação: uma coluna em mobile, painel dividido em desktop.
- Respeitar áreas seguras, foco visível, preferência de movimento reduzido e
  contraste; não depender somente da cor para comunicar estados.

## Componentes compartilhados

- `AppShell.vue`: navegação Inertia, contexto da operação, logout e shell responsivo.
- `AuthShell.vue`: layout de autenticação split-screen com painel brand-950.
- `ResourceList.vue`: busca/filtros e cartão de resultados; usa o primitivo
  `components/ui/table/Table.vue` copiado do modelo e tabelas mobile em cartões.
- Formulários de negócio usam os componentes compartilhados existentes e classes
  Tailwind do modelo para controles nativos, preservando v-model/Inertia.
- Componentes de domínio em `resources/js/Pages` continuam responsáveis por
  textos, campos, tabelas, estados e ações específicas do fluxo.
- Código original, ícones, utilitários e imagens de referência estão sob
  `resources/js/TailAdminSource` e `public/images`; manter a licença incluída em
  `resources/js/TailAdminSource/LICENSE`.

## Padrões de implementação

```html
<section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
  ...
</section>
<button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">
  Salvar
</button>
```

Preservar `useForm`, endpoints, validação de servidor, autorização, estados de
carregamento/erro e navegação Inertia. Usar labels explícitos e `aria-*` em
controles; mensagens de erro devem permanecer associadas aos campos.
