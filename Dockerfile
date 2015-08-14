FROM keboola/base-php
MAINTAINER Jakub Matejka <jakub@keboola.com>

WORKDIR /home

# Initialize
RUN git clone https://github.com/keboola/wr-tableau-server.git ./
RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data