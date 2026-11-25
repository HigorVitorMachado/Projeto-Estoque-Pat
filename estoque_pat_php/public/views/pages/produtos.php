<?php
exige_modulo('produtos');

$edit = null;
if (isset($_GET['editar'])) {
    $s = $pdo->prepare('SELECT * FROM produto WHERE id_produto = ?');
    $s->execute([(int) $_GET['editar']]);
    $edit = $s->fetch();
}

$forns = consulta($pdo, 'SELECT * FROM fornecedor ORDER BY razao_social');
$it    = consulta($pdo, produto_sql() . ' ORDER BY p.nome_produto');
?>
<div class="columns">
    <section class="panel">
        <h3><?= $edit ? 'Editar produto' : 'Cadastrar produto' ?></h3>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="acao" value="salvar_produto">
            <input type="hidden" name="id" value="<?= h($edit['id_produto'] ?? 0) ?>">

            <label>Código
                <input value="<?= $edit ? h($edit['codigo']) : '' ?>"
                       placeholder="Gerado automaticamente" disabled>
            </label>
            <label>Nome do produto
                <input name="nome_produto" required value="<?= h($edit['nome_produto'] ?? '') ?>">
            </label>
            <label>Unidade de medida
                <input name="unidade_medida" placeholder="Un, caixa, kg..." value="<?= h($edit['unidade_medida'] ?? '') ?>">
            </label>
            <label>Quantidade mínima
                <input type="number" min="0" name="qtd_minima" required value="<?= h($edit['qtd_minima'] ?? 0) ?>">
            </label>
            <label>Preço unitário
                <input type="number" min="0" step=".01" name="preco" required value="<?= h($edit['preco'] ?? '0.00') ?>">
            </label>
            <label>Classificação
                <input name="classificacao" value="<?= h($edit['classificacao'] ?? '') ?>">
            </label>

            <label>Fornecedor
                <select name="id_fornecedor">
                    <option value="">Sem fornecedor</option>
                    <?php foreach ($forns as $f): ?>
                        <option value="<?= $f['id_fornecedor'] ?>"
                            <?= ($edit['id_fornecedor'] ?? '') == $f['id_fornecedor'] ? 'selected' : '' ?>>
                            <?= h($f['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="span2">Descrição
                <textarea name="descricao"><?= h($edit['descricao'] ?? '') ?></textarea>
            </label>

            <div class="span2">
                <button class="btn primary">Salvar produto</button>
                <?php if ($edit): ?>
                    <a class="btn" href="?pagina=produtos">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
        <p class="hint">O saldo do estoque é alterado somente por entradas e saídas.</p>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Produtos cadastrados</h3>
                <p>Selecione editar para alterar o cadastro</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>Produto</th>
                    <th>Saldo</th>
                    <th></th>
                </tr>
                <?php foreach ($it as $p): ?>
                    <tr>
                        <td>
                            <b><?= h($p['nome_produto']) ?></b>
                            <small class="sub"><?= h($p['codigo']) ?></small>
                        </td>
                        <td><?= h($p['estoque']) ?></td>
                        <td><a href="?pagina=produtos&editar=<?= $p['id_produto'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>
</div>
