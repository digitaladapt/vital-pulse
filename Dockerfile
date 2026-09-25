# ── Stage 1: Composer ──────────────────────────────────────────────
# One pinned base image for both stages (GUIDING-LIGHT §6.4): a floating tag
# like `1-php8.4` silently changes PHP minor version under the build, which is
# how the version drift in §1.1 stayed invisible for so long.
FROM dunglas/frankenphp:1-php8.5-trixie AS composer

# System deps for composer install
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only manifests first for better layer caching
COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-dev --no-interaction --no-scripts

# Build arg for version (CI should pass --build-arg APP_VERSION=v1.3.0)
ARG APP_VERSION=dev

# Copy the rest of the application.
# `.dockerignore` narrows this context — in particular it excludes .env, which
# must never reach a layer (§6.1). Deleting it in a later layer would not
# remove it from the image, so it is kept out of the context entirely.
COPY . .

# Write the version file for runtime version detection. This file is the source
# of truth for /api/about — the app no longer shells out to `git describe` in a
# production request path (§8.13).
RUN echo "${APP_VERSION}" > VERSION

# Run composer auto-scripts (cache:clear, assets:install)
ENV APP_ENV=prod
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd

# ── Stage 2: Runtime ───────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.5-trixie AS app

# Install only runtime system deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        sqlite3 curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-vital-pulse.ini

WORKDIR /app

# Copy the built application from the composer stage
# (includes the VERSION file generated in the builder)
COPY --from=composer /app /app

# Copy Docker support files
COPY docker/Caddyfile /app/docker/Caddyfile
COPY docker/entrypoint.sh /app/docker/entrypoint.sh
RUN chmod +x /app/docker/entrypoint.sh

# Create var directory for SQLite DB, logs, cache
RUN mkdir -p /app/var/data && chown -R nobody:nogroup /app/var

# Environment defaults
ENV APP_ENV=prod \
    FRANKENPHP_WORKER=1 \
    FRANKENPHP_RESET_KERNEL=1 \
    DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/health_tracker.db"

# Volume for SQLite database, logs, and cache
VOLUME /app/var

# Run as non-root user for security
USER nobody:nogroup

EXPOSE 80

# Liveness probe (GUIDING-LIGHT §8.4): process is up, no dependencies touched.
# This deliberately does NOT hit /ready — a locked or missing SQLite file is
# not something a container restart fixes, and probing the database here would
# put the container in a crash loop over it.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -sf http://localhost:80/health || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
