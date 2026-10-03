# 0015 — Headers de segurança e política de segurança de conteúdo (CSP)

- **Status:** Aceita
- **Data:** 2026-10-02

## Contexto

O SPA autentica por cookie na mesma origem da API. Um script injetado (XSS) teria o mesmo poder
do usuário logado, então a defesa não pode depender só do escape automático do Vue. Também é
preciso impedir que o app seja embutido em outro site (clickjacking), que tokens presentes em links
(convites, redefinição de senha) vazem pelo header `Referer` e que recursos de terceiros exponham o
IP dos visitantes.

## Decisão

- **Headers no Caddy de produção, em toda resposta:**
  - CSP padrão restritiva: `default-src 'self'`, `script-src 'self'`, `style-src 'self'`,
    `img-src 'self'`, `font-src 'self'`, `connect-src 'self'`, `object-src 'none'`,
    `base-uri 'none'`, `form-action 'self'` e `frame-ancestors 'none'`;
  - HSTS de 1 ano com `includeSubDomains`, sem `preload` (que não é possível num subdomínio do
    DuckDNS);
  - `X-Content-Type-Options: nosniff` e `X-Frame-Options: DENY` (para navegadores antigos);
  - `Referrer-Policy: same-origin`, para que URLs com tokens nunca sejam enviadas a outros sites;
  - `Permissions-Policy` negando câmera, microfone, localização, sensores, pagamento e USB;
  - `Cross-Origin-Opener-Policy` e `Cross-Origin-Resource-Policy` como `same-origin`;
  - remoção dos headers `Server` e `Via`.
- **A CSP vale só na imagem de produção.** Em desenvolvimento, o servidor do Vite precisa de
  estilos inline e de websocket; os demais headers (exceto HSTS, inútil em HTTP) valem também em
  dev.
- **Rotas com necessidades próprias enviam a sua política**, e o Caddy a preserva (a CSP padrão
  só é aplicada quando a resposta não traz uma). Hoje, só a documentação da API (`/docs/api`):
  - o script inline que inicializa a Scalar é liberado por um **nonce por requisição**, nunca por
    `'unsafe-inline'`;
  - só o arquivo fixado da Scalar no CDN é permitido (com Subresource Integrity, ver
    [ADR 0007](0007-contrato-tipado-via-openapi.md));
  - estilos inline são permitidos apenas ali, porque a Scalar os injeta em tempo de execução;
  - as fontes de terceiros da Scalar são desligadas;
  - a sondagem de `new Function('')` feita pelo Zod (embutido na Scalar) é bloqueada de propósito:
    ele cai no modo interpretado, e `'unsafe-eval'` nunca é liberado.
- **Nenhum recurso externo no build do SPA.** Fontes são servidas pelo próprio app; uma checagem
  no CI reprova o build se o HTML ou o CSS referenciarem outra origem.
- **Verificação com navegador real.** Testes com Playwright (Chromium) rodam no CI contra as
  imagens de produção e reprovam com qualquer violação de CSP ou erro no console. O navegador
  acessa o app por `localhost`, uma origem segura como o HTTPS de produção, para que políticas como
  a COOP sejam aplicadas de verdade. As imagens só são publicadas depois que esses testes passam.
- **Relatórios de violação** (`report-to`) ficam para quando houver usuários reais, junto com o
  deploy.

## Consequências

- Integrações futuras que carregam recursos de outra origem exigirão ajuste explícito da CSP: as
  imagens no object storage (`img-src` com o domínio do storage) e o Stripe (scripts e frames). Os
  testes de navegador mostram a quebra antes de qualquer publicação.
- Mais um job no CI (cerca de 2 minutos), que baixa a imagem oficial do Playwright.
- A documentação depende do CDN em tempo de execução; no CI, uma nova tentativa absorve falhas
  momentâneas de rede, e um teste que só passa na segunda tentativa aparece como instável no
  relatório.
