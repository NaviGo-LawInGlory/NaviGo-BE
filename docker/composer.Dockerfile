FROM composer:latest as composer-deps

WORKDIR /app

# Copy only what's needed for composer
COPY composer.json composer.lock ./

# Use GitHub OAuth to avoid API rate limits
ARG GITHUB_TOKEN=""
RUN if [ -n "$GITHUB_TOKEN" ]; then \
    composer config -g github-oauth.github.com $GITHUB_TOKEN; \
    fi

# Install dependencies
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader --no-progress

# Copy the rest of the application code
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --no-scripts --no-dev --optimize
