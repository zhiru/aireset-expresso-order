# Identidade Visual Admin Aireset

Este documento define o shell visual base para plugins administrativos da Aireset.

## Objetivo

Padronizar menus, bordas, superficies e estados de navegacao entre plugins, mantendo liberdade apenas no bloco hero de cada produto.

## Regras do shell

- sidebar com base navy escura e gradiente sutil, sem blocos brancos chapados no estado ativo
- bordas discretas em baixa opacidade e sombras curtas, evitando brilho excessivo
- item ativo principal com gradiente navy + teal e anel interno suave
- submenu com fundo translcido, recuo leve e destaque lateral teal no hover e no ativo
- tipografia do hero continua especifica por plugin; nao usar copy generica obrigatoria
- o hero pode variar por produto, mas a navegacao lateral deve manter a mesma linguagem visual
- headers das views SPA podem compartilhar o mesmo gradiente do shell para unificar a navegacao com o conteudo
- wrappers globais do SPA nao devem sobrescrever formularios ou containers de modulos que ja possuam design proprio validado
- configuracoes, PDF e licenca devem manter seus cards internos e apenas receber o shell, espacamento e headers compartilhados

## Tokens de referencia

- fundo da sidebar: `rgba(9, 21, 51, 0.98)` ate `rgba(15, 37, 87, 0.98)`
- gradiente ativo: `rgba(21, 50, 103, 0.95)` ate `rgba(22, 91, 121, 0.9)`
- acento: `#32d1c7`
- borda da sidebar: `rgba(255, 255, 255, 0.06)`
- borda interna ativa: `rgba(80, 201, 194, 0.14)`

## Aplicacao no Expresso Order

- manter o hero com `panel_title` e `panel_subtitle`
- aplicar o padrao apenas no shell lateral, grupos, submenus e estados ativos
- nao acoplar a paleta administrativa aos controles de cor da proposta do cliente
- preservar o visual nativo de `.eop-settings-form`, `.eop-settings-card` e `.el-license-container`, evitando wrappers que redefinam borda, fundo ou sombra nesses modulos

## Referencias internas

- Pedido Expresso: `assets/scss/admin/_spa-shell.scss`
- Checkout: `../aireset-checkout/backend/assets/scss/admin/partials/spa/_navigation.scss`

Use este guia como referencia base ao replicar o shell administrativo em outros plugins Aireset.
