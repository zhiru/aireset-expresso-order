# Aireset - Expresso Order

Plugin WordPress para operacao comercial com WooCommerce, focado em criacao rapida de pedidos, propostas publicas e administracao em uma interface SPA no painel.

## Versao atual

`1.1.24`

## Principais recursos

- criacao rapida de pedidos expresso com busca de produtos e cliente
- painel administrativo SPA em `Aireset > Pedido Expresso`
- navegacao interna para `Novo pedido`, `Pedidos`, `Configuracoes` e `Licenca`
- tela publica de proposta para compartilhamento com o cliente
- calculo de frete e descontos integrados ao WooCommerce
- controle de identidade visual da proposta e do painel
- sistema de licenca integrado ao Elite Licenser
- suporte ao perfil `vendedor_expresso`

## Requisitos

- WordPress
- WooCommerce ativo
- licenca valida do plugin para liberar o carregamento completo

## Estrutura do plugin

- [`aireset-expresso-order.php`](./aireset-expresso-order.php): bootstrap principal e gate de licenca
- [`includes/`](./includes): regras de negocio, admin, licenca, AJAX, pedidos e configuracoes
- [`templates/`](./templates): templates do admin SPA, listagens e shortcode
- [`assets/js/`](./assets/js): comportamento do admin, configuracoes e flyout
- [`assets/scss/`](./assets/scss): fonte SCSS do frontend e do admin
- [`assets/css/`](./assets/css): CSS compilado distribuido com o plugin

## Desenvolvimento

Instale as dependencias de front-end e use os scripts abaixo para compilar os estilos:

```bash
npm install
npm run build:css
npm run watch:css
```

Os estilos sao compilados a partir de:

- `assets/scss/frontend/style.scss` -> `assets/css/frontend.css`
- `assets/scss/admin/style.scss` -> `assets/css/admin.css`
- `assets/scss/admin/settings.scss` -> `assets/css/settings-admin.css`

## Fluxo administrativo

Com a licenca ativa, o plugin opera a partir da ancora:

- `wp-admin/admin.php?page=eop-pedido-expresso`

Views SPA disponiveis:

- `?view=new-order`
- `?view=orders`
- `?view=settings`
- `?view=license`

Sem licenca ativa, o plugin mantem apenas a tela de ativacao e nao exibe submenu proprio de licenca.

## Licenca

O plugin usa integracao proprietaria com Elite Licenser.

- classe base: [`includes/class-eop-license-base.php`](./includes/class-eop-license-base.php)
- manager: [`includes/class-eop-license-manager.php`](./includes/class-eop-license-manager.php)
- guard de carregamento: [`includes/trait-eop-license-guard.php`](./includes/trait-eop-license-guard.php)

## Changelog

O historico completo de alteracoes esta em [`CHANGELOG.md`](./CHANGELOG.md).

Destaques recentes da serie `1.1.24`:

- consolidacao do menu `Aireset > Pedido Expresso` com flyout proprio
- ajuste do comportamento SPA no admin
- integracao e refinamento visual do sistema de licenca
- reorganizacao da cadeia SCSS e compilacao do admin

## Autor

Aireset Agencia Web  
https://aireset.com.br
