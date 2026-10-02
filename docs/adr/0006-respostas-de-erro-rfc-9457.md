# 0006 — Respostas de erro seguem a RFC 9457 (Problem Details)

- **Status:** Aceita
- **Data:** 2026-10-02

## Contexto

Hoje a API é consumida pelo nosso próprio SPA e, no futuro, pode ser consumida por terceiros. Os
clientes precisam distinguir falhas com segurança (por exemplo, "o horário acabou de ser reservado"
versus "o limite do plano foi atingido") sem interpretar mensagens escritas para pessoas, e o
suporte precisa de um jeito de ligar uma falha relatada por um usuário aos logs do servidor. O
formato de erro padrão do Laravel varia entre tipos de exceção e, em modo de debug, expõe stack
traces.

## Decisão

Todo erro gerado ao atender `/api/*` (ou qualquer requisição que espera JSON) é devolvido como um
documento RFC 9457, com `Content-Type: application/problem+json`:

```json
{
  "type": "https://<app-url>/problems/validation-error",
  "title": "Dados inválidos",
  "status": 422,
  "detail": "Alguns campos não foram preenchidos corretamente.",
  "errors": { "email": ["O campo e-mail é obrigatório."] },
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736"
}
```

- **`type`**: falhas HTTP genéricas (401, 403, 404, 405, 419, 429, 500, …) usam `about:blank`, e o
  `title` é a descrição do status, como a RFC recomenda. Falhas específicas da aplicação recebem um
  URI absoluto em `{APP_URL}/problems/{slug}`. O slug faz parte do contrato público: os clientes
  tomam decisões com base nele, nunca em `title` ou `detail`. Como a URL base muda de um ambiente
  para outro, os clientes comparam apenas o slug (o último segmento do caminho), nunca o URI
  completo.
- **Falhas de domínio** estendem `App\Support\Problems\ProblemException`, que declara o status e o
  slug. Como são resultados esperados, **não são registradas** como erros da aplicação.
- **Idioma**: `title` e `detail` são traduzidos pelos arquivos de tradução do Laravel
  (`lang/{locale}/problems.php`), em português (pt-BR) por padrão, para que o SPA possa exibi-los
  como vieram. A resposta declara o idioma no header `Content-Language`.
- **Validação** (`422`) traz o membro `errors`, organizado por campo, com uma lista de mensagens
  para cada um — o mesmo formato que o Laravel usa, que se encaixa direto nas bibliotecas de
  formulário.
- **`trace_id`**: toda resposta traz o header `X-Trace-Id`, e todo documento de erro repete o valor
  no corpo. Ele também entra no contexto de todas as linhas de log. O id segue o formato do W3C
  Trace Context (32 caracteres hexadecimais), para que mais tarde possa ser substituído pelo trace
  id do OpenTelemetry sem mudar o contrato.
- **Nenhum detalhe interno vaza**: erros inesperados devolvem um 500 genérico. Classe, mensagem e
  local da exceção só aparecem no membro `debug` **quando** `APP_DEBUG=true`. Mensagens de
  autorizações negadas e de chamadas a `abort()` nunca são devolvidas ao cliente.
- Headers com significado são preservados (`Retry-After` no 429, `Allow` no 405).
- Uma resposta explícita lançada de propósito (`HttpResponseException`, por exemplo vinda de um
  middleware) é devolvida sem alteração: é um resultado pretendido, não uma falha.

## Consequências

- Um único formato de erro em todos os endpoints: o SPA tem um só caminho de tratamento de erros e
  usa o `type` para comportamentos específicos (por exemplo, oferecer upgrade quando o limite do
  plano é atingido).
- Adicionar uma falha nova significa criar uma subclasse de `ProblemException` e suas traduções; o
  mapeamento para HTTP fica junto da exceção, e não numa tabela central.
- Os títulos de `about:blank` são traduzidos, o que a RFC permite; os clientes não devem comparar
  títulos.
- Como a API é servida na mesma origem que o SPA, o CORS fica totalmente desligado: nenhuma
  requisição de outra origem recebe acesso.
