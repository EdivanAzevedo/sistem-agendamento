# Sistema de Agendamento

[![CI](https://github.com/EdivanAzevedo/sistem-agendamento/actions/workflows/ci.yml/badge.svg)](https://github.com/EdivanAzevedo/sistem-agendamento/actions/workflows/ci.yml)

SaaS de agendamento multi-tenant para negócios de serviço (salões, barbearias, clínicas, estúdios).
Cada negócio gerencia sua equipe, seus serviços e uma página pública onde os clientes agendam
horários.

> Status: desenvolvimento inicial — fundação do projeto.

## Stack

| Camada      | Tecnologia                                                       |
| ----------- | ---------------------------------------------------------------- |
| API         | Laravel 13 (PHP 8.3, php-fpm), MySQL 8, Redis                    |
| Web         | SPA em Vue 3 + TypeScript, Vite, Pinia, Tailwind CSS, shadcn-vue |
| Borda       | Caddy (SPA e API servidos na mesma origem)                       |
| Ferramentas | Pest, Larastan (nível máximo), Pint, Vitest, ESLint, Prettier    |

## Estrutura do repositório

```
api/     Aplicação Laravel (monólito modular em app/Modules)
web/     SPA em Vue
infra/   Imagens de container, Caddy e inicialização do banco
docs/    Registros de decisões de arquitetura (ADRs)
```

## Como rodar localmente

Requisitos: Docker ou Podman com Compose v2.

```sh
cp api/.env.example api/.env
docker compose up -d --build        # ou: podman compose up -d --build
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

| Endereço                       | O que é                                         |
| ------------------------------ | ----------------------------------------------- |
| http://localhost:8080          | Aplicação web (API em `/api/v1`)                |
| http://localhost:8080/up       | Verificação de vida (liveness)                  |
| http://localhost:8080/ready    | Verificação de prontidão (banco, Redis e fila)  |
| http://localhost:8080/docs/api | Documentação da API (OpenAPI, interface Scalar) |
| http://localhost:16686         | Jaeger (traces das requisições)                 |
| http://localhost:8025          | Mailpit (e-mails capturados)                    |

Em hosts Linux, exporte `UID` e `GID` antes do build para que os arquivos criados dentro do
container pertençam ao seu usuário.

## Verificações de qualidade

```sh
# API
docker compose exec api composer test       # Pest, contra um MySQL de verdade
docker compose exec api composer lint       # Pint (só verifica)
docker compose exec api composer analyse    # Larastan, nível máximo

# Web (dentro de web/)
npm run check:format    # Prettier (só verifica)
npm run check:lint      # oxlint + ESLint (só verifica)
npm run type-check      # vue-tsc
npm run test            # Vitest
```

Para corrigir formatação e lint automaticamente no frontend: `npm run format` e `npm run lint`.

## Contrato da API

A documentação da API é gerada do código pelo Scramble e versionada junto com os tipos do frontend
([ADR 0007](docs/adr/0007-contrato-tipado-via-openapi.md)). Depois de mudar a API, regenere os dois:

```sh
docker compose exec api composer api:docs   # atualiza api/openapi.json
cd web && npm run api:types                  # atualiza web/src/api/schema.d.ts
```

O CI falha se algum dos dois estiver desatualizado.

## Observabilidade

Decisões em [ADR 0014](docs/adr/0014-observabilidade-traces-e-logs.md).

- **Traces**: cada requisição vira um trace (HTTP, SQL, cache, filas, chamadas externas), enviado
  ao coletor do OpenTelemetry e visível no Jaeger em http://localhost:16686. O header `X-Trace-Id`
  da resposta (e o `trace_id` dos erros) é o id do trace.
- **Logs**: uma linha JSON por evento em stderr, com `trace_id`, `span_id` e `request_id`. Para ler
  no terminal: `docker compose logs api | jq`.
- **Dados sensíveis**: o coletor remove query strings, chaves de Redis e de cache e detalhes de erros
  de banco antes de enviar os traces (regras em [`infra/otel/processors.yaml`](infra/otel/processors.yaml),
  testadas por [`infra/otel/test-redaction.sh`](infra/otel/test-redaction.sh)); erros de banco são
  registrados sem os valores da consulta.

## Integração contínua

Todo push na `main` e todo pull request executam:

1. **API** — Pint, Larastan (nível máximo), Pest contra MySQL e Redis com cobertura mínima de 90%,
   checagem de que `openapi.json` está atualizado e `composer audit`.
2. **Web** — Prettier, oxlint + ESLint, checagem de que os tipos gerados da API estão atualizados,
   `vue-tsc`, Vitest, `npm audit` e build de produção.
3. **Coletor** — teste das regras de redação do OpenTelemetry com o coletor real.
4. **Imagens** — gera as imagens de produção `api` (PHP-FPM) e `edge` (Caddy + SPA) a partir de
   [`infra/docker/Dockerfile`](infra/docker/Dockerfile), executa testes de inicialização
   ([`smoke-image.sh`](.github/scripts/smoke-image.sh)) e faz a varredura com o Trivy.
   Vulnerabilidades HIGH ou CRITICAL com correção disponível reprovam o build; exceções com prazo
   ficam em [`.trivyignore.yaml`](.trivyignore.yaml), cada uma com motivo e data de expiração.

Na `main`, cada imagem é publicada uma única vez, identificada pelo digest. Só depois que esse
mesmo digest passa nos testes de inicialização e na checagem de vulnerabilidades ele recebe as tags
(`sha-<commit>` e `latest`) no GitHub Container Registry. Nada é reconstruído entre a verificação e
a publicação.

O CI testa nas mesmas versões que vão para produção: PHP (com as mesmas extensões), Node e Composer são lidos do Dockerfile,
e MySQL e Redis sobem a partir do [`compose.yaml`](compose.yaml). Todas as actions de terceiros são
fixadas por SHA de commit, e o Dependabot mantém actions, imagens base e dependências atualizadas.
