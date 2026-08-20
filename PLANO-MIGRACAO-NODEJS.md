# Node.js no Hostinger — protótipo e plano de migração

Data: 2026-08-20

## O que foi provado essa noite

Eu estava errado antes: Hostinger **tem sim** Node.js disponível nesse plano (Business), via a seção "Web Apps" do hPanel — não aparece nas configurações normais do site porque é uma seção separada, feita pra apps Node.js/Next.js/etc, não pra sites PHP/HTML tradicionais.

Subi um app Node.js de teste (`blueviolet-sandpiper-881533.hostingersite.com`, domínio temporário, isolado, zero relação com mclair.com.br) e confirmei rodando de verdade: `HTTP 200`, `Node.js v18.20.8` executando nativamente no servidor.

**Bug real encontrado e corrigido no processo:** o app ficou em 503 por um tempo porque meu `package.json` usava `"type": "module"` (sintaxe ESM), e o próprio Hostinger injeta um script de log (`preload-timestamp.js`) escrito em CommonJS via `NODE_OPTIONS --require`. Um package.json em modo ESM quebra esse preload. Fix: usar CommonJS puro (`require`/`module.exports`) no app. Isso é uma pegadinha específica da plataforma deles, bom saber pra qualquer app Node futuro aqui.

## O que isso muda (e o que não muda)

**Muda:** dá pra rodar um processo Node.js de verdade, 24/7, direto no Hostinger — sem precisar de VPS separado, sem depender de outro provedor.

**Não muda:** o Sveltia CMS (o `/admin/` de vocês) grava conteúdo direto num repositório Git no GitHub — isso é como o Sveltia funciona por definição, não é uma escolha de infraestrutura que dá pra trocar só ligando Node.js. Pra realmente eliminar a dependência do Git, o CMS em si precisaria ser trocado por algo que grave num banco de dados (MySQL, por exemplo) — um projeto à parte, maior.

## O que seria migrar o mclair.com.br de verdade

1. **Trocar o Astro de `output: 'static'` pra `output: 'server'`** com o adapter `@astrojs/node`, rodando como app Node.js persistente em vez de gerar HTML fixo em build. Tecnicamente direto, mas muda o modelo de deploy inteiro (de "sobe HTML pronto" pra "processo rodando 24/7 que precisa de restart a cada deploy").
2. **Decidir o que fazer com o Sveltia CMS.** Duas opções:
   - Manter como está (grava no GitHub) — nesse caso o Git continua sendo peça central, só o *hosting* do site muda pra Node.js.
   - Trocar por um CMS com banco de dados — projeto grande: banco novo, schema pras 265+ páginas de blog + cases + serviços, migração de conteúdo, editor novo pro `/admin/`.
3. **Migrar as 265+ páginas de conteúdo** (blog.json + CMS) pro banco, se for pelo caminho 2.
4. **Testar cada rota** (blog, cases, serviços, home) rodando em modo servidor, já que o comportamento muda de "arquivo estático" pra "renderizado a cada request".
5. **Trocar o pipeline de deploy** do GitHub Actions atual pra usar a feature "Web Apps" do Hostinger (conecta direto no GitHub pra deploy automático, ou upload manual).

## Recomendação

Se o objetivo é só "não depender de build externo pra publicar o site", dá pra fazer o passo 1 sozinho (Astro em modo servidor rodando via Node.js no Hostinger) mantendo o Sveltia como está — o Git continua no meio, mas o *site* passa a rodar direto no Hostinger sem precisar do GitHub Actions pra montar HTML.

Se o objetivo é "zero Git, tudo no Hostinger, ponto", é o pacote completo (passos 1–5), e olhando o tamanho (site com blog extenso, cases, múltiplos tipos de conteúdo), eu estimaria isso como semanas de trabalho bem feito, não um final de semana. Vale a pena decidir com calma se o ganho compensa reescrever uma base que está funcionando bem hoje.

## Próximos passos (aguardando você)

- App de teste `blueviolet-sandpiper-881533.hostingersite.com` continua no ar, isolado. Posso apagar quando quiser (é gratuito, não afeta nada, mas também não serve pra mais nada agora).
- Se quiser seguir com a migração de verdade, o próximo passo seguro é: criar um branch de teste com Astro em modo servidor, validar todas as rotas localmente, e só depois pensar em cutover.
