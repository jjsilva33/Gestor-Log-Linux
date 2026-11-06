FROM debian:13-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        rsyslog \
        rsyslog-mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY rsyslog.conf.template /etc/rsyslog.conf.template
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 514/udp
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
