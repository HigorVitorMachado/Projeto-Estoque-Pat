<?php
/**
 * Cria (ou atualiza) um usuário administrador (perfil "administrador") no sistema.
 *
 * Uso (no terminal, dentro da pasta estoque_pat_php):
 *   C:\xampp\php\php.exe criar_admin.php "Seu Nome" seu_login sua_senha
 *
 * Se o login já existir, a senha e o perfil dele são atualizados.
 * Rode apenas via linha de comando (bloqueado no navegador).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute este script apenas pelo terminal.');
}

require __DIR__ . '/config.php';

[, $nome, $login, $senha] = $argv + [null, null, null, null];

if (!$nome || !$login || !$senha) {
    fwrite(STDERR, "Uso: php criar_admin.php \"Nome\" login senha\n");
    exit(1);
}
if (strlen($senha) < 6) {
    fwrite(STDERR, "A senha deve ter ao menos 6 caracteres.\n");
    exit(1);
}

$hash = password_hash($senha, PASSWORD_BCRYPT);

// Usa o setor "TI" (criado pelo setup.php), se existir.
$idSetor = $pdo->query("SELECT id_setor FROM setor WHERE nome_setor = 'TI' LIMIT 1")->fetchColumn() ?: null;

$q = $pdo->prepare('SELECT id_usuario FROM usuario WHERE login = ?');
$q->execute([$login]);
$existente = $q->fetchColumn();

if ($existente) {
    $pdo->prepare("UPDATE usuario SET nome = ?, senha = ?, perfil = 'administrador', status = 'ativo', id_setor = ? WHERE id_usuario = ?")
        ->execute([$nome, $hash, $idSetor, $existente]);
    echo "Usuário '{$login}' atualizado como administrador.\n";
} else {
    $pdo->prepare("INSERT INTO usuario (nome, login, senha, status, id_setor, perfil) VALUES (?, ?, ?, 'ativo', ?, 'administrador')")
        ->execute([$nome, $login, $hash, $idSetor]);
    echo "Administrador '{$login}' criado com sucesso.\n";
}
