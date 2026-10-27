<?php
require __DIR__ . '/db.php';

// Volta o banco ao estado da demonstração (clientes de exemplo, conta da Fernanda, pesagens e consultas).
// Apaga tudo o que foi criado pelo site, inclusive pelo público no dia. Protegido por reset_token do config.php.
$token = config()['reset_token'] ?? '';
if (!$token || !hash_equals($token, (string)($_GET['t'] ?? ''))) json_out(['erro' => 'Não encontrado'], 404);

$pdo = db();

// origem: 'demo' = dados desta lista, 'site' = gestantes que vieram pela calculadora (painel ao vivo conta só essas)
if (!$pdo->query("SHOW COLUMNS FROM pacientes LIKE 'origem'")->fetchAll()) {
    $pdo->exec("ALTER TABLE pacientes ADD COLUMN origem ENUM('site', 'demo') NOT NULL DEFAULT 'site' AFTER nutricionista_id");
}

// A senha da conta de demonstração da Fernanda é preservada entre restaurações
$st = $pdo->prepare('SELECT senha_hash FROM pacientes WHERE email = ?');
$st->execute(['fernanda@exemplo.com']);
$senhaFernanda = $st->fetchColumn() ?: null;

$pdo->exec('DELETE FROM pacientes'); // planos, pesagens e consultas caem junto (ON DELETE CASCADE)
foreach (['pacientes', 'pesagens', 'consultas'] as $t) $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = 1");

$nutri = $pdo->query('SELECT usuario, id FROM nutricionistas')->fetchAll(PDO::FETCH_KEY_PAIR);

// [nome, altura, peso antes, peso atual, semana, nutricionista (ou null = nova pelo site), dias atrás]
$exemplos = [
    ['Fernanda Lima', 165, 58, 64, 24, 'maryanna', 6],
    ['Patrícia M.', 158, 74, 84, 26, 'maryanna', 4],
    ['Camila S.', 160, 50, 51, 20, 'maryanna', 2],
    ['Juliana R.', 168, 62, 70, 28, 'leticia', 7],
    ['Bruna A.', 155, 82, 86, 30, 'leticia', 5],
    ['Renata C.', 162, 55, 55.5, 18, 'leticia', 1],
    ['Aline P.', 170, 68, 72, 22, 'thais', 8],
    ['Gabriela M.', 158, 60, 58.5, 10, 'thais', 3],
    ['Larissa F.', 165, 77, 90, 32, 'thais', 2],
    ['Priscila N.', 163, 48, 58, 30, 'abida', 9],
    ['Daniela V.', 160, 66, 71, 25, 'abida', 4],
    ['Tatiane O.', 157, 90, 103, 27, 'abida', 1],
    ['Júlia T.', 164, 59, 63, 19, null, 1],
    ['Marina B.', 159, 71, 80, 24, null, 1],
    ['Sofia R.', 167, 52, 53, 21, null, 1],
];
// Meio da faixa recomendada (IOM 2009) na semana, para o histórico de exemplo seguir o formato da curva
function meio_da_faixa(float $altura, float $pre, int $semana): float {
    $imc = $pre / (($altura / 100) ** 2);
    [$wMin, $wMax] = $imc < 18.5 ? [0.44, 0.58] : ($imc < 25 ? [0.35, 0.50] : ($imc < 30 ? [0.23, 0.33] : [0.17, 0.27]));
    [$min, $max] = $semana <= 13 ? [0.5 * $semana / 13, 2 * $semana / 13] : [0.5 + ($semana - 13) * $wMin, 2 + ($semana - 13) * $wMax];
    return ($min + $max) / 2;
}

$ins = $pdo->prepare("INSERT INTO pacientes (nome, altura, peso_pre, peso_atual, semana, nutricionista_id, origem, criado_em) VALUES (?, ?, ?, ?, ?, ?, 'demo', NOW() - INTERVAL ? DAY)");
$pes = $pdo->prepare('INSERT INTO pesagens (paciente_id, semana, peso) VALUES (?, ?, ?)');
$ids = [];
foreach ($exemplos as [$nome, $altura, $pre, $atual, $semana, $usuario, $dias]) {
    $ins->execute([$nome, $altura, $pre, $atual, $semana, $usuario ? $nutri[$usuario] : null, $dias]);
    $id = $ids[$nome] = (int)$pdo->lastInsertId();
    // Histórico: algumas medições ao longo da gestação até o peso atual, proporcionais à curva recomendada.
    // Assim quem está adequada hoje aparece adequada nas semanas anteriores, e quem está fora segue fora.
    $fator = ($atual - $pre) / meio_da_faixa($altura, $pre, $semana);
    foreach (array_values(array_unique(array_filter([$semana - 12, $semana - 8, $semana - 4, $semana], fn($w) => $w >= 4))) as $w) {
        $pes->execute([$id, $w, $w === $semana ? $atual : round($pre + $fator * meio_da_faixa($altura, $pre, $w), 1)]);
    }
}

if ($senhaFernanda) {
    $pdo->prepare('UPDATE pacientes SET email = ?, senha_hash = ? WHERE id = ?')->execute(['fernanda@exemplo.com', $senhaFernanda, $ids['Fernanda Lima']]);
}

$pdo->prepare('INSERT INTO planos (paciente_id, refeicoes) VALUES (?, ?)')->execute([$ids['Fernanda Lima'], json_encode([
    'cafe' => '1 fatia de pão integral com ovo mexido + 1 fruta (mamão ou banana) + café com leite',
    'lanche1' => '1 iogurte natural com aveia',
    'almoco' => 'Arroz, feijão, salada de folhas verde-escuras, legumes cozidos e 1 porção de carne magra ou frango',
    'lanche2' => '1 fruta + 1 punhado de castanhas',
    'jantar' => 'Omelete com legumes ou sopa de legumes com frango desfiado',
    'ceia' => '1 copo de leite ou chá de camomila',
], JSON_UNESCAPED_UNICODE)]);

$con = $pdo->prepare("INSERT INTO consultas (paciente_id, nutricionista_id, status, data_hora) VALUES (?, ?, ?, ?)");
$em = fn($dias) => date('Y-m-d 14:00:00', strtotime("+$dias day"));
foreach ([
    ['Fernanda Lima', 'maryanna', 'confirmada', $em(5)], ['Camila S.', 'maryanna', 'pedida', null],
    ['Renata C.', 'leticia', 'confirmada', $em(2)], ['Júlia T.', 'leticia', 'pedida', null],
    ['Larissa F.', 'thais', 'confirmada', $em(3)], ['Marina B.', 'thais', 'pedida', null],
    ['Tatiane O.', 'abida', 'confirmada', $em(1)], ['Sofia R.', 'abida', 'pedida', null],
] as [$nome, $usuario, $status, $data]) {
    $con->execute([$ids[$nome], $nutri[$usuario], $status, $data]);
}

json_out([
    'ok' => true,
    'pacientes' => (int)$pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn(),
    'pesagens' => (int)$pdo->query('SELECT COUNT(*) FROM pesagens')->fetchColumn(),
    'consultas' => (int)$pdo->query('SELECT COUNT(*) FROM consultas')->fetchColumn(),
    'conta_fernanda' => (bool)$senhaFernanda,
]);
