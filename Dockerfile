# Imagem de aplicação (PHP) do novo site do CEMA.
# Roda Laravel/Filament em container; espelha o ambiente de produção (VPS + Docker).
# Node/Vite ficam fora desta imagem (rodam no host).
# Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24
FROM php:8.3-cli

# Dependências de sistema e extensões PHP exigidas por Laravel 13 e Filament 5.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        jpegoptim \
        optipng \
        pngquant \
        gifsicle \
        webp \
    && docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        bcmath \
        exif \
        intl \
    && rm -rf /var/lib/apt/lists/*

# Limite de memória do PHP elevado para o processamento de imagens (Media Library/GD):
# o padrão 128M estoura ao gerar conversões/responsive de imagens grandes — tanto nos
# testes de cap quanto na importação em lote dos 45 posts (cema:importar-blog).
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

# OPcache — CRÍTICO para a performance. O app roda via `php artisan serve` (SAPI cli);
# sem `opcache.enable_cli=1` o PHP recompila TODOS os arquivos a cada request (boot ~8s
# no dev, agravado pelo bind mount do Windows). Com o cli ligado, o processo persistente
# do servidor mantém o bytecode em memória → só o 1º request paga o boot.
# Dev: validate_timestamps=1 + revalidate_freq=0 → edições são vistas na hora.
# Produção: usar validate_timestamps=0 (sem stat por arquivo) e limpar o cache no deploy.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Composer (binário copiado da imagem oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000

# Servidor de desenvolvimento do Laravel acessível fora do container.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
