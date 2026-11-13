# Orca Fácil CRM — complete MVP UX/UI prototype blueprint

**Source of truth:** the two supplied specifications dated 2026-10-23. This document is a UX/UI prototype contract only: it does not prescribe Laravel controllers, migrations, policies or API implementation.

## How to read this blueprint

Each functionality has exactly two screen specifications: **Desktop** and **Mobile**. “Role” is the intended visible authorization context; the server remains authoritative. The route/state names are proposed Inertia route states and can be mapped to the final route names without changing the UX contract.

## Coverage inventory

| ID | Functionality | Routes/states | Role | Desktop | Mobile |
|---|---|---|---|:---:|:---:|
| A01 | Login | `/login` | guest | ✓ | ✓ |
| A02 | Registration | `/register` | guest | ✓ | ✓ |
| A03 | Email verification | `/email/verify` | authenticated, unverified | ✓ | ✓ |
| A04 | Password recovery/reset | `/forgot-password`, `/reset-password/{token}` | guest | ✓ | ✓ |
| A05 | 2FA challenge/recovery | `/two-factor-challenge` | authenticated challenge | ✓ | ✓ |
| A06 | 2FA setup/recovery codes | `/settings/security/two-factor` | verified | ✓ | ✓ |
| A07 | Profile/password/email/delete | `/profile` | verified | ✓ | ✓ |
| B01 | Tenant onboarding | `/onboarding/tenant` | new owner | ✓ | ✓ |
| B02 | Team, invites and roles | `/settings/team` | owner/admin | ✓ | ✓ |
| C01 | Dashboard | `/dashboard` | owner/admin/member | ✓ | ✓ |
| C02 | Clients list and filters | `/clients` | authorized tenant member | ✓ | ✓ |
| C03 | Client detail/history | `/clients/{client}` | authorized tenant member | ✓ | ✓ |
| C04 | Catalog | `/catalog` | owner/admin/member | ✓ | ✓ |
| D01 | Quote list | `/quotes` | authorized tenant member | ✓ | ✓ |
| D02 | Quote create/edit/review | `/quotes/create`, `/quotes/{quote}/edit` | authorized creator/editor | ✓ | ✓ |
| D03 | Quote versions/link | `/quotes/{quote}/versions` | authorized tenant member | ✓ | ✓ |
| D04 | Public quote approval | `/p/{token}` | public recipient | ✓ | ✓ |
| E01 | Services list | `/services` | authorized tenant member | ✓ | ✓ |
| E02 | Service detail/execution | `/services/{service}` | assigned/member/admin | ✓ | ✓ |
| F01 | Receivables/settlement | `/receivables` | authorized tenant member | ✓ | ✓ |
| F02 | Reports/export | `/reports` | Complete owner/admin | ✓ | ✓ |
| G01 | Plan/usage/billing | `/settings/billing` | owner | ✓ | ✓ |
| G02 | Activity/notifications | `/activity` | owner/admin/member | ✓ | ✓ |

Total: **23 functionalities × exactly 2 screen specs = 46 screen specs**.

## Global interaction and visual tokens

Use [design-system/orca-f-cil-crm/MASTER.md](../../design-system/orca-f-cil-crm/MASTER.md) as the reusable source of truth. The short form is: Poppins headings + Inter data/UI; navy `#0F172A`; CTA blue `#0369A1`; background `#F8FAFC`; cards `#FFFFFF`; 4/8px spacing rhythm; 44px minimum controls; visible 2–4px focus ring; no horizontal scroll at 360/390px; motion 150–300ms and disabled under `prefers-reduced-motion`.

## Screen specifications

### A01 — Login

#### Desktop screen

- **Route/state/role:** `GET /login`, guest; default, invalid credentials, rate-limited and just-logged-out states.
- **Purpose/task:** sign in with email/password, optionally remember the session, then continue to email verification, 2FA or dashboard.
- **Wireframe:** split shell; left navy trust panel with “Orçamentos claros. Serviços sob controle.” and three short benefits; right form card with logo, `h1 Entrar`, email, password with show/hide, remember checkbox, primary Entrar, “Esqueci minha senha”, registration link.
- **Components/data states:** field errors near fields; pending submit; generic invalid-credentials error; rate-limit message with wait time; success redirect status.
- **Responsive/interaction:** desktop card max 440px; links retain focus order. `Enter` submits; password toggle does not move focus; preserve typed email after failure.
- **Accessibility/icons:** labels/`autocomplete`; `LockClosedIcon`, `EnvelopeIcon`, `ArrowRightOnRectangleIcon`; icon-only toggle has label; focus error summary after multiple errors.
- **Acceptance checks:** no sidebar/chrome; paste/password manager works; 4.5:1 text contrast; no disabled submit before a valid-looking form; server errors are recoverable.

#### Mobile screen

- **Route/state/role:** same route/state; guest at 360/390px.
- **Purpose/task:** complete sign-in one-handed without losing context.
- **Wireframe:** brand row, form card full width with 16px gutters, fields stacked, CTA full width, recovery/register links below; trust copy collapses to one sentence.
- **Components/data states:** same states; keyboard resize keeps CTA visible; loading text remains in button.
- **Responsive/interaction:** 48px inputs, 44px icon hit areas, no split panel, no horizontal overflow; `Tab` order follows reading order.
- **Accessibility/icons:** same registry and labels; `scroll-padding-bottom` prevents keyboard overlap.
- **Acceptance checks:** usable at 360px with long email/error text; no clipped focus ring; error summary is reachable above CTA.

### A02 — Registration

#### Desktop screen

- **Route/state/role:** `/register`; guest, default/validation/email-duplicate/terms-required state.
- **Purpose/task:** create user account with name, email and strong password, then enter verification wait state.
- **Wireframe:** trust panel + form: name, email, password, confirm password, live password requirements, optional username/phone, terms checkbox when configured, Criar conta.
- **Components/data states:** password requirement checklist, inline validation on blur, global registration error, pending, success to verification.
- **Responsive/interaction:** two-column optional fields at ≥1024px; password requirements remain adjacent; preserve values except passwords after a failed request.
- **Accessibility/icons:** `CheckCircleIcon`, `ExclamationTriangleIcon`, `EyeIcon` (if available in implementation registry; otherwise use text button), labels and `aria-describedby`.
- **Acceptance checks:** error summary links to every invalid field; terms requirement is explicit; no password is returned in errors or URL.

#### Mobile screen

- **Route/state/role:** same; guest at 360/390px.
- **Purpose/task:** finish registration with minimal scrolling and clear password guidance.
- **Wireframe:** single column, name/email first, password block, requirements below password, confirmation, terms, full-width CTA.
- **Components/data states:** same; sticky footer is avoided so focus is not obscured; success uses a status region.
- **Responsive/interaction:** 48px fields; requirements wrap without overflow; optional fields can be collapsed under “Mais dados”.
- **Accessibility/icons:** all requirements use text plus icon; no color-only valid/invalid state; keyboard focus remains in viewport.
- **Acceptance checks:** 360px layout fits; paste and autofill work; validation does not trigger on every keystroke.

### A03 — Email verification

#### Desktop screen

- **Route/state/role:** `/email/verify`; authenticated but unverified; pending, resent, expired-link and verified states.
- **Purpose/task:** understand the block, resend confirmation and log out/change address path.
- **Wireframe:** centered card, `h1 Confirme seu e-mail`, masked destination, numbered steps, primary Reenviar link, cooldown, secondary Sair, support link.
- **Components/data states:** resend pending, rate-limit countdown, email-send failure with retry, verified success redirect to 2FA setup.
- **Responsive/interaction:** card max 520px; no operational nav; preserve current auth session.
- **Accessibility/icons:** `EnvelopeIcon`, `ArrowPathIcon`, `CheckCircleIcon`; status updates `aria-live=polite`; button text states are explicit.
- **Acceptance checks:** no dashboard data leaks; resend is not available while throttled; expired link explains re-open path.

#### Mobile screen

- **Route/state/role:** same; authenticated unverified.
- **Purpose/task:** resend from a narrow device without ambiguity.
- **Wireframe:** logo, card, email destination, stacked actions and support link.
- **Components/data states:** same; cooldown remains readable when wrapped.
- **Responsive/interaction:** 16px gutters; primary full width; logout is secondary text button with 44px target.
- **Accessibility/icons:** same; focus returns to resend button after response; screen reader announces success/error.
- **Acceptance checks:** no horizontal overflow; resend and logout are keyboard reachable; back does not expose operational screen.

### A04 — Password recovery/reset

#### Desktop screen

- **Route/state/role:** `/forgot-password` guest and `/reset-password/{token}` guest; request, sent, invalid/expired token and reset success.
- **Purpose/task:** request a reset link or set a new password from a valid link.
- **Wireframe:** same auth shell; request has email + Enviar link; reset has read-only email, new password, confirmation, requirements and Redefinir senha.
- **Components/data states:** neutral anti-enumeration success, rate limit, token expired with new-request action, pending, success to login.
- **Responsive/interaction:** reset email is visually read-only, not disabled-looking; form never includes old password.
- **Accessibility/icons:** `EnvelopeIcon`, `LockClosedIcon`, `ArrowPathIcon`; labels and live feedback.
- **Acceptance checks:** link errors include recovery; token is never rendered as visible copy outside route contract; success clears sensitive fields.

#### Mobile screen

- **Route/state/role:** same guest routes.
- **Purpose/task:** recover access with correct mobile keyboard types.
- **Wireframe:** one field or reset stack, large CTA, back-to-login link.
- **Components/data states:** same; email uses email keyboard; password fields have visibility controls.
- **Responsive/interaction:** no side panel; full-width CTA; long error copy wraps.
- **Accessibility/icons:** focus moves to error summary after submit; password managers can fill.
- **Acceptance checks:** 360px no clipping; read-only email remains understandable; retry is reachable.

### A05 — 2FA challenge/recovery

#### Desktop screen

- **Route/state/role:** `/two-factor-challenge`; session challenge, invalid TOTP, recovery-code mode, email-recovery send/verify and throttled state.
- **Purpose/task:** complete six-digit TOTP, use a one-time recovery code or start email recovery.
- **Wireframe:** centered security card with six-digit input, primary Confirmar, “Usar recovery code”, “Receber código por e-mail”, back/sign-out.
- **Components/data states:** segmented visual input backed by one semantic input; pending, invalid, rate-limited, sent and recovered-to-setup states.
- **Responsive/interaction:** auto-advance only within the same field; paste six digits allowed; never auto-submit before user confirmation unless explicitly accepted.
- **Accessibility/icons:** `ShieldCheckIcon`, `KeyIcon`, `EnvelopeIcon`; visible mode heading; announce remaining attempts only when policy allows.
- **Acceptance checks:** no dashboard data before success; recovery invalidates old TOTP and routes to setup; focus never hidden by overlays.

#### Mobile screen

- **Route/state/role:** same challenge at 360/390px.
- **Purpose/task:** enter code one-handed with a clear fallback.
- **Wireframe:** security icon/title, single six-digit input, full-width confirm, fallback actions stacked.
- **Components/data states:** same; numeric keyboard; paste and password-manager flows retained.
- **Responsive/interaction:** 48px code field; fallback links have 8px spacing and 44px hit areas.
- **Accessibility/icons:** no reliance on six separate DOM inputs; error text tied to input; mode changes announce.
- **Acceptance checks:** input does not reject paste; invalid code retains route and shows retry; no chrome/sidebar.

### A06 — 2FA setup/recovery codes

#### Desktop screen

- **Route/state/role:** `/settings/security/two-factor`; verified user before dashboard access or managing active TOTP; setup, confirm, active and regenerated-code states.
- **Purpose/task:** enable/confirm TOTP, save one-time recovery codes, regenerate or disable according to policy.
- **Wireframe:** security page with stepper: 1 scan QR/manual key, 2 enter six digits, 3 recovery codes; active status card and regenerate action.
- **Components/data states:** QR data URL area, manual key behind reveal control, confirmation error, one-time codes with copy/download/print guidance, success banner.
- **Responsive/interaction:** QR remains scannable; manual key is optional fallback; setup cannot be skipped to operations.
- **Accessibility/icons:** `ShieldCheckIcon`, `KeyIcon`, `ArrowDownTrayIcon`, `CheckCircleIcon`; QR has a text alternative.
- **Acceptance checks:** secrets only in this flow, not global props; recovery codes shown once and never in logs/URL; disable/regenerate confirmations are explicit.

#### Mobile screen

- **Route/state/role:** same; verified user.
- **Purpose/task:** configure authenticator from a second device or use manual fallback.
- **Wireframe:** vertical steps, QR card, reveal manual key, code field, recovery codes in a horizontally scroll-free code list with copy buttons.
- **Components/data states:** same; codes use wrapping or one-per-row, never clipped.
- **Responsive/interaction:** 48px code input; QR has safe margin; action bar not fixed over focused content.
- **Accessibility/icons:** recovery codes have accessible copy labels; focus returns to next step after confirmation.
- **Acceptance checks:** 360px can read every code; reduced motion does not hide steps; setup success routes to dashboard only after confirmation.

### A07 — Profile/password/email/delete

#### Desktop screen

- **Route/state/role:** `/profile`; verified owner/admin/member; default, save success, validation failure, email-change pending, password confirmation and delete-confirmation states.
- **Purpose/task:** update name/username/phone/photo/preferences, change password/email, review sessions policy and delete account.
- **Wireframe:** settings tabs/sections: profile info, notification preference, password, email (current read-only + request new), danger zone. Delete is visually separated.
- **Components/data states:** upload progress/error, saved toast, signed email confirmation pending, current-password modal, destructive confirmation.
- **Responsive/interaction:** desktop two-column sections; no modal for primary profile editing; preserve scroll on save.
- **Accessibility/icons:** `UserIcon`, `EnvelopeIcon`, `LockClosedIcon`, `TrashIcon`; visible labels, input associations, focus management.
- **Acceptance checks:** email change logs out only after confirmation; delete requires current password and explicit confirmation; old photo cleanup is represented as progress/success.

#### Mobile screen

- **Route/state/role:** same; verified member.
- **Purpose/task:** edit profile without dense settings chrome.
- **Wireframe:** stacked sections with disclosure headings; sticky-free Save at the end; danger zone collapsed until opened.
- **Components/data states:** same; photo action opens native file picker affordance; errors stay beside fields.
- **Responsive/interaction:** 44px rows, 48px inputs; email request opens a bottom sheet with explicit cancel.
- **Accessibility/icons:** disclosure state announced; destructive action uses text + `TrashIcon`; no icon-only settings rows.
- **Acceptance checks:** no focus obscured by keyboard; long labels wrap; success feedback remains visible after navigation.

### B01 — Tenant onboarding

#### Desktop screen

- **Route/state/role:** `/onboarding/tenant`; new authenticated, verified owner; empty, validation, success and plan-limit state.
- **Purpose/task:** create the active tenant and establish owner context before operational data.
- **Wireframe:** progress 1/2 identity, 2/2 business: tenant name, slug preview (not editable as raw tenant id), business data, timezone/currency, plan summary, Continue.
- **Components/data states:** autosave draft indicator only for non-sensitive values, inline errors, duplicate slug guidance, success to dashboard.
- **Responsive/interaction:** max 680px form; step back preserves state; no sidebar until completion.
- **Accessibility/icons:** use `HomeIcon` plus visible text for tenant context; `ChevronRightIcon` and `CheckCircleIcon` are verified.
- **Acceptance checks:** UI never sends or trusts arbitrary tenant id; owner role is explained; timezone/currency are explicit.

#### Mobile screen

- **Route/state/role:** same new owner flow.
- **Purpose/task:** complete setup with progressive disclosure.
- **Wireframe:** one step at a time, progress indicator, full-width Continue, Back text button.
- **Components/data states:** same; plan choice is a card list with radio semantics.
- **Responsive/interaction:** 48px controls; no side-by-side fields unless safe; preserve focus on step change.
- **Accessibility/icons:** step indicator includes text state; no color-only completion.
- **Acceptance checks:** 360px no clipped buttons; back preserves input; failed submit focuses summary.

### B02 — Team, invites and roles

#### Desktop screen

- **Route/state/role:** `/settings/team`; owner/admin in Completo; member read-only; empty, pending invite, limit reached, revoke/remove and role-change states.
- **Purpose/task:** invite up to plan limit, assign owner/admin/member, remove member without losing historical authorship.
- **Wireframe:** header with usage meter, Invite member CTA, table of name/email/role/status/joined/actions, pending invites section.
- **Components/data states:** invite drawer, token-expiry label, resend/revoke confirmation, unauthorized/plan-locked panel.
- **Responsive/interaction:** actions menu not crammed into row; role select has confirmation when privilege changes.
- **Accessibility/icons:** `UserPlusIcon`, `UsersIcon`, `ShieldCheckIcon`, `EllipsisVerticalIcon`, `ClockIcon`.
- **Acceptance checks:** Basic plan explains upgrade instead of exposing dead control; member removal confirmation preserves attribution copy; invite error has retry.

#### Mobile screen

- **Route/state/role:** same; owner/admin/member.
- **Purpose/task:** manage small team from a card list.
- **Wireframe:** usage meter, Invite button, member cards with role/status, overflow action sheet.
- **Components/data states:** same; pending invites are a separate collapsible group.
- **Responsive/interaction:** cards show name, role, status and one primary action; no horizontal table scroll.
- **Accessibility/icons:** action sheet traps focus and restores it; role changes announce selected state.
- **Acceptance checks:** 360px cards fit; destructive remove is separated; permissions remain server-enforced.

### C01 — Dashboard

#### Desktop screen

- **Route/state/role:** `/dashboard`; all tenant roles; populated, first-use empty, loading skeleton, partial error and plan-limited states.
- **Purpose/task:** understand pipeline and jump to client/quote/service/receivable work.
- **Wireframe:** top bar + sidebar; `h1 Visão geral`; KPI row drafts, awaiting response, in progress, receivable/overdue, received; two-column activity and quick actions.
- **Components/data states:** KPI cards with trend context, status chips, recent activity timeline, empty guidance, retry per widget.
- **Responsive/interaction:** max four KPI cards in row; charts simplify to text summaries; dashboard content preserves tenant scope.
- **Accessibility/icons:** `ChartBarIcon`, `DocumentTextIcon`, `WrenchScrewdriverIcon`, `BanknotesIcon`, `ClockIcon`, `ExclamationTriangleIcon`.
- **Acceptance checks:** no cross-tenant metrics; statuses not color-only; loading reserves card height; quick actions have labels.

#### Mobile screen

- **Route/state/role:** same at 360/390px.
- **Purpose/task:** triage today’s work quickly.
- **Wireframe:** compact header, horizontally non-scrolling KPI stack, “A fazer agora” list, recent activity, bottom Create action.
- **Components/data states:** cards become stacked; activity truncation has “Ver tudo”; widget error includes retry.
- **Responsive/interaction:** one-column; primary action is fixed only if bottom safe area is reserved; no chart wider than viewport.
- **Accessibility/icons:** bottom action has visible text + `PlusIcon`; live counts are contextual phrases.
- **Acceptance checks:** no horizontal overflow at 360px; first focus after route is `h1`; all empty states suggest next action.

### C02 — Clients list and filters

#### Desktop screen

- **Route/state/role:** `/clients`; authorized tenant member; populated, empty, filtered-empty, loading, error and archived-filter states.
- **Purpose/task:** find, create, filter, archive and page through clients.
- **Wireframe:** title + Novo cliente; search; filter bar status/type; table name/contact/status/last activity/actions; pagination.
- **Components/data states:** debounce search, clear filters, archive confirmation, toast success, retry error.
- **Responsive/interaction:** filters retain order and labels; row action menu; status chip includes text.
- **Accessibility/icons:** `UsersIcon`, `PlusIcon`, `MagnifyingGlassIcon`, `FunnelIcon`, `ArchiveBoxIcon`, `EllipsisVerticalIcon`.
- **Acceptance checks:** URL state preserves filters/pagination; archive does not erase history; table headers have sort semantics where used.

#### Mobile screen

- **Route/state/role:** same; authorized tenant member.
- **Purpose/task:** find a client with one hand and open its record.
- **Wireframe:** search at top, Filter button with active count, client cards, pagination/load-more control, floating/inline Novo cliente.
- **Components/data states:** cards show name, type, WhatsApp/email, status, last activity; filtered-empty suggests reset.
- **Responsive/interaction:** filter sheet; card tap opens detail while overflow holds archive; 44px targets.
- **Accessibility/icons:** cards are not nested interactive controls; each action has accessible name.
- **Acceptance checks:** no table overflow; search and filter state survive back navigation; skeleton matches card geometry.

### C03 — Client detail/history

#### Desktop screen

- **Route/state/role:** `/clients/{client}`; authorized tenant member; default, archived, missing/forbidden and history-empty states.
- **Purpose/task:** review client facts and historical quotes/services/receivables, then create related work.
- **Wireframe:** breadcrumb, client header with status/actions, contact/address panel, tabs Overview / Quotes / Services / Receivables, activity timeline.
- **Components/data states:** masked optional document, edit panel, archive confirmation, tab loading/error, empty history CTAs.
- **Responsive/interaction:** tabs become segmented select or horizontal tab list with no page overflow; header actions move to overflow.
- **Accessibility/icons:** `UserIcon`, `MapPinIcon`, `DocumentTextIcon`, `WrenchScrewdriverIcon`, `BanknotesIcon`, `ArchiveBoxIcon`.
- **Acceptance checks:** missing/unauthorized uses non-revealing not-found; address snapshot distinction is explicit; tab changes preserve scroll sensibly.

#### Mobile screen

- **Route/state/role:** same; authorized member.
- **Purpose/task:** call/message client or inspect history.
- **Wireframe:** compact client header, primary contact actions, stacked fact cards, select-like tab control, timeline.
- **Components/data states:** same; long notes expand/collapse with text label.
- **Responsive/interaction:** no two-column squeeze; actions have 44px targets; sticky header does not cover focus.
- **Accessibility/icons:** contact actions include text labels; current tab announced.
- **Acceptance checks:** optional fields do not leave blank gaps; archived state is text + icon; back restores prior list scroll.

### C04 — Catalog

#### Desktop screen

- **Route/state/role:** `/catalog`; authorized tenant member, create/edit owner/admin policy; default, empty, inactive-filter, validation and error states.
- **Purpose/task:** maintain services/materials/taxes and default price/cost snapshots.
- **Wireframe:** title + Novo item, type/status filters, table name/type/unit/default price/cost/status/actions; side drawer form.
- **Components/data states:** currency input in BRL, active toggle, duplicate-name guidance, save pending/success/error.
- **Responsive/interaction:** form drawer becomes page/sheet on mobile; price formatting remains locale-aware.
- **Accessibility/icons:** `WrenchScrewdriverIcon`, `PlusIcon`, `PencilSquareIcon`, `CurrencyDollarIcon`, `ArchiveBoxIcon`.
- **Acceptance checks:** decimals display correctly while storage remains cents; inactive items are never silently deleted; item action is keyboard reachable.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** adjust a catalog item quickly in the field.
- **Wireframe:** filter chips, item cards, full-width Novo item, form sheet with grouped fields.
- **Components/data states:** same; form errors inline and summary on submit.
- **Responsive/interaction:** numeric keyboard for price; 48px fields; currency prefix does not steal focus.
- **Accessibility/icons:** active status uses label/icon; sheet has Escape/close and focus restoration.
- **Acceptance checks:** no clipped currency; cancel preserves no accidental save; empty state explains how catalog values help quote creation.

### D01 — Quote list

#### Desktop screen

- **Route/state/role:** `/quotes`; authorized tenant member; all status/loading/empty/error/filter states.
- **Purpose/task:** monitor draft-to-approval pipeline and open or create proposals.
- **Wireframe:** title + Novo orçamento; search/client/status/date filters; table number/client/total/status/validity/updated/actions; pagination/export if allowed.
- **Components/data states:** status chips draft/sent/viewed/approved/rejected/expired/canceled, link indicator, retry.
- **Responsive/interaction:** status filter uses text; overflow menu owns duplicate/version/cancel/share actions.
- **Accessibility/icons:** `DocumentTextIcon`, `PlusIcon`, `MagnifyingGlassIcon`, `FunnelIcon`, `LinkIcon`, `EllipsisVerticalIcon`.
- **Acceptance checks:** approved quote cannot show silent edit affordance; plan limits explain blocked creation; URL preserves filters.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** triage proposals from a phone.
- **Wireframe:** search, filter sheet, quote cards with number/title/client/total/status/validity, inline Novo orçamento.
- **Components/data states:** same; card displays one clear primary action and overflow for secondary.
- **Responsive/interaction:** filters in sheet; totals stay visible; no horizontal status pill row without wrapping.
- **Accessibility/icons:** status includes icon and text; card action names include quote number.
- **Acceptance checks:** 360px supports long client names; back restores filter state; error has retry without clearing prior results.

### D02 — Quote create/edit/review

#### Desktop screen

- **Route/state/role:** `/quotes/create` and `/quotes/{quote}/edit`; authorized member; draft, validation, recalculating, limit reached, saved and approved-locked states.
- **Purpose/task:** build a quote with client, items, quantities, unit prices, discount, fee, validity, payment terms, prazo, internal/public notes and attachments.
- **Wireframe:** step-like sections without modal wizard: 1 client/title, 2 line items table, 3 totals/terms, 4 notes/attachments, right summary rail with status and Save/Review/Send.
- **Components/data states:** catalog picker, add/remove rows, currency totals, server-calculated total notice, unsaved changes guard, attachment progress/error.
- **Responsive/interaction:** desktop line-item table; editing after sent routes to version/duplicate confirmation; approval locks values visibly.
- **Accessibility/icons:** `DocumentTextIcon`, `PlusIcon`, `TrashIcon`, `PaperClipIcon`, `CheckCircleIcon`, `ExclamationTriangleIcon`; totals have text summary.
- **Acceptance checks:** values are not presented as float assumptions; server totals reconcile; error summary focuses first invalid field; no silent mutation after send/approval.

#### Mobile screen

- **Route/state/role:** same draft/editor states at 360/390px.
- **Purpose/task:** create a reliable quote with minimal horizontal data entry.
- **Wireframe:** stacked sections; each item is a card with quantity/unit/price; sticky-free bottom Save/Review area with safe-area padding.
- **Components/data states:** “Adicionar item” opens searchable sheet; totals card follows items; attachments become upload rows.
- **Responsive/interaction:** inputs 48px; line item fields stack; quantity uses numeric keyboard; confirmation before abandoning unsaved changes.
- **Accessibility/icons:** all row remove buttons labeled with item name; summary is announced after recalculation; no drag-only ordering.
- **Acceptance checks:** no horizontal table; long descriptions wrap; send pending disables duplicate submit; version prompt is explicit.

### D03 — Quote versions/link

#### Desktop screen

- **Route/state/role:** `/quotes/{quote}/versions`; authorized member; version history, link active/revoked/expired, viewed/sent/approved/rejected events.
- **Purpose/task:** compare immutable versions, copy/revoke public link and understand recipient activity.
- **Wireframe:** quote header, version timeline/table with created-by/date/status, preview link card with Copy, Revoke, Resend; event timeline.
- **Components/data states:** copy confirmation, revoke confirmation, expired link, rate-limit warning, no-version empty state.
- **Responsive/interaction:** version compare opens a full page/drawer, not a blocking modal for primary review.
- **Accessibility/icons:** `LinkIcon`, `ClockIcon`, `EyeIcon`, `ArrowPathIcon`, `XCircleIcon`, `CheckCircleIcon`.
- **Acceptance checks:** raw token is never shown; revoked/expired actions explain recovery; approval event is idempotent in UI.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** resend or revoke a proposal link safely.
- **Wireframe:** current link status card, primary Copy link, secondary Resend/Revoke, version cards, event accordion.
- **Components/data states:** same; copy uses polite live feedback without stealing focus.
- **Responsive/interaction:** link is not a long unwrapped URL; use a copy button and masked display.
- **Accessibility/icons:** icon-only copy is forbidden; use “Copiar link”; event accordion exposes expanded state.
- **Acceptance checks:** code/token never enters page text; destructive revoke needs confirmation; 360px cards fit.

### D04 — Public quote approval

#### Desktop screen

- **Route/state/role:** `/p/{token}`; public recipient; valid, viewed, approved, rejected, expired, revoked, rate-limited and already-decided states.
- **Purpose/task:** inspect public quote, confirm name, approve or reject once.
- **Wireframe:** standalone branded public page, provider identity, quote number/date/validity, client-safe description/items/totals/payment terms, attachment links, recipient name confirmation, Approve/Decline.
- **Components/data states:** skeleton, invalid link explanation, confirmation dialog with explicit action, success receipt/status, support contact.
- **Responsive/interaction:** readable document measure, no authenticated chrome; buttons remain separate and equal prominence until decision.
- **Accessibility/icons:** `DocumentTextIcon`, `CheckCircleIcon`, `XCircleIcon`, `PaperClipIcon`, `InformationCircleIcon`.
- **Acceptance checks:** only public fields appear; approval state cannot be repeated; token is never echoed; totals/validity are clear.

#### Mobile screen

- **Route/state/role:** same public route.
- **Purpose/task:** approve from WhatsApp/email on a phone with confidence.
- **Wireframe:** provider header, summary card, collapsible item sections, sticky-free decision buttons at bottom after recipient name confirmation.
- **Components/data states:** same; attachment rows open safely; decision success replaces CTA with receipt.
- **Responsive/interaction:** one-column, 16px gutter, currency and date locale-aware; no wide table.
- **Accessibility/icons:** buttons include text and icon; confirmation dialog focus trap; errors have retry/support route.
- **Acceptance checks:** usable without login; expired/revoked state cannot expose quote details beyond safe message; no horizontal overflow.

### E01 — Services list

#### Desktop screen

- **Route/state/role:** `/services`; authorized tenant member; planned/in-progress/paused/completed/canceled, filters, loading/empty/error.
- **Purpose/task:** track execution pipeline and open a service.
- **Wireframe:** title + Novo serviço/Converter aprovado; status/date/assignee filters; table title/client/assignee/status/dates/value/actions.
- **Components/data states:** conversion confirmation, status chips, pagination, retry.
- **Responsive/interaction:** primary action is Convert approved quote when eligible; manual create respects plan limits.
- **Accessibility/icons:** `WrenchScrewdriverIcon`, `PlusIcon`, `FunnelIcon`, `CalendarDaysIcon`, `CheckCircleIcon`, `ClockIcon`.
- **Acceptance checks:** conversion does not duplicate service; status labels match enum; filters preserve state.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** see what is planned and in progress.
- **Wireframe:** status filter, service cards with client/title/status/date/assignee/value, create/convert CTA.
- **Components/data states:** same; card menu for pause/cancel where authorized.
- **Responsive/interaction:** date/assignee facts wrap; no table; status change confirmation if consequential.
- **Accessibility/icons:** status icon + text; card buttons not nested; focus after status update returns to card.
- **Acceptance checks:** cards fit 360px; empty state differentiates no services vs filtered none; retry preserves prior list.

### E02 — Service detail/execution

#### Desktop screen

- **Route/state/role:** `/services/{service}`; assigned member/admin/owner; default, checklist empty/complete, attachment upload, paused/completed/canceled and history states.
- **Purpose/task:** execute work, update status, checklist, notes, occurrences, photos and history.
- **Wireframe:** service header/status action, overview/customer/address snapshot, checklist panel, notes/occurrences, attachments, activity history.
- **Components/data states:** optimistic-looking pending only after server ack; upload progress, failed attachment retry, completion confirmation, audit timeline.
- **Responsive/interaction:** checklist supports click and keyboard; no drag-only ordering; status transitions explain consequences.
- **Accessibility/icons:** use `WrenchScrewdriverIcon`, `CheckCircleIcon`, `ClockIcon` for paused, `PhotoIcon`, `PaperClipIcon`, `MapPinIcon`, and `ArrowPathIcon`.
- **Acceptance checks:** assigned role restrictions visible; address is labeled snapshot; completed service stays readable; actions are audited.

#### Mobile screen

- **Route/state/role:** same at 360/390px.
- **Purpose/task:** update execution at the job site.
- **Wireframe:** status header, customer/address card, checklist rows, Add note/photo buttons, history accordion.
- **Components/data states:** camera/file picker rows, upload retry, offline-like network error messaging without claiming offline persistence.
- **Responsive/interaction:** checklist rows 48px; action bar is bottom-safe-area aware; notes expand to full-width field.
- **Accessibility/icons:** photo/attachment actions have text; status change confirmation is keyboard/screen-reader operable.
- **Acceptance checks:** no content hidden behind action tray; large text does not break checklist; error keeps entered note.

### F01 — Receivables/settlement

#### Desktop screen

- **Route/state/role:** `/receivables`; authorized tenant member; pending/paid/overdue/canceled, filters, settlement modal/page, error/loading.
- **Purpose/task:** monitor due amounts and register idempotent payment receipts.
- **Wireframe:** KPI strip pending/overdue/received, date/status/client filters, table installment/client/due/value/status/payment method/actions; settle side panel.
- **Components/data states:** currency inputs in cents semantics, paid timestamp/method/notes, duplicate settlement protection, success receipt.
- **Responsive/interaction:** destructive cancel is separate from settle; overdue is icon/text plus color.
- **Accessibility/icons:** `BanknotesIcon`, `ReceiptPercentIcon`, `CurrencyDollarIcon`, `CheckCircleIcon`, `ExclamationTriangleIcon`, `FunnelIcon`.
- **Acceptance checks:** cancel never erases history; double submit is prevented; totals reconcile; export respects tenant/plan.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** identify overdue invoices and mark paid.
- **Wireframe:** KPI cards, filter button, receivable cards, “Dar baixa” sheet with amount/date/method/notes.
- **Components/data states:** same; payment method is a labeled select; success returns to updated card.
- **Responsive/interaction:** numeric keyboard for money; card status appears before amount; no horizontal table.
- **Accessibility/icons:** settlement form uses error summary; success is announced politely; cancel action has confirmation.
- **Acceptance checks:** 360px amount/status remain readable; prior results remain on error; retry does not duplicate.

### F02 — Reports/export

#### Desktop screen

- **Route/state/role:** `/reports`; Complete owner/admin; locked Basic, loading, no-data, populated, export pending/error.
- **Purpose/task:** inspect sales, quote conversion, receivable health, services by status and clients by volume; export CSV.
- **Wireframe:** report filter bar period/status, KPI row, line/bar charts with table alternatives, export CTA, insight annotations.
- **Components/data states:** skeleton charts, no-data guidance, error retry, CSV progress/success/error, legend and direct labels.
- **Responsive/interaction:** chart detail uses accessible table; period selection is explicit; no automatic carousel.
- **Accessibility/icons:** `ChartBarIcon`, `ArrowDownTrayIcon`, `DocumentTextIcon`, `InformationCircleIcon`; charts have summaries and keyboard reachable data.
- **Acceptance checks:** accessible table alternative; colors not sole differentiator; Basic lock explains plan; export is tenant-isolated.

#### Mobile screen

- **Route/state/role:** same; Complete owner/admin.
- **Purpose/task:** answer one business question quickly rather than read a dense dashboard.
- **Wireframe:** period filter, KPI stack, simplified chart or summary bars, “Ver dados em tabela”, export button.
- **Components/data states:** same; fewer ticks and direct labels; no-data state suggests widening period.
- **Responsive/interaction:** chart reflows to horizontal bars/text; filters in sheet; export reports pending state.
- **Accessibility/icons:** table view is first-class; chart summary read before graphic; buttons retain text.
- **Acceptance checks:** no chart overflow at 360px; legend remains close; loading reserves space.

### G01 — Plan/usage/billing

#### Desktop screen

- **Route/state/role:** `/settings/billing`; owner; trialing/active/past_due/canceled/expired, Basic/Complete, limit-warning and provider-error states.
- **Purpose/task:** see plan, limits, usage, activate/switch/cancel and understand data preservation.
- **Wireframe:** current plan card, usage meters clients/quotes/services/users, plan comparison, billing status timeline, change/cancel actions.
- **Components/data states:** provider pending, past-due read-tolerance banner, upgrade confirmation, cancellation retention copy.
- **Responsive/interaction:** compare plan cards side by side desktop; no guilt or dark patterns; dangerous cancellation separated.
- **Accessibility/icons:** `BanknotesIcon`, `ChartBarIcon`, `CheckCircleIcon`, `ExclamationTriangleIcon`, `ArrowPathIcon`.
- **Acceptance checks:** limits and server locks are reflected in UI; cancel preserves records; webhooks/idempotency are represented by stable pending/success/error states.

#### Mobile screen

- **Route/state/role:** same owner-only screen.
- **Purpose/task:** understand why a creation action is blocked and choose next step.
- **Wireframe:** current plan/status, stacked meters, upgrade CTA, disclosure for features, cancellation section.
- **Components/data states:** same; no horizontal comparison table; provider error has retry/support.
- **Responsive/interaction:** 48px actions; status banner before meters; confirmation sheets for upgrade/cancel.
- **Accessibility/icons:** plan state includes text and icon; meters expose numeric values, not just progress color.
- **Acceptance checks:** 360px preserves readable prices/limits; locked features explain why; cancellation confirmation is explicit.

### G02 — Activity/notifications

#### Desktop screen

- **Route/state/role:** `/activity`; tenant roles according to policy; populated, empty, filtered, loading/error states.
- **Purpose/task:** review auditable events, reminders and notification outcomes without exposing secrets.
- **Wireframe:** title, event/type/date filters, timeline/table actor/action/entity/time, unread marker, mark-read action.
- **Components/data states:** pagination, retry, notification preference link, contextual live count.
- **Responsive/interaction:** filter bar collapses; event detail opens an accessible disclosure.
- **Accessibility/icons:** `BellIcon`, `InformationCircleIcon`, `CheckCircleIcon`, `ExclamationTriangleIcon`, `ClockIcon`.
- **Acceptance checks:** no token/secret/password in activity; unread status has text; mark-read does not steal focus.

#### Mobile screen

- **Route/state/role:** same.
- **Purpose/task:** catch important changes from a phone.
- **Wireframe:** unread summary, filter button, vertical event cards, disclosure details.
- **Components/data states:** empty state explains when activity appears; retry retains previous cards.
- **Responsive/interaction:** cards show actor/action/time first; actions use 44px targets; no dense table.
- **Accessibility/icons:** live count uses a complete phrase; details expose expanded state; icon-only bell has label.
- **Acceptance checks:** timestamps and event text wrap; no secret values; focus returns to the event after closing details.

## Cross-screen acceptance checklist

- Auth boundary is respected on all A01–A06 screens; no sidebar or logged-in chrome appears before email verification, TOTP setup and challenge completion.
- Every C–G screen shows tenant-scoped status, loading, empty, error-with-retry and success feedback; filtered-empty is distinct from first-use empty where applicable.
- At 360px and 390px, no horizontal overflow, all interactive targets are at least 44×44px, keyboard focus remains visible and fixed UI observes safe-area/content insets.
- Every icon-only control has an accessible name; decorative icons are hidden; Heroicons names come only from the registry in MASTER.md.
- Forms use associated labels, semantic input types, paste/password-manager support, inline errors, and a focusable linked error summary after failed submit.
- Status never relies on color alone; charts have a table/text alternative; destructive actions require explicit confirmation and preserve history where the specification requires it.
- Motion is subtle, 150–300ms, and disabled/reduced under `prefers-reduced-motion`; no critical content is revealed only by animation.
- All routes are deep-linkable and back navigation preserves filter, scroll and draft state where stated.

## Sources and evidence

- Local UI/UX Pro Max outputs: `design-system/orca-f-cil-crm/MASTER.md`, plus the skill’s `quick-reference.md` and `pro-rules.md`.
- Heroicons official catalog: [heroicons.com](https://heroicons.com/).
- Heroicons official repository and Vue usage/package naming: [tailwindlabs/heroicons README](https://github.com/tailwindlabs/heroicons).

