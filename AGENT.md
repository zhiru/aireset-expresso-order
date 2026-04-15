# Aireset Expresso Order — Agent Guide

Contexto canônico para agentes de IA que trabalham neste plugin.

## Visão geral

Aireset Expresso Order é um plugin WordPress/WooCommerce de pedido expresso para vendedores internos. Ele permite:
- Buscar clientes por CPF/CNPJ
- Adicionar produtos com busca Select2
- Calcular frete com endereço do cliente
- Gerar pedido WooCommerce e proposta pública por token

## Stack e arquitetura

- PHP 7.4+ sem namespace, classes prefixadas com `EOP_`
- WooCommerce obrigatório
- jQuery + Select2 no admin/frontend do PDV
- AJAX para busca de cliente, busca de produto, criação, edição e cálculo de frete

### Estrutura principal

```text
aireset-expresso-order.php
includes/
  class-admin-page.php
  class-ajax-handlers.php
  class-order-creator.php
  class-orders-page.php
  class-public-proposal.php
  class-role.php
  class-settings.php
  class-shipping-calculator.php
  class-shortcode.php
  class-eop-license-base.php
  class-eop-license-manager.php
  trait-eop-license-guard.php
assets/
templates/
docs/
LICENSE
```

## Fluxos principais

### Criação de pedido
1. Busca cliente por CPF/CNPJ em `eop_search_customer`
2. Busca produtos em `eop_search_products`
3. Calcula frete em `eop_calculate_shipping`
4. Cria pedido via `EOP_Order_Creator::create()`
5. Se `flow_mode=proposal`, gera link público da proposta

### Edição de pedido
1. Carrega pedido por `eop_load_order`
2. Persiste atualização por `eop_update_order`

### Proposta pública
1. Cliente acessa shortcode com token
2. Visualiza proposta e confirma via `EOP_Public_Proposal`

## Sistema de licenciamento

Documentação global: `~/.copilot/skills/aireset-licensing/licenciamento-aireset.md`

### Arquivos de licença
1. `class-eop-license-base.php` — SDK de comunicação com Elite Licenser
2. `class-eop-license-manager.php` — UI admin de ativação/desativação
3. `trait-eop-license-guard.php` — validadores ocultos anti-pirataria

### Estado atual
- `product_id = 2`
- `enc_key = C4293B5D4F9D3BAA`
- License gate ativo no bootstrap antes dos requires de negócio
- Guards ativos em 3 classes:
  - `EOP_Admin_Page` → `_resolve_env_config()`
  - `EOP_Ajax_Handlers` → `_prefetch_module_state()`
  - `EOP_Order_Creator` → `_validate_session_tokens()`

### Options relevantes
- `Aireset-ExpressoOrder_lic_Key` com hash de domínio
- `Aireset-ExpressoOrder_lic_email`

## Convenções

- Prefixo de classes: `EOP_`
- Prefixo de funções/hooks: `eop_`
- Prefixo CSS: `eop-`
- Text domain: `aireset-expresso-order`
- Menu pai funcional: `aireset`
- Capability principal do fluxo: `edit_shop_orders`
- Nonce AJAX: `eop_nonce`
- Meta keys do pedido: `_eop_*`
- Pedidos do plugin usam `created_via = 'aireset-expresso-order'`

## Regras de manutenção

1. Nunca simplifique ou remova código dos arquivos de licenciamento sem motivo explícito.
2. Preserve o trait `EOP_License_Guard` nas três classes protegidas.
3. O license gate deve permanecer antes dos requires de negócio no bootstrap.
4. O `OPT_PREFIX` do license manager não pode mudar após ativação em produção.
5. Ao adicionar nova classe principal do fluxo, considere incluir guard oculto.
6. Preserve meta keys, AJAX actions, slugs e comportamento WooCommerce existente.
7. Sanitize entrada na borda, escape saída no contexto correto e valide capability/nonce.
8. Mantenha o SPA em jQuery vanilla; não introduza framework sem necessidade.
9. CSS compilado de SCSS deve ser alterado no SCSS quando aplicável; `admin.css` pode ser editado diretamente.
10. Para release, compile os três arquivos de licença com ionCube.

## Validação mínima

```bash
find . -name "*.php" -exec php -l {} \;
```

## Observações operacionais

- A tela principal `Pedido Expresso` deve abrir o formulário do plugin.
- Quando a licença estiver inválida, o item do menu abre a tela de ativação.
- Quando a licença estiver válida, a tela de licença fica oculta do menu e acessível apenas por slug interno.
