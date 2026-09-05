# SoftphoriaCMS Production Docker Deployment

This package adds a separate production Docker configuration while leaving the existing development `compose.yaml` unchanged.

## Files

- `docker/php/Dockerfile.prod` — multi-stage production image
- `docker/php/php.prod.ini` — production PHP settings
- `docker/php/entrypoint.prod.sh` — runtime writable-directory setup
- `docker/nginx/prod.conf` — HTTPS nginx configuration
- `docker-compose.prod.yml` — production Compose stack

## Before first deployment

1. Copy the production files into the repository.
2. Create the real production `.env`.
3. Replace `DOMAIN` in `docker/nginx/prod.conf` with the real domain.
4. Obtain the Let's Encrypt certificate on the EC2 host.
5. Verify DNS points to the EC2 public IP.
6. Build the images:
   `docker compose -f docker-compose.prod.yml build`
7. Start infrastructure:
   `docker compose -f docker-compose.prod.yml up -d`
8. Run migrations only after verifying the production `.env`:
   `docker compose -f docker-compose.prod.yml exec app php artisan migrate --force`
9. Create/cache production configuration as appropriate:
   `docker compose -f docker-compose.prod.yml exec app php artisan config:cache`
   `docker compose -f docker-compose.prod.yml exec app php artisan route:cache`
   `docker compose -f docker-compose.prod.yml exec app php artisan view:cache`

## Important

- Do not copy `.env.example` directly to production.
- Do not expose MariaDB or Redis ports.
- Generate a new production `APP_KEY`.
- Keep the existing development `compose.yaml` for local development.
- Test Stripe webhook delivery over HTTPS before launch.
- Verify the 512 MB upload path with a representative large file.
- Back up MariaDB before any migration or production deployment.
