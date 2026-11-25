<?php
$it = consulta($pdo, produto_sql() . ' ORDER BY p.nome_produto');
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Posição atual do estoque</h3>
            <p><?= count($it) ?> produtos cadastrados</p>
        </div>
        <a class="btn primary" href="?pagina=movimentar">Registrar movimentação</a>
    </div>

    <div class="table-wrap">
        <table>
            <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Fornecedor</th>
                <th>Disponível</th>
                <th>Mínimo</th>
                <th>Situação</th>
            </tr>
            <?php foreach ($it as $p): ?>
                <tr>
                    <td><?= h($p['codigo']) ?></td>
                    <td><?= h($p['nome_produto']) ?></td>
                    <td><?= h($p['razao_social'] ?? '-') ?></td>
                    <td><?= h($p['estoque']) ?> <?= h($p['unidade_medida']) ?></td>
                    <td><?= h($p['qtd_minima']) ?></td>
                    <td>
                        <span class="tag <?= $p['abaixo_minimo'] ? 'danger' : 'success' ?>">
                            <?= $p['abaixo_minimo'] ? 'Abaixo do mínimo' : 'Normal' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>
