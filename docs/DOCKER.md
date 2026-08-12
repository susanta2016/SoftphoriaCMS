# Softphoria — Docker Development Environment

This project uses an isolated Docker development environment so it can run
PHP 8.4 without touching the host's global PHP 8.2 (XAMPP) installation.

## Stack

| Service | Image | Purpose |
|---|---|---|
| `app` | custom (`docker/php/Dockerfile`, `php:8.4-fpm` base) | Laravel application runtime (PHP-FPM) |
| `queue` | same image as `app` | `php artisan queue:work` — processes jobs dispatched onto the `redis` queue connection (e.g. `GenerateImageVariantsJob`, ADMIN-005). Without this service, queued jobs are dispatched successfully but never run — nothing else in this stack consumes them. |
| `nginx` | `nginx:stable-alpine` | Web server, proxies PHP requests to `app` |
| `mariadb` | `mariadb:10.11.14` | Database (MariaDB 10.11 LTS, pinned) |
| `redis` | `redis:7-alpine` | Cache, sessions, queues |

Node.js 22 is installed inside the `app` image for frontend tooling
(Vite/npm). There is no separate Node service at this stage.

## Isolation guarantees

- Host PHP 8.2 (`C:\xampp\php`) is never touched by this setup.
- XAMPP Apache (port 80) and XAMPP MySQL (port 3306) are left running
  untouched. Docker services use different host ports (see below).
- All services run on a dedicated `softphoria_net` bridge network and a
  dedicated `softphoria_mariadb_data` named volume — nothing is shared with
  other Docker projects on this machine.

## Port mapping

| Service | Container port | Host port | Exposed to Windows host? |
|---|---|---|---|
| nginx | 80 | **8080** | Yes — app is reached at `http://localhost:8080` |
| mariadb | 3306 | **3307** | Yes — for external DB tools only |
| redis | 6379 | — | **No** — internal network only |
| app (php-fpm) | 9000 | — | No — internal only, reached via nginx |

Laravel itself connects to services using their **container-internal**
addresses (`DB_HOST=mariadb`, `DB_PORT=3306`, `REDIS_HOST=redis`,
`REDIS_PORT=6379`) via Docker's internal DNS on `softphoria_net`. The
`3307` host mapping is only relevant if you want to connect a database GUI
tool from Windows directly to `localhost:3307`.

## Volumes

| Volume | Mount | Purpose |
|---|---|---|
| `./` (bind mount) | `/var/www/html` (`app` + `nginx`) | Live project code |
| `softphoria_mariadb_data` (named) | `/var/lib/mysql` (`mariadb`) | Persistent database storage |
| `./docker/nginx/default.conf` (bind, read-only) | `/etc/nginx/conf.d/default.conf` (`nginx`) | Nginx site configuration |

## Usage

Copy the environment file and adjust secrets as needed:

```
cp .env.example .env
```

Build the images:

```
docker compose build
```

Start the stack:

```
docker compose up -d
```

This starts every service in `compose.yaml`, including `queue` — there is
no separate step to start the queue worker. If `queue` is ever missing from
`docker compose ps`, dispatched jobs (e.g. `GenerateImageVariantsJob`) will
sit in Redis and never run; `docker compose up -d queue` starts just that
one service without restarting the rest of the stack.

Check status:

```
docker compose ps
```

Stop the stack:

```
docker compose down
```

Stopping this stack does **not** affect XAMPP. XAMPP's Apache/MySQL
services must be managed independently through the XAMPP control panel.

## Running Artisan / Composer / Node inside the container

The Laravel application now exists in this repository (CORE-001). All
PHP/Composer/Artisan/Node work for this project runs inside the `app`
container — never against host PHP 8.2:

```
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec app npm <command>
docker compose exec app php artisan test
```

Use `docker compose run --rm --no-deps app <command>` instead of `exec` for
one-off commands when the stack isn't already running (e.g. before the
first `docker compose up`).

## Storage/bootstrap-cache permissions (entrypoint)

`docker/php/entrypoint.sh` runs on every `app` container start and fixes
ownership of `storage/` and `bootstrap/cache/` to `www-data`. This is
required because the project directory is bind-mounted from the Windows
host, so file ownership does not reliably match the PHP-FPM worker user —
without it, Laravel can't write compiled views, logs, or cache files and
every HTTP request 500s. This does not affect `nginx`, `mariadb`, `redis`,
host port mappings, or anything outside the `app` image.

## Notes

- Composer dependencies (Laravel 13, Livewire, Filament) are installed;
  `node_modules` is not installed yet — run `npm install` inside the `app`
  container before frontend work begins.
- Redis has no host port mapping by design; it is reachable only from
  other containers on `softphoria_net`.
- Vite's dev server currently runs inside the `app` container without a
  published host port. HMR (hot module reload) configuration for the
  browser to reach Vite will be added when frontend development begins.
