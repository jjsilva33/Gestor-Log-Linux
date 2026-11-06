FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-client \
        cron \
        rsync \
    && docker-php-ext-install pdo_mysql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

# docker/, DOCKER.md e afins não precisam ficar dentro do docroot servido
RUN rm -rf /var/www/html/docker /var/www/html/DOCKER.md /var/www/html/.dockerignore

RUN mkdir -p /var/backups/gestorlog/diario \
    && chown -R www-data:www-data /var/backups/gestorlog \
    && chown -R www-data:www-data /var/www/html

# cron agendado (equivalente ao `crontab -e` do guia de instalação bare-metal)
# formato /etc/cron.d (com coluna de usuário), lido automaticamente pelo cron
COPY docker/app/crontab /etc/cron.d/gestorlog
RUN chmod 0644 /etc/cron.d/gestorlog

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
