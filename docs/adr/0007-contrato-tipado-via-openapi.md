# 0007 — Contrato tipado entre frontend e backend via OpenAPI

- **Status:** Aceita
- **Data:** 2026-10-02

## Contexto

O SPA e a API evoluem juntos, no mesmo repositório. Sem um contrato verificável, o frontend pode
assumir campos que a API não envia, e tipos escritos à mão se desatualizam em silêncio. A
documentação da API também precisa refletir o código real; documentação escrita à parte envelhece
na primeira mudança.

## Decisão

- **A documentação é gerada do código** pelo [Scramble](https://scramble.dedoc.co) (OpenAPI 3.1),
  que lê rotas, validações, respostas e exceções lançadas, sem anotações duplicadas. Ela é servida
  em `/docs/api` (interface Scalar) e `/docs/api.json`.
- **A documentação é pública em todos os ambientes**: a API já é pública, e esconder a
  documentação não protegeria nada. Como, sem cache, cada requisição analisa o código para montar o
  documento, as rotas têm limite de 30 requisições por minuto por IP, e o deploy precisa rodar
  `php artisan scramble:cache` ao iniciar o container (o teste de inicialização da imagem garante
  que o comando funciona).
- **Os erros seguem a [ADR 0006](0006-respostas-de-erro-rfc-9457.md) também na documentação**:
  extensores próprios substituem os formatos de erro padrão do Laravel por `application/problem+json`,
  e os esquemas `ProblemDetails` e `ValidationProblemDetails` estão sempre presentes no documento.
  Falhas de domínio aparecem com o próprio status, título e slug.
- **O contrato é versionado**: `api/openapi.json` (exportado pelo Scramble) e
  `web/src/api/schema.d.ts` (gerado pelo [openapi-typescript](https://openapi-ts.dev)). O CI
  regenera os dois e falha se forem diferentes do que está no repositório; se a API mudar de forma
  incompatível, o `vue-tsc` quebra no CI.
- **O servidor do documento é relativo** (`/api/v1`), para que o contrato exportado seja idêntico
  em qualquer ambiente.
- **O cliente HTTP do frontend** usa o `openapi-fetch`, tipado pelo contrato, com um middleware que
  transforma toda resposta de erro em `ApiError` (status, documento de erro, slug do `type`, erros
  por campo e `trace_id`).
- **Idioma**: os PHPDoc que viram documentação da API (endpoints e campos) são escritos em pt-BR;
  os demais comentários de código continuam em inglês.
- **A interface Scalar** é carregada do CDN com versão fixa e Subresource Integrity, e sem o proxy
  de terceiros da Scalar: a documentação compartilha a origem do app, então um arquivo adulterado
  nunca pode ser executado.

## Consequências

- Toda mudança de contrato aparece no diff do commit (`openapi.json` e `schema.d.ts`).
- Depois de mudar a API, é preciso regenerar o contrato (`composer api:docs` e `npm run api:types`);
  o CI avisa quando isso for esquecido.
- O Scramble infere o que consegue a partir do código; casos que ele não inferir exigirão anotações.
- O `openapi-typescript` ainda declara suporte só ao TypeScript 5; um `overrides` no `package.json`
  libera o TypeScript 6 apenas para ele, até o suporte oficial chegar.
- A política de segurança de conteúdo (CSP) precisará liberar `cdn.jsdelivr.net` nas rotas de
  documentação.
