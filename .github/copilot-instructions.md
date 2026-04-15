# Copilot Instructions — Aireset Expresso Order

## Contexto do projeto

Plugin WordPress/WooCommerce que implementa um PDV (point of sale) para vendedores criarem pedidos e propostas comerciais. Depende de WooCommerce ativo. Slug: `aireset-expresso-order`, text domain: `aireset-expresso-order`.

## Estrutura

- `aireset-expresso-order.php` — bootstrap, constantes, carrega classes.
- `includes/` — classes PHP prefixadas com `EOP_`.
- `templates/` — templates PHP renderizados pelas classes.
- `assets/js/` — JavaScript jQuery vanilla (SPA do PDV em `admin.js`).
- `assets/css/` — CSS direto (`admin.css`, `orders.css`) e compilado de SCSS (`frontend.css`, `settings-admin.css`).
- `assets/scss/` — SCSS modular do frontend e da página de configurações.

## Convenções obrigatórias

### PHP
- Prefixo de funções: `eop_`. Prefixo de classes: `EOP_`.
- Meta keys de pedido: `_eop_*`.
- Pedidos sempre filtrados por `created_via = 'aireset-expresso-order'`.
- Sanitize entrada no ponto de entrada (`sanitize_text_field`, `absint`, `floatval`, cast explícito).
- Escape saída no contexto correto (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Valide `current_user_can` e nonce em toda ação admin e AJAX.
- Retorne erros estruturados em AJAX (`wp_send_json_error`); não esconda falhas.
- Preserve hooks, filtros, meta keys e AJAX actions existentes — não quebre retrocompatibilidade.
- Strings de interface devem usar `__()` / `esc_html__()` com text domain `aireset-expresso-order`.
- Execute `php -l` nos arquivos PHP alterados para validação mínima.

### JavaScript
- jQuery vanilla, sem frameworks adicionais.
- Variável global `eop_vars` (localizada via `wp_localize_script`).
- Nonce em `eop_vars.nonce`. Strings i18n em `eop_vars.i18n`.
- Prefixo CSS/HTML: `eop-`.
- Escape HTML via `$('<span>').text(value).html()` ao construir markup dinâmico.

### CSS / SCSS
- `admin.css` e `orders.css` — editados diretamente, sem SCSS.
- `frontend.css` e `settings-admin.css` — gerados de SCSS. Edite os `.scss` e rode `npm run build:css`.
- Tokens visuais definidos em `assets/scss/frontend/_tokens.scss`.
- Prefixo de classes CSS: `eop-`.

### Versionamento (obrigatório ao final de toda modificação)
- Incremente a versão patch (ex: `1.1.17` → `1.1.18`) em **dois lugares simultaneamente**: `EOP_VERSION` em `aireset-expresso-order.php` e `version` em `package.json`.
- Adicione uma entrada no topo do `CHANGELOG.md` com a nova versão, a data e uma descrição curta e objetiva do que mudou.
- Esses dois passos devem ser os **últimos** da tarefa, após todas as outras alterações de código estarem concluídas e validadas.

## Endpoints AJAX

| Action | Classe | Capability |
|---|---|---|
| `eop_search_customer` | `EOP_Ajax_Handlers` | `edit_shop_orders` |
| `eop_search_products` | `EOP_Ajax_Handlers` | `edit_shop_orders` |
| `eop_create_order` | `EOP_Ajax_Handlers` | `edit_shop_orders` |
| `eop_calculate_shipping` | `EOP_Shipping_Calculator` | `edit_shop_orders` |
| `eop_list_orders` | `EOP_Orders_Page` | `edit_shop_orders` |
| `eop_load_order` | `EOP_Orders_Page` | `edit_shop_orders` |
| `eop_update_order` | `EOP_Orders_Page` | `edit_shop_orders` |

## Shortcodes

| Shortcode | Classe | Auth |
|---|---|---|
| `[expresso_order]` | `EOP_Shortcode` | Requer login + `edit_shop_orders` |
| `[expresso_order_proposal]` | `EOP_Public_Proposal` | Público via token |

## Permissões

- `edit_shop_orders` — acesso ao PDV, AJAX e listagem de pedidos.
- `manage_options` — acesso às configurações.
- Role `vendedor_expresso` — menu restrito, só vê pedidos próprios.

## O que não fazer

- Não altere core do WordPress ou WooCommerce.
- Não introduza dependências JS ou PHP sem justificativa clara.
- Não altere arquivos `.css` gerados por SCSS diretamente — edite o `.scss` correspondente.
- Não remova meta keys ou AJAX actions públicas sem migração.
- Não exponha tokens, documentos ou dados sensíveis em logs ou HTML.
