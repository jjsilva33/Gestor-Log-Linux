# Deploy com Docker (VPS Contabo ou similar)

Sobe 3 containers: `app` (PHP + Apache, o painel), `db` (MariaDB) e `rsyslog`
(recebe o log do Mikrotik na porta 514 e grava direto no banco).

## 1. Pré-requisitos na VPS

```
curl -fsSL https://get.docker.com | sh
apt-get install -y docker-compose-plugin
```

## 2. Clonar e configurar

```
git clone https://github.com/jjsilva33/Gestor-Log-Linux.git
cd Gestor-Log-Linux
cp .env.example .env
nano .env   # preencha DB_ROOT_PASSWORD, DB_PASSWORD e NAT_RECEIVER_TOKEN
```

Gere senhas/token fortes com `openssl rand -hex 32`.

## 3. Subir os containers

```
docker compose up -d --build
docker compose logs -f
```

Na primeira subida o MariaDB roda os scripts em `docker/mariadb/init/` e os
`.sql` da raiz do projeto automaticamente (schema do rsyslog, tabela de
usuários, tabela de NAT). Isso só acontece quando o volume `db_data` está
vazio — se precisar recriar o banco do zero: `docker compose down -v`.

## 4. Login padrão

Usuário `admin`, senha a mesma dos dados em `usuarios.sql` (cadastrado como
hash SHA1) — troque depois de logar pela primeira vez, em
`Configurações → Manutenção do log`.

## 5. Abrir as portas no firewall da VPS

```
ufw allow 80/tcp
ufw allow 514/udp
ufw allow 514/tcp
```

**Importante — porta 514 exposta na internet:** syslog não tem autenticação.
Qualquer um que descubra o IP da VPS pode mandar lixo pra sua tabela
`SystemEvents`. Restrinja a porta 514 só aos IPs públicos dos seus Mikrotik
(CONCENTRADOR_IMF e BORDA_IMF_TELECOM_2116), em vez de liberar geral:

```
ufw allow from <IP_PUBLICO_DO_MIKROTIK_1> to any port 514
ufw allow from <IP_PUBLICO_DO_MIKROTIK_2> to any port 514
ufw deny 514
```

## 6. HTTPS (recomendado)

O container `app` só serve HTTP na porta 8080 (mapeada do host). Coloque um
proxy reverso com TLS na frente — forma mais simples, usando
[Caddy](https://caddyserver.com/):

```
apt-get install -y caddy
```

`/etc/caddy/Caddyfile`:
```
seu-dominio.com.br {
    reverse_proxy localhost:8080
}
```

```
systemctl restart caddy
```

## 7. Apontar os Mikrotik pro novo servidor

Depois que o DNS/IP da VPS estiver no ar, atualize em cada roteador:

- **Syslog (porta 514):** `/system logging action set remote/RemoteLog/remotenatlog remote=<IP_DA_VPS>`
- **Script `natlog-export` (NAT da borda):** troque `$url` pra
  `https://seu-dominio.com.br/site/gestorserver/nat/receiver.php` (ou
  `http://<IP_DA_VPS>:8080/...` se ainda não tiver TLS configurado) e
  `$token` pelo mesmo valor de `NAT_RECEIVER_TOKEN` do `.env`.

## 8. Backup

O container `app` roda o backup automático a cada 2h via cron (igual ao
guia bare-metal), gravando em um volume Docker nomeado `backups`. Pra ver
onde esse volume fica no disco da VPS:

```
docker volume inspect gestor-log-linux_backups
```

## Diferenças em relação à instalação tradicional (README.md)

- Não precisa instalar PHP/Apache/MariaDB/rsyslog direto na VPS — tudo roda
  em container.
- `site/Connections/site.php` e `site/gestorserver/nat/token.php` são
  gerados automaticamente pelo `entrypoint.sh` do container `app` a partir
  do `.env`, não precisam ser criados manualmente.
- O schema do `rsyslog-mysql` (tabelas `SystemEvents` e
  `SystemEventsProperties`) é criado por `docker/mariadb/init/01-rsyslog-schema.sql`
  em vez de vir do pacote Debian `rsyslog-mysql` com dbconfig-common.
