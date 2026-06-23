FROM php:8.4-fpm-alpine

# Use the prebuilt extension installer instead of pecl + source compilation:
# it fetches prebuilt extension binaries over curl (robust against the flaky
# PEAR downloader) and avoids installing the gcc/clang/llvm toolchain, turning
# an ~18-minute source build into a couple of minutes.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql redis bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
