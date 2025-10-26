FROM dunglas/frankenphp

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV SERVER_NAME=:80

RUN install-php-extensions \
	intl

COPY . /app
