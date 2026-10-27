<?php
require __DIR__ . '/db.php';

$acao = $_GET['acao'] ?? '';

// POST ?acao=assumir: a nutricionista assume uma gestante que chegou pelo site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'assumir') {
    $nutri = require_painel();
    $id = (int)(body()['id'] ?? 0);
    $stmt = db()->prepare('UPDATE pacientes SET nutricionista_id = ? WHERE id = ? AND nutricionista_id IS NULL');
    $stmt->execute([$nutri['id'], $id]);
    $stmt->rowCount() ? json_out(['ok' => true]) : json_out(['erro' => 'Esta paciente já tem nutricionista'], 409);
}

// POST: gestante envia os dados da calculadora (público)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $nome = mb_substr(trim((string)($b['nome'] ?? '')), 0, 80) ?: 'Gestante';
    $altura = (float)($b['altura'] ?? 0);
    $pre = (float)($b['pre'] ?? 0);
    $atual = (float)($b['atual'] ?? 0);
    $semana = (int)($b['semana'] ?? 0);
    if (!valida_medidas($altura, $pre, $atual, $semana)) json_out(['erro' => 'Dados inválidos'], 422);

    $token = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO pacientes (nome, altura, peso_pre, peso_atual, semana, conta_token) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$nome, $altura, $pre, $atual, $semana, $token]);
    $id = (int)db()->lastInsertId();
    db()->prepare('INSERT INTO pesagens (paciente_id, semana, peso) VALUES (?, ?, ?)')->execute([$id, $semana, $atual]);
    json_out(['id' => $id, 'token' => $token], 201);
}

// GET ?id=: ficha de uma paciente com o histórico de pesagens
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $nutri = require_painel();
    $id = (int)$_GET['id'];
    if (!paciente_visivel($id, $nutri['id'])) json_out(['erro' => 'Paciente não encontrada'], 404);
    json_out(['pesagens' => pesagens($id), 'plano' => plano($id)]);
}

// GET: pacientes que a nutricionista logada pode ver
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $nutri = require_painel();
    $stmt = db()->prepare('SELECT p.id, p.nome, p.altura, p.peso_pre, p.peso_atual, p.semana, p.nutricionista_id, p.email IS NOT NULL AS conta,
            EXISTS (SELECT 1 FROM planos pl WHERE pl.paciente_id = p.id) AS tem_plano
        FROM pacientes p WHERE ' . PACIENTE_VISIVEL . ' ORDER BY p.criado_em DESC, p.id DESC LIMIT 100');
    $stmt->execute(['nutri' => $nutri['id'], 'nutri2' => $nutri['id']]);
    json_out(array_map(fn($r) => [
        'id' => (int)$r['id'],
        'nome' => $r['nome'],
        'altura' => (float)$r['altura'],
        'pre' => (float)$r['peso_pre'],
        'atual' => (float)$r['peso_atual'],
        'semana' => (int)$r['semana'],
        'minha' => (int)$r['nutricionista_id'] === $nutri['id'],
        'nova' => $r['nutricionista_id'] === null,
        'conta' => (bool)$r['conta'],
        'tem_plano' => (bool)$r['tem_plano'],
    ], $stmt->fetchAll()));
}

json_out(['erro' => 'Método não permitido'], 405);
