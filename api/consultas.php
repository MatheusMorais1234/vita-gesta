<?php
require __DIR__ . '/db.php';

$u = usuario_logado();
if (!$u) json_out(['erro' => 'Não autorizado'], 401);

function lista_consultas(string $where, array $params): array {
    $stmt = db()->prepare("SELECT c.id, c.status, c.data_hora, c.criado_em, c.paciente_id, p.nome AS paciente, p.semana, n.nome AS nutricionista, n.usuario
        FROM consultas c JOIN pacientes p ON p.id = c.paciente_id JOIN nutricionistas n ON n.id = c.nutricionista_id
        WHERE $where ORDER BY c.status = 'pedida' DESC, c.data_hora IS NULL, c.data_hora, c.criado_em DESC");
    $stmt->execute($params);
    return array_map(fn($r) => [
        'id' => (int)$r['id'],
        'status' => $r['status'],
        'data_hora' => $r['data_hora'],
        'criado_em' => $r['criado_em'],
        'paciente_id' => (int)$r['paciente_id'],
        'paciente' => $r['paciente'],
        'semana' => (int)$r['semana'],
        'nutricionista' => $r['nutricionista'],
        'usuario' => $r['usuario'],
    ], $stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_out($u['tipo'] === 'nutri'
        ? lista_consultas('c.nutricionista_id = ?', [$u['id']])
        : lista_consultas('c.paciente_id = ?', [$u['id']]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$b = body();

// Gestante pede consulta com uma nutricionista
if ($u['tipo'] === 'gestante') {
    $stmt = db()->prepare('SELECT id, nome FROM nutricionistas WHERE usuario = ?');
    $stmt->execute([(string)($b['nutricionista'] ?? '')]);
    $n = $stmt->fetch();
    if (!$n) json_out(['erro' => 'Nutricionista não encontrada'], 404);

    $stmt = db()->prepare("SELECT 1 FROM consultas WHERE paciente_id = ? AND nutricionista_id = ? AND (status = 'pedida' OR (status = 'confirmada' AND data_hora >= NOW()))");
    $stmt->execute([$u['id'], $n['id']]);
    if ($stmt->fetch()) json_out(['erro' => 'Você já tem um pedido em aberto com ' . $n['nome']], 409);

    db()->prepare("INSERT INTO consultas (paciente_id, nutricionista_id, status) VALUES (?, ?, 'pedida')")->execute([$u['id'], $n['id']]);
    json_out(['ok' => true, 'nutricionista' => $n['nome']], 201);
}

// Nutricionista confirma (com data e hora) ou recusa um pedido
$id = (int)($b['id'] ?? 0);
$acao = $b['acao'] ?? '';
$stmt = db()->prepare("SELECT paciente_id FROM consultas WHERE id = ? AND nutricionista_id = ? AND status = 'pedida'");
$stmt->execute([$id, $u['id']]);
$c = $stmt->fetch();
if (!$c) json_out(['erro' => 'Pedido não encontrado'], 404);

if ($acao === 'confirmar') {
    $data = DateTime::createFromFormat('Y-m-d\TH:i', (string)($b['data_hora'] ?? ''));
    if (!$data) json_out(['erro' => 'Escolha a data e o horário da consulta'], 422);
    db()->prepare("UPDATE consultas SET status = 'confirmada', data_hora = ? WHERE id = ?")->execute([$data->format('Y-m-d H:i:00'), $id]);
    // Ao confirmar, a nutricionista passa a acompanhar a gestante, se ela ainda não tinha ninguém
    db()->prepare('UPDATE pacientes SET nutricionista_id = ? WHERE id = ? AND nutricionista_id IS NULL')->execute([$u['id'], $c['paciente_id']]);
    json_out(['ok' => true]);
}
if ($acao === 'recusar') {
    db()->prepare("UPDATE consultas SET status = 'recusada' WHERE id = ?")->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['erro' => 'Ação inválida'], 422);
