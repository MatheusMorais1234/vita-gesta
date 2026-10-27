<?php
require __DIR__ . '/db.php';

$nutri = require_painel();

const REFEICOES = ['cafe', 'lanche1', 'almoco', 'lanche2', 'jantar', 'ceia'];

// GET: plano alimentar de uma paciente
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['paciente_id'] ?? 0);
    if (!paciente_visivel($id, $nutri['id'])) json_out(['erro' => 'Paciente não encontrada'], 404);
    $p = plano($id);
    json_out($p ? $p['refeicoes'] : new stdClass());
}

// POST: salva (ou atualiza) o plano alimentar. Quem envia o plano passa a acompanhar a paciente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $id = (int)($b['paciente_id'] ?? 0);
    $plano = [];
    foreach (REFEICOES as $k) {
        $plano[$k] = mb_substr((string)($b['plano'][$k] ?? ''), 0, 1000);
    }

    if (!paciente_visivel($id, $nutri['id'])) json_out(['erro' => 'Paciente não encontrada'], 404);

    db()->prepare('INSERT INTO planos (paciente_id, refeicoes) VALUES (?, ?) ON DUPLICATE KEY UPDATE refeicoes = VALUES(refeicoes)')
        ->execute([$id, json_encode($plano, JSON_UNESCAPED_UNICODE)]);
    db()->prepare('UPDATE pacientes SET nutricionista_id = ? WHERE id = ? AND nutricionista_id IS NULL')->execute([$nutri['id'], $id]);
    json_out(['ok' => true]);
}

json_out(['erro' => 'Método não permitido'], 405);
