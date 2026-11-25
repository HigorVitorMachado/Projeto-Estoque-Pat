<?php
exige_modulo('fornecedores');

$edit = null;
if (isset($_GET['editar'])) {
    $s = $pdo->prepare('SELECT * FROM fornecedor WHERE id_fornecedor = ?');
    $s->execute([(int) $_GET['editar']]);
    $edit = $s->fetch();
}

$it = consulta($pdo, 'SELECT * FROM fornecedor ORDER BY razao_social');
?>
<div class="columns">
    <section class="panel">
        <h3><?= $edit ? 'Editar fornecedor' : 'Novo fornecedor' ?></h3>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="acao" value="salvar_fornecedor">
            <input type="hidden" name="id" value="<?= h($edit['id_fornecedor'] ?? 0) ?>">

            <label>Código
                <input value="<?= $edit ? h(cod($edit['id_fornecedor'])) : '' ?>"
                       placeholder="Gerado automaticamente" disabled>
            </label>
            <label>Razão social
                <input name="razao_social" required value="<?= h($edit['razao_social'] ?? '') ?>">
            </label>
            <label>CNPJ
                <input name="cnpj" required value="<?= h($edit['cnpj'] ?? '') ?>">
            </label>
            <label>Contato
                <input name="contato" value="<?= h($edit['contato'] ?? '') ?>">
            </label>
            <label class="span2">Endereço
                <input name="endereco" value="<?= h($edit['endereco'] ?? '') ?>">
            </label>

            <div class="span2">
                <button class="btn primary">Salvar fornecedor</button>
                <?php if ($edit): ?>
                    <a class="btn" href="?pagina=fornecedores">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>Fornecedores</h3>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Código</th>
                    <th>Razão social</th>
                    <th>CNPJ</th>
                    <th>Contato</th>
                    <th></th>
                </tr>
                <?php foreach ($it as $f): ?>
                    <tr>
                        <td><?= h(cod($f['id_fornecedor'])) ?></td>
                        <td><?= h($f['razao_social']) ?></td>
                        <td><?= h($f['cnpj']) ?></td>
                        <td><?= h($f['contato']) ?></td>
                        <td><a href="?pagina=fornecedores&editar=<?= $f['id_fornecedor'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>
</div>
