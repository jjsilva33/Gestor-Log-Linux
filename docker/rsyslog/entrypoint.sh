#!/bin/sh
set -e

sed \
  -e "s#__DB_HOST__#${DB_HOST}#" \
  -e "s#__DB_NAME__#${DB_NAME}#" \
  -e "s#__DB_USER__#${DB_USER}#" \
  -e "s#__DB_PASSWORD__#${DB_PASSWORD}#" \
  /etc/rsyslog.conf.template > /etc/rsyslog.conf

exec rsyslogd -n
