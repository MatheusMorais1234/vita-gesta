<?php
require __DIR__ . '/db.php';

$g = require_gestante();

// GET: tudo o que a gestante vê na área dela
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT p.id, p.nome, p.email, p.altura, p.peso_pre, p.peso_atual, p.semana, n.nome AS nutri_nome, n.usuario AS nutri_usuario
        FROM pacientes p LEFT JOIN nutricionistas n ON n.id = p.nutricionista_id WHERE p.id = ?');
    $stmt->execute([$g['id']]);
    $p = $stmt->fetch();
    if (!$p) json_out(['erro' => 'Conta não encontrada'], 404);
    json_out([
        'id' => (int)$p['id'],
        'nome' => $p['nome'],
        'email' => $p['email'],
        'altura' => (float)$p['altura'],
        'pre' => (float)$p['peso_pre'],
        'atual' => (float)$p['peso_atual'],
        'semana' => (int)$p['semana'],
        'nutricionista' => $p['nutri_nome'] ? ['nome' => $p['nutri_nome'], 'usuario' => $p['nutri_usuario']] : null,
        'pesagens' => pesagens($g['id']),
        'plano' => plano($g['id']),
    ]);
}

// POST: registra uma nova pesagem e atualiza o peso e a semana atuais
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $semana = (int)($b['semana'] ?? 0);
    $peso = round((float)($b['peso'] ?? 0), 1);
    if ($semana < 1 || $semana > 42 || $peso < 35 || $peso > 250) json_out(['erro' => 'Informe a semana (1 a 42) e o peso em kg (35 a 250)'], 422);

    db()->prepare('INSERT INTO pesagens (paciente_id, semana, peso) VALUES (?, ?, ?)')->execute([$g['id'], $semana, $peso]);
    // O peso atual é sempre o da semana mais recente registrada
    db()->prepare('UPDATE pacientes p JOIN (SELECT semana, peso FROM pesagens WHERE paciente_id = ? ORDER BY semana DESC, id DESC LIMIT 1) u
        SET p.semana = u.semana, p.peso_atual = u.peso WHERE p.id = ?')->execute([$g['id'], $g['id']]);
    json_out(['ok' => true], 201);
}

json_out(['erro' => 'Método não permitido'], 405);
