# Changelog

Todas as alteracoes relevantes do plugin `Aireset Expresso Order` devem ser registradas aqui.

## 1.1.27 - 2026-04-16

- atualizacao do fluxo de build para gerar zip de release padrao e evitar empacotamentos quebrados
- agora o pacote inclui readme.txt na raiz e exclui arquivos de documentacao interna
- arquivos com transicao PHP/HTML sao pulados da ofuscacao para evitar erros na ativacao

## 1.1.26 - 2026-04-15

- workflow de release valida a integridade do pacote antes de anexar o zip ao GitHub Release
- `class-admin-page.php` passou a entrar no pacote final junto com os arquivos essenciais do plugin

## 1.1.25 - 2026-04-15

- separacao do sistema em `verification core` e `integrity core`
- integridade da distribuicao agora valida a presenca dos arquivos essenciais e pode exibir aviso no admin
- refinado o visual de quantidade, desconto e subtotal nos cards de item do painel
- pipeline de release atualizado para gerar pacote distribuivel mais consistente

## 1.1.24 - 2026-04-15

- corrigido o acesso aos submenus `Pedidos` e `Ativacao` do Pedido Expresso no admin
- submenu continua registrado no WordPress e agora e ocultado apenas no DOM pelo flyout

## 1.1.23 - 2026-04-15

- restaurado o comportamento do flyout do admin do Pedido Expresso apos regressao no submenu
- ajuste das permissoes do menu para `vendedor_expresso` manter o pai `Aireset`
- flyout do Pedido Expresso agora exibe apenas itens compativeis com a capability do usuario

## 1.1.22 - 2026-04-14

- ajuste do flyout do admin para usar o menu raiz correto do `Aireset`, mantendo a estrutura `Aireset > Pedido Expresso > Configuracoes, Pedidos, Ativacao`
- limpeza do bootstrap principal para evitar texto corrompido por encoding

## 1.1.21 - 2026-04-14

- reversao da mudanca que transformava `Pedido Expresso` em menu pai do admin
- retorno do plugin para a estrutura filha de `Aireset`, com submenu flyout proprio no item `Pedido Expresso`
- ocultacao dos itens auxiliares do menu raiz e exposicao de `Configuracoes`, `Pedidos` e `Ativacao` no flyout do plugin

## 1.1.20 - 2026-04-14

- reorganizacao do menu admin para `Pedido Expresso` funcionar como menu pai real do plugin
- exibicao correta dos submenus `Configuracoes`, `Pedidos` e `Ativacao` no padrao esperado do WordPress
- ajuste complementar da integracao da tela de ativacao para conviver com a nova estrutura de menu

## 1.1.19 - 2026-04-14

- adicao de botoes de menos e mais ao redor do campo de quantidade em cada item do pedido
- campo de desconto por item e desconto geral passam a exibir teclado numerico decimal no mobile via inputmode

## 1.1.18 - 2026-04-14

- correcao do encoding de entidades HTML no preco dos produtos na busca (R$ aparecia como &#82;&#36;)
- nova configuracao "Modo do desconto" nas configuracoes do plugin (porcentagem, valor fixo ou ambos)
- campo de desconto por item e desconto geral agora respeitam o modo configurado

## 1.1.17 - 2026-04-13

- reforco da integracao do campo de logo com a biblioteca de midia do WordPress
- ajuste das dependencias do uploader e do binding JavaScript para seguir o padrao funcional usado pelo plugin de PDF invoices

## 1.1.16 - 2026-04-13

- substituicao do campo manual de logo por um uploader com biblioteca de midia do WordPress
- adicao de preview, troca e remocao da logo direto na tela de configuracoes
- melhoria do visual do bloco de logo para ficar coerente com o restante do admin do plugin

## 1.1.15 - 2026-04-13

- conversao da listagem de itens da proposta publica para cards visuais de produto
- exibicao de imagem, quantidade, SKU e subtotal por item para deixar a proposta mais bonita e facil de revisar

## 1.1.14 - 2026-04-13

- refatoracao da base do frontend para um SCSS mais correto, com mixins utilitarios, funcao de alpha e nesting real nos componentes principais
- melhoria do escopo dos seletores compilados para evitar combinacoes globais indevidas e manter prioridade sem perder previsibilidade

## 1.1.13 - 2026-04-13

- reforco da cadeia SCSS do frontend para deixar explicito que `frontend.css` e gerado a partir de `assets/scss/frontend`
- aumento da especificidade dos estilos base de botao, notice e wrappers para reduzir conflitos com o CSS do tema

## 1.1.12 - 2026-04-13

- correcao da navegacao SPA para limpar a sessao de edicao e resetar o formulario ao clicar em `Novo pedido`
- garantia de que o vendedor sempre inicia um pedido novo com estado limpo ao sair de uma edicao

## 1.1.11 - 2026-04-13

- correcao do fluxo de salvar pedidos para persistir, recarregar e executar o recalculo real do WooCommerce a cada criacao e edicao
- sincronizacao dos totais do pedido e da proposta publica com os descontos por item e descontos gerais ja aplicados

## 1.1.10 - 2026-04-13

- substituicao do switch de pagamento apos confirmacao por um componente visual funcional baseado no padrao do `checkout-aireset`
- ajuste do HTML, CSS e JavaScript da tela de configuracoes para refletir claramente os estados `Ativado` e `Desativado`

## 1.1.9 - 2026-04-13

- refinamento da comunicacao no admin para deixar a opcao de pagamento apos confirmacao mais clara e menos tecnica
- ajuste do titulo e da descricao do switch para refletir o comportamento real da proposta confirmada

## 1.1.8 - 2026-04-13

- limpeza do admin para manter a operacao de criar e editar pedidos somente no SPA
- menu do plugin no admin simplificado para foco em `Configuracoes` e `Pedidos`
- nova personalizacao visual dedicada para a pagina publica do cliente
- proposta confirmada agora pode exibir botao de pagamento em vez de redirecionamento automatico

## 1.1.7 - 2026-04-13

- correcao do link de PDF no fluxo SPA para remover entidades HTML da URL antes do envio ao navegador
- ajuste de compatibilidade com o endpoint `generate_wpo_wcpdf` do plugin `PDF Invoices & Packing Slips for WooCommerce`

## 1.1.6 - 2026-04-13

- edicao de pedidos agora pode ser aberta e salva diretamente dentro do SPA do shortcode
- novo estado visual de edicao no painel com banner e cancelamento rapido
- ajuste da integracao com `PDF Invoices & Packing Slips for WooCommerce` para usar o endpoint oficial do plugin ao gerar links de PDF
- reforco de permissao para vendedores acessarem e editarem apenas os pedidos expresso que pertencem a eles

## 1.1.5 - 2026-04-13

- transformacao do shortcode em experiencia de painel com navegacao SPA entre `Novo pedido` e `Pedidos`
- nova listagem frontend de pedidos com atualizacao por AJAX, filtros por status e busca textual
- administradores agora visualizam todos os pedidos do plugin e vendedores visualizam apenas os pedidos criados por eles
- persistencia do vendedor criador no pedido para suportar rastreio e filtragem por responsavel

## 1.1.4 - 2026-04-13

- correcao da regressao visual no frontend apos a simplificacao do desconto
- restauracao da estrutura visual dos cards de item e dos accordions no shortcode
- ajuste do comportamento dos icones de abrir e fechar nos accordions

## 1.1.3 - 2026-04-13

- simplificacao do desconto em todas as telas do pedido para um unico campo textual
- agora `10%` aplica desconto percentual e `10` aplica desconto fixo em reais
- ajuste dos cards de item, tela de criacao e tela de edicao para manter o mesmo comportamento de desconto

## 1.1.2 - 2026-04-13

- troca do login customizado do shortcode para o fluxo nativo do `wp-login.php`, com `redirect_to` de volta para a pagina do pedido
- ajuste no `login_redirect` para respeitar redirecionamento solicitado quando o acesso vier do frontend
- melhora de compatibilidade para sessao persistente em ambientes locais e instalacoes com comportamento diferente de cookie

## 1.1.1 - 2026-04-13

- reforco no login do shortcode com `wp_set_current_user()`, `wp_set_auth_cookie()` e hook `wp_login` para persistir melhor a sessao
- blindagem de compatibilidade nas telas do plugin para nao quebrar caso o servidor esteja com deploy parcial das funcoes de fonte
- alinhamento de versao no bootstrap do plugin e no `package.json`

## 1.1.0 - 2026-04-13

- suporte a desconto por produto em `R$` ou `%`
- suporte a desconto geral em `R$` ou `%`
- nova experiencia do shortcode com cards de item, accordions e fluxo de frete mais guiado
- seletor de fontes, color picker e switch na tela de configuracoes
