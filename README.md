# Sistema de Agendamento

Multi-tenant scheduling SaaS for service businesses (salons, barbershops, clinics, studios).
Each business manages its team, services and a public page where customers book appointments.

> Status: early development — project foundation.

## Stack

| Layer    | Technology                                                      |
| -------- | --------------------------------------------------------------- |
| API      | Laravel 13 (PHP 8.3, php-fpm), MySQL 8, Redis                    |
| Web      | Vue 3 + TypeScript SPA, Vite, Pinia, Tailwind CSS, shadcn-vue    |
| Edge     | Caddy (SPA and API served from the same origin)                 |
| Tooling  | Pest, Larastan (max level), Pint, Vitest, ESLint, Prettier      |

## Repository layout

```
api/     Laravel application (modular monolith under app/Modules)
web/     Vue single-page application
infra/   Container images, Caddy and database bootstrap
docs/    Architecture decision records
```

## Running locally

Requirements: Docker or Podman with Compose v2.

```sh
cp api/.env.example api/.env
docker compose up -d --build        # or: podman compose up -d --build
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

| URL                         | What                                      |
| --------------------------- | ----------------------------------------- |
| http://localhost:8080       | Web app (API under `/api/v1`)             |
| http://localhost:8080/up    | Liveness check                            |
| http://localhost:8080/ready | Readiness check (database, Redis, queue)  |
| http://localhost:8025       | Mailpit (captured e-mails)                |

On Linux hosts, export `UID` and `GID` before building so files created inside the
container belong to your user.

## Quality checks

```sh
# API
docker compose exec api php artisan test       # Pest, against a real MySQL database
docker compose exec api vendor/bin/pint --test
docker compose exec api vendor/bin/phpstan analyse

# Web (from web/)
npm run lint
npm run type-check
npm run test
```
