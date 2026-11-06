<?php
// Integração com o Google Drive via OAuth2 + API REST (sem rclone, sem SDK).
// Credenciais ficam em drive_credentials.json (fora do git, veja .gitignore).
// Conectar/desconectar é feito pela interface web em backup_drive.php; os
// scripts de cron só chamam enviar_para_drive() para subir um arquivo.

const DRIVE_ARQUIVO_CREDENCIAIS = __DIR__ . '/drive_credentials.json';
const DRIVE_PASTA_RAIZ = 'GestorLog-Backups';
const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

function drive_carregar_credenciais(): array
{
    if (!file_exists(DRIVE_ARQUIVO_CREDENCIAIS)) {
        return [];
    }
    $conteudo = file_get_contents(DRIVE_ARQUIVO_CREDENCIAIS);
    $dados = json_decode((string) $conteudo, true);
    return is_array($dados) ? $dados : [];
}

function drive_salvar_credenciais(array $novos_dados): void
{
    $dados = array_merge(drive_carregar_credenciais(), $novos_dados);
    file_put_contents(DRIVE_ARQUIVO_CREDENCIAIS, json_encode($dados, JSON_PRETTY_PRINT));
    chmod(DRIVE_ARQUIVO_CREDENCIAIS, 0640);
}

function drive_desconectar(): void
{
    $dados = drive_carregar_credenciais();
    unset($dados['refresh_token'], $dados['pasta_raiz_id'], $dados['pasta_diario_id'], $dados['pasta_retencao_id']);
    file_put_contents(DRIVE_ARQUIVO_CREDENCIAIS, json_encode($dados, JSON_PRETTY_PRINT));
}

function drive_tem_credenciais_app(): bool
{
    $dados = drive_carregar_credenciais();
    return !empty($dados['client_id']) && !empty($dados['client_secret']);
}

function drive_conectado(): bool
{
    $dados = drive_carregar_credenciais();
    return drive_tem_credenciais_app() && !empty($dados['refresh_token']);
}

function drive_redirect_uri(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $esquema = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$esquema://$host/site/gestorserver/log/index.php?pagina=backup_drive_callback";
}

function drive_url_autorizacao(): ?string
{
    $dados = drive_carregar_credenciais();
    if (empty($dados['client_id'])) {
        return null;
    }
    $parametros = [
        'client_id' => $dados['client_id'],
        'redirect_uri' => drive_redirect_uri(),
        'response_type' => 'code',
        'scope' => DRIVE_SCOPE,
        'access_type' => 'offline',
        'prompt' => 'consent',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parametros);
}

function drive_curl_json(string $url, array $campos_post): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($campos_post),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resposta = curl_exec($ch);
    curl_close($ch);
    if ($resposta === false) {
        return null;
    }
    $json = json_decode($resposta, true);
    return is_array($json) ? $json : null;
}

// Troca o "code" do redirect do Google pelo refresh_token (uma única vez, no momento de conectar).
function drive_trocar_code_por_token(string $code): bool
{
    $dados = drive_carregar_credenciais();
    if (empty($dados['client_id']) || empty($dados['client_secret'])) {
        return false;
    }
    $resposta = drive_curl_json('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $dados['client_id'],
        'client_secret' => $dados['client_secret'],
        'redirect_uri' => drive_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
    if (empty($resposta['refresh_token'])) {
        return false;
    }
    drive_salvar_credenciais(['refresh_token' => $resposta['refresh_token']]);
    return true;
}

// Troca o refresh_token (de longa duração) por um access_token válido por ~1h.
function drive_access_token(): ?string
{
    $dados = drive_carregar_credenciais();
    if (empty($dados['client_id']) || empty($dados['client_secret']) || empty($dados['refresh_token'])) {
        return null;
    }
    $resposta = drive_curl_json('https://oauth2.googleapis.com/token', [
        'client_id' => $dados['client_id'],
        'client_secret' => $dados['client_secret'],
        'refresh_token' => $dados['refresh_token'],
        'grant_type' => 'refresh_token',
    ]);
    return $resposta['access_token'] ?? null;
}

function drive_curl_api(string $url, string $access_token, array $opcoes_extra = []): ?array
{
    $headers_extra = $opcoes_extra[CURLOPT_HTTPHEADER] ?? [];
    unset($opcoes_extra[CURLOPT_HTTPHEADER]);

    $ch = curl_init($url);
    curl_setopt_array($ch, $opcoes_extra + [
        CURLOPT_HTTPHEADER => array_merge(["Authorization: Bearer $access_token"], $headers_extra),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resposta = curl_exec($ch);
    curl_close($ch);
    if ($resposta === false) {
        return null;
    }
    $json = json_decode($resposta, true);
    return is_array($json) ? $json : null;
}

// Busca uma pasta pelo nome (dentro do pai informado) e cria se não existir. Resultado fica em cache no credentials.json.
function drive_obter_pasta(string $chave_cache, string $nome, ?string $pasta_pai_id, string $access_token): ?string
{
    $dados = drive_carregar_credenciais();
    if (!empty($dados[$chave_cache])) {
        return $dados[$chave_cache];
    }

    $query = "name = " . "'" . str_replace("'", "\\'", $nome) . "'" . " and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    if ($pasta_pai_id !== null) {
        $query .= " and '$pasta_pai_id' in parents";
    }
    $busca = drive_curl_api('https://www.googleapis.com/drive/v3/files?' . http_build_query(['q' => $query, 'fields' => 'files(id)']), $access_token);
    if (!empty($busca['files'][0]['id'])) {
        $id = $busca['files'][0]['id'];
        drive_salvar_credenciais([$chave_cache => $id]);
        return $id;
    }

    $metadados = ['name' => $nome, 'mimeType' => 'application/vnd.google-apps.folder'];
    if ($pasta_pai_id !== null) {
        $metadados['parents'] = [$pasta_pai_id];
    }
    $criacao = drive_curl_api('https://www.googleapis.com/drive/v3/files', $access_token, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($metadados),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if (empty($criacao['id'])) {
        return null;
    }
    drive_salvar_credenciais([$chave_cache => $criacao['id']]);
    return $criacao['id'];
}

// Ponto de entrada usado pelos scripts de backup/retenção.
function enviar_para_drive(string $arquivo_local, string $subpasta): bool
{
    if (!file_exists($arquivo_local) || !drive_conectado()) {
        return false;
    }

    $access_token = drive_access_token();
    if ($access_token === null) {
        return false;
    }

    $pasta_raiz_id = drive_obter_pasta('pasta_raiz_id', DRIVE_PASTA_RAIZ, null, $access_token);
    if ($pasta_raiz_id === null) {
        return false;
    }
    $pasta_destino_id = drive_obter_pasta("pasta_{$subpasta}_id", $subpasta, $pasta_raiz_id, $access_token);
    if ($pasta_destino_id === null) {
        return false;
    }

    $metadados = json_encode(['name' => basename($arquivo_local), 'parents' => [$pasta_destino_id]]);
    $boundary = 'gestorlog-' . bin2hex(random_bytes(8));
    $corpo = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadados\r\n"
        . "--$boundary\r\nContent-Type: application/gzip\r\n\r\n" . file_get_contents($arquivo_local) . "\r\n"
        . "--$boundary--";

    $resposta = drive_curl_api('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', $access_token, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_HTTPHEADER => ["Content-Type: multipart/related; boundary=$boundary"],
    ]);

    return !empty($resposta['id']);
}
