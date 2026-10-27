<?php
// Conexão e utilitários compartilhados pela API

function config(): array {
    static $config;
    return $config ??= require __DIR__ . '/config.php';
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = config();
        $pdo = new PDO(
            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

// Sessão guardada em cookie httponly. $_SESSION['user'] = ['tipo' => 'nutri'|'gestante', 'id', 'nome']
function sessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('vgsess');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function usuario_logado(): ?array {
    sessao();
    return $_SESSION['user'] ?? null;
}

function entrar(string $tipo, int $id, string $nome): array {
    sessao();
    session_regenerate_id(true);
    return $_SESSION['user'] = ['tipo' => $tipo, 'id' => $id, 'nome' => $nome];
}

function require_tipo(string $tipo): array {
    $u = usuario_logado();
    if (!$u || $u['tipo'] !== $tipo) json_out(['erro' => 'Não autorizado'], 401);
    return $u;
}

// O painel do nutricionista exige login de nutricionista (api/login.php)
function require_painel(): array {
    return require_tipo('nutri');
}

function require_gestante(): array {
    return require_tipo('gestante');
}

// A nutricionista vê as próprias pacientes, as que chegaram pelo site sem nutricionista
// e as que pediram consulta com ela
const PACIENTE_VISIVEL = '(p.nutricionista_id = :nutri OR p.nutricionista_id IS NULL
    OR EXISTS (SELECT 1 FROM consultas c WHERE c.paciente_id = p.id AND c.nutricionista_id = :nutri2))';

function paciente_visivel(int $paciente_id, int $nutri_id): bool {
    $stmt = db()->prepare('SELECT 1 FROM pacientes p WHERE p.id = :id AND ' . PACIENTE_VISIVEL);
    $stmt->execute(['id' => $paciente_id, 'nutri' => $nutri_id, 'nutri2' => $nutri_id]);
    return (bool)$stmt->fetch();
}

function pesagens(int $paciente_id): array {
    $stmt = db()->prepare('SELECT semana, peso FROM pesagens WHERE paciente_id = ? ORDER BY semana, id');
    $stmt->execute([$paciente_id]);
    return array_map(fn($r) => ['semana' => (int)$r['semana'], 'peso' => (float)$r['peso']], $stmt->fetchAll());
}

function plano(int $paciente_id): ?array {
    $stmt = db()->prepare('SELECT refeicoes, atualizado_em FROM planos WHERE paciente_id = ?');
    $stmt->execute([$paciente_id]);
    $row = $stmt->fetch();
    return $row ? ['refeicoes' => json_decode($row['refeicoes'], true), 'atualizado_em' => $row['atualizado_em']] : null;
}

function valida_medidas(float $altura, float $pre, float $atual, int $semana): bool {
    return $altura >= 130 && $altura <= 210 && $pre >= 35 && $pre <= 250 && $atual >= 35 && $atual <= 250 && $semana >= 1 && $semana <= 42;
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_out(['erro' => 'Erro interno'], 500);
});
