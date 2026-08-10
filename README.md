# Softphoria Platform

Laravel modular monolith for the Softphoria platform (Blade, Livewire-ready,
Filament-ready), running in an isolated Docker development environment on
PHP 8.4.

This README covers local development only. See `docs/DOCKER.md` for
infrastructure detail and `docs/` for the approved project specifications.

## Stack

- Laravel 13 (PHP 8.4)
- Blade, Livewire, Filament (installed; not yet scaffolded)
- MariaDB 10.11 LTS
- Redis 7 (cache, session, queue)
- Nginx
- Vite / Node.js 22

## Important: host PHP 8.2 vs. container PHP 8.4

This machine's global PHP install (`C:\xampp\php`, PHP 8.2.12, used by
XAMPP and other projects) is **never** used for Softphoria. All
Composer/Artisan/PHP/Node commands for this project must run **inside the
Docker `app` container**, which runs PHP 8.4. Do not run `php`, `composer`,
or `artisan` directly on the Windows host for this project.

XAMPP's Apache (port 80) and MySQL (port 3306) keep running independently
and are untouched by this setup — Softphoria uses different host ports
(8080, 3307) specifically to avoid colliding with them.

## Starting Softphoria

```
docker compose up -d
```

Application: http://localhost:8080

## Stopping Softphoria

```
docker compose down
```

This does not affect XAMPP, which is managed separately via the XAMPP
control panel.

## Running Artisan commands

```
docker compose exec app php artisan <command>
```

Example:

```
docker compose exec app php artisan about
```

## Running Composer commands

```
docker compose exec app composer <command>
```

Example:

```
docker compose exec app composer require <package>
```

## Running Node/NPM commands

Node.js 22 is available inside the `app` container:

```
docker compose exec app npm install
docker compose exec app npm run dev
```

## Running tests

```
docker compose exec app php artisan test
```

## Database connection

| | Value |
|---|---|
| Connection | `mysql` (MariaDB 10.11 LTS) |
| Host (from inside containers) | `mariadb` |
| Port (from inside containers) | `3306` |
| Host (from Windows tools, e.g. MySQL Workbench) | `localhost` |
| Port (from Windows tools) | `3307` |
| Database | `softphoria` |

Laravel always connects via `DB_HOST=mariadb` / `DB_PORT=3306` — never
`localhost`/`127.0.0.1` and never port `3307`. The `3307` host mapping
exists solely for external database GUI tools running on Windows.

## Redis connection

| | Value |
|---|---|
| Host (from inside containers) | `redis` |
| Port | `6379` |
| Exposed to Windows host? | **No** |

Redis is reachable only from other containers on the `softphoria_net`
Docker network. It has no host port mapping by design.

Redis backs the cache, session, and queue drivers (`CACHE_STORE`,
`SESSION_DRIVER`, `QUEUE_CONNECTION` are all `redis`).

## Environment setup (first time)

```
cp .env.example .env
```

Fill in `DB_PASSWORD` and `DB_ROOT_PASSWORD` in `.env` with local
development values (this file is git-ignored and never committed).

```
docker compose up -d
docker compose exec app php artisan key:generate
```

## Project status

This repository currently contains the Laravel application foundation only
(CORE-001). No business modules, authentication, admin panel, or public
pages have been implemented yet — see
`docs/Softphoria_Platform_Implementation_Guide_v1.4_Laravel_Jacob_Approved.md`
for the full staged roadmap.
