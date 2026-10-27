<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Gestor Log — Entrar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #15181e;
      --bg-elevated: #1b1f27;
      --bg-elevated-2: #20252e;
      --border: #2c323d;
      --text: #e4e7ec;
      --text-muted: #8b93a1;
      --text-faint: #5b6472;
      --accent: #2dd4bf;
      --accent-strong: #14b8a6;
      --accent-soft: rgba(45, 212, 191, 0.14);
      --danger: #f66d6d;
      --radius: 14px;
      --radius-sm: 8px;
    }

    * { box-sizing: border-box; }

    html, body {
      height: 100%;
      margin: 0;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg);
      background-image:
        radial-gradient(circle at 15% 10%, rgba(45, 212, 191, 0.12), transparent 40%),
        radial-gradient(circle at 85% 90%, rgba(77, 163, 255, 0.10), transparent 40%);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    .login-shell {
      width: 100%;
      max-width: 380px;
    }

    .brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 28px;
    }

    .brand .dot {
      width: 12px;
      height: 12px;
      border-radius: 4px;
      background: var(--accent);
      box-shadow: 0 0 14px rgba(45, 212, 191, 0.8);
    }

    .brand span {
      font-weight: 700;
      font-size: 18px;
      letter-spacing: -0.01em;
    }

    .login-card {
      background: linear-gradient(160deg, var(--bg-elevated-2), var(--bg-elevated));
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 32px 30px 28px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
    }

    .login-card h1 {
      font-size: 19px;
      font-weight: 600;
      margin: 0 0 4px;
      text-align: center;
    }

    .login-card p.subtitle {
      text-align: center;
      color: var(--text-muted);
      font-size: 13px;
      margin: 0 0 26px;
    }

    .field { margin-bottom: 16px; }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }

    .field input {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: var(--radius-sm);
      padding: 11px 13px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color .15s ease, box-shadow .15s ease;
    }

    .field input::placeholder { color: var(--text-faint); }

    .field input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft);
    }

    .submit-btn {
      width: 100%;
      background: var(--accent);
      border: 1px solid var(--accent);
      color: #0b1411;
      font-weight: 700;
      font-size: 14px;
      padding: 11px 0;
      border-radius: var(--radius-sm);
      cursor: pointer;
      margin-top: 6px;
      transition: background .15s ease, transform .1s ease;
    }

    .submit-btn:hover { background: var(--accent-strong); transform: translateY(-1px); }
    .submit-btn:active { transform: translateY(0); }

    .notice {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: var(--accent-soft);
      border: 1px solid rgba(45, 212, 191, 0.3);
      color: var(--text);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      font-size: 12.5px;
      margin-bottom: 22px;
      line-height: 1.5;
    }

    .notice svg { flex: none; margin-top: 1px; }

    .footer-note {
      text-align: center;
      color: var(--text-faint);
      font-size: 12px;
      margin-top: 22px;
    }
  </style>
</head>

<body>
  <div class="login-shell">
    <div class="brand">
      <span class="dot"></span>
      <span>Gestor Log</span>
    </div>

    <div class="login-card">
      <h1>Acessar painel</h1>
      <p class="subtitle">Entre com suas credenciais para continuar</p>

      <div class="notice">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>Você precisa estar logado para acessar esta página.</span>
      </div>

      <form action="/site/login/validacao.php" method="post">
        <div class="field">
          <label for="txUsuario">Usuário</label>
          <input type="text" name="usuario" id="txUsuario" maxlength="25" placeholder="Seu usuário" autocomplete="username" required>
        </div>
        <div class="field">
          <label for="txSenha">Senha</label>
          <input type="password" name="senha" id="txSenha" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button class="submit-btn" type="submit" name="submit">Entrar</button>
      </form>
    </div>

    <p class="footer-note">Gestor Log &middot; Bylltec</p>
  </div>
</body>

</html>
