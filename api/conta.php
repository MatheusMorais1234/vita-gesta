<?php
require __DIR__ . '/db.php';

// POST: a gestante cria a conta a partir do resultado da calculadora.
// O token devolvido por pacientes.php prova que foi ela quem preencheu aquele cálculo.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);

$b = body();
$id = (int)($b['paciente_id'] ?? 0);
$token = (string)($b['token'] ?? '');
$nome = mb_substr(trim((string)($b['nome'] ?? '')), 0, 80);
$email = mb_strtolower(trim((string)($b['email'] ?? '')));
$senha = (string)($b['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['erro' => 'Informe um e-mail válido'], 422);
if (mb_strlen($senha) < 6) json_out(['erro' => 'A senha precisa ter pelo menos 6 caracteres'], 422);

$stmt = db()->prepare('SELECT id, nome, conta_token FROM pacientes WHERE id = ? AND email IS NULL');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p || !$p['conta_token'] || !hash_equals($p['conta_token'], $token)) json_out(['erro' => 'Faça o cálculo de novo para criar a sua conta'], 403);

$stmt = db()->prepare('SELECT 1 FROM pacientes WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) json_out(['erro' => 'Este e-mail já tem conta. Entre com ele'], 409);

$nome = $nome ?: $p['nome'];
db()->prepare('UPDATE pacientes SET nome = ?, email = ?, senha_hash = ?, conta_token = NULL WHERE id = ?')
    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $id]);
json_out(entrar('gestante', $id, $nome), 201);
