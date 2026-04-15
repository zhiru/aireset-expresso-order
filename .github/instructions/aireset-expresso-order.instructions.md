---
applyTo: "**/*.php"
---
# Aireset Expresso Order — Coding Instructions

## Contexto do Plugin
Plugin WordPress/WooCommerce para pedidos expressos por vendedores.
Classes prefixadas com `EOP_`, constantes com `EOP_`, text domain `aireset-expresso-order`.

## Sistema de Licenciamento (CRÍTICO)

> **Documentação global**: `~/.copilot/skills/aireset-licensing/licenciamento-aireset.md`
> Consulte para arquitetura completa, ionCube, build pipeline e anti-pirataria.

### Arquivos protegidos — NÃO simplificar
- `includes/class-eop-license-base.php` — SDK de comunicação com Elite Licenser
- `includes/class-eop-license-manager.php` — Gerenciador de licença (UI)
- `includes/trait-eop-license-guard.php` — Verificadores ocultos anti-pirataria

### Regras invioláveis
1. O license gate em `aireset-expresso-order.php` DEVE permanecer antes dos requires de negócio
2. O trait `EOP_License_Guard` DEVE estar presente em: `EOP_Admin_Page`, `EOP_Ajax_Handlers`, `EOP_Order_Creator`
3. Cada classe com o trait DEVE chamar um dos métodos de verificação no `init()` ou método principal
4. NUNCA remover chamadas a `_dispatch_status_report()`, `_flag_env_status()`, `quick_validate()` ou `verify_class_integrity()`
5. O `OPT_PREFIX` (`Aireset-ExpressoOrder`) NÃO pode ser alterado após deploy
6. Placeholders `%%PRODUCT_ID%%` e `%%ENC_KEY%%` devem ser substituídos com valores reais antes do deploy

### Ao adicionar novas classes
- Inclua `use EOP_License_Guard;` na classe
- Adicione verificação no `init()` ou no método público principal:
  ```php
  if ( ! self::_resolve_env_config() ) { return; }
  ```

## Padrões PHP
- Sanitize toda entrada: `sanitize_text_field()`, `sanitize_email()`, `absint()`
- Escape toda saída: `esc_html()`, `esc_attr()`, `esc_url()`
- Nonces em forms admin: `wp_nonce_field()` / `check_admin_referer()`
- Nonces em AJAX: `wp_create_nonce()` / `check_ajax_referer()`
- Capabilities: `edit_shop_orders` para vendedores, `manage_options` para admin

## Licença
Este plugin é proprietário (não GPL). Não adicione headers GPL em arquivos novos.
Use: `@license Proprietary`
