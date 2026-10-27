<?php
require __DIR__ . '/db.php';

// POST: entra como nutricionista (usuario) ou gestante (email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $tipo = ($b['tipo'] ?? '') === 'gestante' ? 'gestante' : 'nutri';
    $login = mb_strtolower(trim((string)($b['usuario'] ?? '')));
    $senha = (string)($b['senha'] ?? '');

    $stmt = $tipo === 'nutri'
        ? db()->prepare('SELECT id, nome, senha_hash FROM nutricionistas WHERE usuario = ?')
        : db()->prepare('SELECT id, nome, senha_hash FROM pacientes WHERE email = ?');
    $stmt->execute([$login]);
    $u = $stmt->fetch();
    if (!$u || !$u['senha_hash'] || !password_verify($senha, $u['senha_hash'])) {
        usleep(500000); // atrasa tentativas seguidas de adivinhar a senha
        json_out(['erro' => 'Usuário ou senha incorretos'], 401);
    }
    json_out(entrar($tipo, (int)$u['id'], $u['nome']));
}

// GET: quem está logado
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $u = usuario_logado();
    $u ? json_out($u) : json_out(['erro' => 'Não autorizado'], 401);
}

// DELETE: sai
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    sessao();
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
}

json_out(['erro' => 'Método não permitido'], 405);
