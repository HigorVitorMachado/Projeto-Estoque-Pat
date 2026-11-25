<?php
require dirname(__DIR__) . '/config.php';

try {
    // 1) Cria as tabelas e os dados iniciais do schema.sql.
    //    Os comandos são executados um a um para que qualquer erro apareça
    //    (um exec() com vários comandos esconde falhas depois do primeiro).
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    $schema = preg_replace('/^\s*--.*$/m', '', $schema);

    foreach (array_filter(array_map('trim', explode(';', $schema))) as $comando) {
        $pdo->exec($comando);
    }

    // 2) Garantias independentes (caso o banco já tenha dados antigos).
    $pdo->beginTransaction();

    // Setores: se a tabela estiver vazia, cadastra os iniciais.
    if ((int) $pdo->query('SELECT count(*) FROM setor')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO setor (nome_setor, responsavel) VALUES (?, ?)');
        foreach (['TI', 'Compras', 'PAT', 'Sebrae', 'Banco do Povo', 'Sala do Empreendedor', 'Gabinete'] as $nome) {
            $ins->execute([$nome, '']);
        }
    }

    $idTi = $pdo->query("SELECT id_setor FROM setor WHERE nome_setor = 'TI' LIMIT 1")->fetchColumn() ?: null;

    // Administrador: se o login "admin" não existir, cria; se existir, só garante o setor.
    $senha = getenv('ADMIN_SENHA') ?: 'admin123';
    $q = $pdo->prepare('SELECT id_usuario, id_setor FROM usuario WHERE login = ?');
    $q->execute(['admin']);
    $admin = $q->fetch();

    if (!$admin) {
        $pdo->prepare(
            "INSERT INTO usuario (nome, login, senha, status, id_setor, perfil)
             VALUES ('Administrador', 'admin', ?, 'ativo', ?, 'administrador')"
        )->execute([password_hash($senha, PASSWORD_BCRYPT), $idTi]);
    } elseif (empty($admin['id_setor'])) {
        $pdo->prepare('UPDATE usuario SET id_setor = ? WHERE id_usuario = ?')
            ->execute([$idTi, $admin['id_usuario']]);
    }

    $pdo->commit();

    $nSetores = (int) $pdo->query('SELECT count(*) FROM setor')->fetchColumn();

    echo '<h2>Banco preparado com sucesso.</h2>'
       . '<p>Setores cadastrados: <b>' . $nSetores . '</b></p>'
       . '<p>Usuário inicial: <b>admin</b> · Senha inicial: <b>'
       . htmlspecialchars(getenv('ADMIN_SENHA') ?: 'admin123')
       . '</b> (se o admin já existia, a senha dele não foi alterada por esta página)</p>'
       . '<p>Por segurança, exclua setup.php após a configuração e altere a senha do administrador.</p>'
       . '<a href="index.php">Abrir sistema</a>';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Falha na preparação: ' . htmlspecialchars($e->getMessage());
}
