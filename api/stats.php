<?php
require __DIR__ . '/db.php';

// Números anônimos para o painel ao vivo: só gestantes que fizeram o cálculo pelo site (origem = 'site').
// Sem nomes, sem e-mails. A mesma regra do IOM (2009) usada no front.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['erro' => 'Método não permitido'], 405);

const IOM_SEMANAL = ['baixo' => [0.44, 0.58], 'adequado' => [0.35, 0.50], 'sobrepeso' => [0.23, 0.33], 'obesidade' => [0.17, 0.27]];

function avalia(array $p): array {
    $imc = $p['peso_pre'] / (($p['altura'] / 100) ** 2);
    $cat = $imc < 18.5 ? 'baixo' : ($imc < 25 ? 'adequado' : ($imc < 30 ? 'sobrepeso' : 'obesidade'));
    $s = (int)$p['semana'];
    [$wMin, $wMax] = IOM_SEMANAL[$cat];
    [$min, $max] = $s <= 13 ? [0.5 * $s / 13, 2 * $s / 13] : [0.5 + ($s - 13) * $wMin, 2 + ($s - 13) * $wMax];
    $ganho = $p['peso_atual'] - $p['peso_pre'];
    $piso = $s <= 13 ? min($min, -1) : $min; // perda de até 1 kg no 1º trimestre conta como adequada
    return ['imc' => $imc, 'cat' => $cat, 'status' => $ganho < $piso ? 'low' : ($ganho > $max ? 'high' : 'ok')];
}

$rows = db()->query("SELECT p.altura, p.peso_pre, p.peso_atual, p.semana, p.email IS NOT NULL AS conta,
        TIMESTAMPDIFF(SECOND, p.criado_em, NOW()) AS idade
    FROM pacientes p WHERE p.origem = 'site' ORDER BY p.criado_em DESC, p.id DESC")->fetchAll();

$status = ['ok' => 0, 'low' => 0, 'high' => 0];
$tri = [1 => 0, 2 => 0, 3 => 0];
$cats = ['baixo' => 0, 'adequado' => 0, 'sobrepeso' => 0, 'obesidade' => 0];
$somaSemana = 0;
$recentes = [];
foreach ($rows as $i => $r) {
    $a = avalia($r);
    $status[$a['status']]++;
    $cats[$a['cat']]++;
    $t = $r['semana'] <= 13 ? 1 : ($r['semana'] <= 27 ? 2 : 3);
    $tri[$t]++;
    $somaSemana += $r['semana'];
    if ($i < 6) $recentes[] = ['semana' => (int)$r['semana'], 'trimestre' => $t, 'status' => $a['status'], 'idade' => (int)$r['idade']];
}

$consultas = (int)db()->query("SELECT COUNT(*) FROM consultas c JOIN pacientes p ON p.id = c.paciente_id WHERE p.origem = 'site'")->fetchColumn();

json_out([
    'total' => count($rows),
    'contas' => count(array_filter($rows, fn($r) => $r['conta'])),
    'consultas' => $consultas,
    'semana_media' => $rows ? round($somaSemana / count($rows), 1) : null,
    'status' => $status,
    'trimestres' => $tri,
    'categorias' => $cats,
    'recentes' => $recentes,
]);
