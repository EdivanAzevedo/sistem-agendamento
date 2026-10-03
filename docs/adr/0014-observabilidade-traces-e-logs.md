# 0014 — Observabilidade: traces com OpenTelemetry e logs estruturados

- **Status:** Aceita
- **Data:** 2026-10-02

## Contexto

Para investigar um problema relatado por um usuário, é preciso reconstruir o que aconteceu naquela
requisição: rotas, consultas ao banco, tempos e erros. Logs soltos e sem correlação não bastam, e
dados pessoais (e-mails, códigos, IDs de sessão) não podem ir parar em logs e traces, como exige o
modelo de ameaças.

## Decisão

- **Traces com OpenTelemetry**, pela instrumentação automática oficial do Laravel (extensão
  `opentelemetry` 1.4.2 e pacote `opentelemetry-auto-laravel`): requisições HTTP, consultas SQL,
  cache, filas e chamadas HTTP externas viram spans sem código manual. A exportação acontece depois
  que a resposta já foi enviada ao cliente.
- **Um coletor local** recebe os traces (OTLP/HTTP com protobuf) e os repassa ao Jaeger no
  desenvolvimento; em produção, o destino será o Grafana Cloud quando o deploy for retomado.
- **O trace começa no PHP.** O Caddy descarta `traceparent` e `tracestate` vindos de fora, para que
  nenhum cliente escolha os nossos trace ids. A propagação interna (por exemplo, para jobs da fila)
  continua usando o padrão W3C Trace Context.
- **Um único id de rastreio**: o `X-Trace-Id` das respostas e o `trace_id` dos erros
  ([ADR 0006](0006-respostas-de-erro-rfc-9457.md)) passam a ser o próprio trace id do OpenTelemetry.
  Sem tracing ativo, um id no mesmo formato é gerado, sem mudar o contrato.
- **Um id por requisição**: o Caddy atribui o `X-Request-Id` (sobrescrevendo qualquer valor do
  cliente), a API o devolve na resposta e o registra em todos os logs.
- **Logs estruturados em todos os ambientes**: uma linha JSON por evento em stderr, com `trace_id`,
  `span_id` e `request_id`, e com o stack trace das exceções.
- **Dados sensíveis ficam de fora**:
  - o coletor remove a query string das URLs, as chaves de Redis e de cache (que contêm IDs de
    sessão e chaves de limite de requisições) e os detalhes das mensagens de erro de banco, antes de
    enviar qualquer trace. As regras ficam em `infra/otel/processors.yaml` e são verificadas no CI
    por `infra/otel/test-redaction.sh`, com o coletor real;
  - erros de banco são registrados sem os valores da consulta: SQL com placeholders, SQLSTATE e
    código do driver, nunca a mensagem do Laravel, que embute os valores;
  - as consultas SQL dos spans já usam placeholders.
- **Amostragem de 100%**, configurável pelas variáveis `OTEL_*`.
- **Fora desta decisão, por enquanto**: métricas (entram com o motor de disponibilidade, para medir
  o p95) e Sentry (entra com o deploy, pois exige conta).

## Consequências

- Qualquer erro relatado por um usuário leva, pelo `trace_id`, ao trace completo e aos logs daquela
  requisição.
- Medido na imagem de produção, o tracing custa cerca de 0,5 ms por requisição (`/up`: 3,2 ms com
  tracing contra 2,7 ms sem).
- Os testes rodam com um provedor de traces em memória: nenhum span de teste sai do processo, e os
  testes inspecionam os spans reais produzidos pela instrumentação.
- Comandos `artisan` também geram um span com o nome do comando, o que será útil para acompanhar
  tarefas agendadas.
- Os dados chegam ao coletor ainda sem redação, pela rede interna; a redação no coletor é o ponto
  único que precisa estar correto, e por isso é testada no CI.
- Dois componentes a mais no ambiente local (coletor e Jaeger) e duas extensões PHP compiladas na
  imagem.
