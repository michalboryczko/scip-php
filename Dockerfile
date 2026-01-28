FROM golang:1.24-alpine3.21 AS scip-builder
RUN apk add --no-cache git
RUN git clone https://github.com/sourcegraph/scip.git --depth=1 /scip \
    && cd /scip && go build -o /usr/local/bin/scip ./cmd/scip

FROM php:8.4-cli-alpine3.21
RUN echo 'memory_limit=2G' >> /usr/local/etc/php/conf.d/docker-php-memory-limit.ini
RUN apk add --no-cache git libxml2-dev \
    && docker-php-ext-install ctype dom simplexml xml
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=scip-builder /usr/local/bin/scip /usr/local/bin/scip
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-progress --no-interaction
COPY . .
ENTRYPOINT ["php"]
