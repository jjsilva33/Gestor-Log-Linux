# Deploy com Docker via Portainer + Traefik

Sobe 3 containers: `app` (PHP + Apache, o painel), `db` (MariaDB) e `rsyslog`
(recebe o log do Mikrotik na porta 514 e grava direto no banco). O `app` é
roteado pelo Traefik (HTTPS automático) em vez de expor a porta direto.

Pré-requisito: Portainer e Traefik já rodando na VPS, com o Traefik ligado
a uma network Docker externa (geralmente chamada `traefik` ou `proxy`).

## 1. Descobrir o nome da network e do certresolver do seu Traefik

```
docker network ls | grep -i traefik
```

E veja, na stack do próprio Traefik (Portainer → Stacks → a stack do
Traefik → Editor), o nome configurado em `--certificatesresolvers.<nome>...`
— é esse `<nome>` que vai em `TRAEFIK_CERTRESOLVER`.

## 2. Criar a stack no Portainer

**Stacks → Add stack**

- **Name:** `gestorlog`
- **Build method:** "Repository", apontando para
  `https://github.com/jjsilva33/Gestor-Log-Linux.git`, branch `master`,
  compose path `docker-compose.yml` (ou "Web editor" colando o conteúdo do
  arquivo, se preferir não linkar o repositório).
- **Environment variables:** preencha (equivalente ao `.env`):

  | Variável | Valor |
  |---|---|
  | `DB_ROOT_PASSWORD` | senha forte (`openssl rand -hex 32`) |
  | `DB_NAME` | `Syslog` |
  | `DB_USER` | `gestorlog` |
  | `DB_PASSWORD` | senha forte |
  | `NAT_RECEIVER_TOKEN` | token forte (`openssl rand -hex 32`) |
  | `DOMAIN` | `log.seudominio.com.br` |
  | `TRAEFIK_NETWORK` | nome achado no passo 1 |
  | `TRAEFIK_CERTRESOLVER` | nome achado no passo 1 |

- Clique em **Deploy the stack**.

Na primeira subida o MariaDB roda os scripts em `docker/mariadb/init/` e os
`.sql` da raiz do projeto automaticamente (schema do rsyslog, tabela de
usuários, tabela de NAT) — só acontece com o volume `db_data` vazio.

## 3. Login padrão

Usuário `admin`, senha a mesma dos dados em `usuarios.sql` (cadastrado como
hash SHA1) — troque depois de logar pela primeira vez, em
`Configurações → Manutenção do log`.

## 4. Firewall da VPS

As portas 80/443 já devem estar liberadas pelo Traefik. Falta a porta do
syslog, que **não passa pelo Traefik** (não é tráfego HTTP):

```
ufw allow 514/udp
ufw allow 514/tcp
```

**Importante:** syslog não tem autenticação. Restrinja a porta 514 só aos
IPs públicos dos seus Mikrotik, em vez de liberar geral:

```
ufw allow from <IP_PUBLICO_DO_MIKROTIK_1> to any port 514
ufw allow from <IP_PUBLICO_DO_MIKROTIK_2> to any port 514
ufw deny 514
```

A porta `8080` (mapeada do container `app`) é só uma via de acesso direto
para debug — com o Traefik cuidando do domínio público, não precisa (e é
melhor não) abrir ela no firewall:

```
ufw deny 8080
```

## 5. Apontar os Mikrotik pro novo servidor

Depois que o domínio estiver resolvendo e o certificado emitido, atualize
em cada roteador:

- **Syslog (porta 514):** `/system logging action set remote/RemoteLog/remotenatlog remote=<IP_DA_VPS>`
- **Script `natlog-export` (NAT da borda):** troque `$url` pra
  `https://log.seudominio.com.br/site/gestorserver/nat/receiver.php` e
  `$token` pelo mesmo valor de `NAT_RECEIVER_TOKEN`.

## 6. Backup

O container `app` roda o backup diário + retenção via cron (igual ao guia
bare-metal), gravando em um volume Docker nomeado `backups`. Pra ver onde
esse volume fica no disco da VPS:

```
docker volume inspect gestor-log-linux_backups
```

Pra mandar os backups também pro Google Drive, sem precisar de terminal,
use **Configurações → Backup na nuvem** dentro do próprio painel (ver
README.md).

## Diferenças em relação à instalação tradicional (README.md)

- Não precisa instalar PHP/Apache/MariaDB/rsyslog direto na VPS — tudo roda
  em container, gerenciado pelo Portainer.
- `site/Connections/site.php` e `site/gestorserver/nat/token.php` são
  gerados automaticamente pelo `entrypoint.sh` do container `app` a partir
  das variáveis de ambiente da stack, não precisam ser criados manualmente.
- HTTPS é feito pelo Traefik (labels no serviço `app`), não por um proxy
  reverso separado.
- O schema do `rsyslog-mysql` (tabelas `SystemEvents` e
  `SystemEventsProperties`) é criado por
  `docker/mariadb/init/01-rsyslog-schema.sql` em vez de vir do pacote
  Debian `rsyslog-mysql` com dbconfig-common.

## Alternativa sem Portainer (CLI pura)

Se preferir usar `docker compose` direto por SSH em vez do Portainer, o
mesmo `docker-compose.yml` funciona — só copie `.env.example` para `.env`,
preencha as mesmas variáveis (incluindo `TRAEFIK_NETWORK`/`TRAEFIK_CERTRESOLVER`
se tiver um Traefik rodando, ou apague as labels `traefik.*` e a network
`traefik` do arquivo se não usar Traefik) e rode:

```
docker compose up -d --build
docker compose logs -f
```
