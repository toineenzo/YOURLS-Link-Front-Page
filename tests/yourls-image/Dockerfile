FROM php:8.4-apache

# YOURLS needs mysqli + mod_rewrite. Drop curl + unzip in just for the
# install step.
RUN apt-get update -qq \
    && apt-get install -qq -y --no-install-recommends curl unzip ca-certificates \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Allow .htaccess overrides so YOURLS can rewrite short URLs.
RUN printf '<Directory /var/www/html/>\n\
    AllowOverride All\n\
</Directory>\n' > /etc/apache2/conf-available/yourls.conf \
    && a2enconf yourls

WORKDIR /var/www/html

# Pin to YOURLS 1.10.2 — known-stable on PHP 8.4.
ENV YOURLS_VERSION=1.10.2
RUN curl -fsSL "https://github.com/YOURLS/YOURLS/archive/refs/tags/${YOURLS_VERSION}.tar.gz" \
        -o /tmp/yourls.tgz \
    && tar -xzf /tmp/yourls.tgz -C /tmp \
    && cp -a "/tmp/YOURLS-${YOURLS_VERSION}/." /var/www/html/ \
    && rm -rf /tmp/yourls.tgz "/tmp/YOURLS-${YOURLS_VERSION}" \
    && chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
