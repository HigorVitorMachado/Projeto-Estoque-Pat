<?php
exige_perfil([]);

$aba     = $_GET['aba'] ?? 'usuarios';
$setores = consulta($pdo, 'SELECT * FROM setor ORDER BY nome_setor');
$users   = consulta(
    $pdo,
    'SELECT u.*, s.nome_setor
     FROM usuario u
     LEFT JOIN setor s ON s.id_setor = u.id_setor
     ORDER BY u.nome'
);

$eset = null; // setor em edição
$eu   = null; // usuário em edição

if (isset($_GET['editar_setor'])) {
    $s = $pdo->prepare('SELECT * FROM setor WHERE id_setor = ?');
    $s->execute([(int) $_GET['editar_setor']]);
    $eset = $s->fetch();
}

if (isset($_GET['editar_usuario'])) {
    $s = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = ?');
    $s->execute([(int) $_GET['editar_usuario']]);
    $eu = $s->fetch();
}
?>
<section class="panel">
    <div class="tabs">
        <a class="<?= $aba === 'usuarios' ? 'selected' : '' ?>" href="?pagina=admin&aba=usuarios">Usuários</a>
        <a class="<?= $aba === 'setores' ? 'selected' : '' ?>" href="?pagina=admin&aba=setores">Setores</a>
    </div>

    <?php if ($aba === 'setores'): ?>

        <div class="columns">
            <div>
                <h3><?= $eset ? 'Editar setor' : 'Novo setor' ?></h3>
                <form method="post" class="form-grid single">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="acao" value="salvar_setor">
                    <input type="hidden" name="id" value="<?= h($eset['id_setor'] ?? 0) ?>">

                    <label>Código
                        <input value="<?= $eset ? h(cod($eset['id_setor'])) : '' ?>"
                               placeholder="Gerado automaticamente" disabled>
                    </label>
                    <label>Nome do setor
                        <input name="nome_setor" required value="<?= h($eset['nome_setor'] ?? '') ?>">
                    </label>
                    <label>Responsável
                        <input name="responsavel" value="<?= h($eset['responsavel'] ?? '') ?>">
                    </label>

                    <?= modulos_checkboxes($eset['modulos'] ?? null) ?>
                    <small class="hint">Todos os usuários do setor recebem estes módulos.</small>

                    <button class="btn primary">Salvar setor</button>
                </form>
            </div>

            <div>
                <h3>Setores cadastrados</h3>
                <?php foreach ($setores as $s): ?>
                    <div class="list-row">
                        <b><?= h(cod($s['id_setor'])) ?> · <?= h($s['nome_setor']) ?></b>
                        <span><?= h($s['responsavel']) ?>
                            <small class="sub">Módulos: <?= h(implode(', ', array_map(fn($m) => MODULOS[$m], modulos_lista($s['modulos'] ?? ''))) ?: 'nenhum') ?></small>
                        </span>
                        <a href="?pagina=admin&aba=setores&editar_setor=<?= $s['id_setor'] ?>">Editar</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>

        <div class="columns">
            <div>
                <h3><?= $eu ? 'Editar usuário' : 'Novo usuário' ?></h3>
                <form method="post" class="form-grid single">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="acao" value="salvar_usuario">
                    <input type="hidden" name="id" value="<?= h($eu['id_usuario'] ?? 0) ?>">

                    <label>Código
                        <input value="<?= $eu ? h(cod($eu['id_usuario'])) : '' ?>"
                               placeholder="Gerado automaticamente" disabled>
                    </label>
                    <label>Nome
                        <input name="nome" required value="<?= h($eu['nome'] ?? '') ?>">
                    </label>
                    <label>Login
                        <input name="login" required value="<?= h($eu['login'] ?? '') ?>">
                    </label>
                    <label>Senha
                        <input type="password" name="senha"
                               <?= $eu ? '' : 'required' ?>
                               placeholder="<?= $eu ? 'Deixe vazio para manter' : 'Mínimo 6 caracteres' ?>">
                    </label>

                    <label>Perfil
                        <select name="perfil">
                            <?php foreach ($perfis as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($eu['perfil'] ?? 'comum') === $k ? 'selected' : '' ?>>
                                    <?= h($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Status
                        <select name="status">
                            <option value="ativo" <?= ($eu['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($eu['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </label>

                    <label>Setor
                        <select name="id_setor">
                            <option value="">Sem setor</option>
                            <?php foreach ($setores as $s): ?>
                                <option value="<?= $s['id_setor'] ?>"
                                    <?= ($eu['id_setor'] ?? '') == $s['id_setor'] ? 'selected' : '' ?>>
                                    <?= h($s['nome_setor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?= modulos_checkboxes($eu['modulos'] ?? null) ?>
                    <small class="hint">Acesso extra, somando ao que o setor do usuário já libera.</small>

                    <button class="btn primary">Salvar usuário</button>
                </form>
            </div>

            <div>
                <h3>Usuários cadastrados</h3>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        <?php foreach ($users as $x): ?>
                            <tr>
                                <td><?= h(cod($x['id_usuario'])) ?></td>
                                <td>
                                    <?= h($x['nome']) ?>
                                    <small class="sub"><?= h($x['login']) ?> · <?= h($x['nome_setor'] ?? 'Sem setor') ?></small>
                                </td>
                                <td><?= h($perfis[$x['perfil']] ?? $x['perfil']) ?></td>
                                <td><?= h($x['status']) ?></td>
                                <td><a href="?pagina=admin&aba=usuarios&editar_usuario=<?= $x['id_usuario'] ?>">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</section>
