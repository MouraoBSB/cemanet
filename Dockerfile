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

# Composer (binário copiado da imagem oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000

# Servidor de desenvolvimento do Laravel acessível fora do container.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
