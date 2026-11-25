<?php
exige_modulo('relatorios', ['responsavel']);

$aba = $_GET['aba'] ?? 'posicao';
$ini = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fim = $_GET['fim'] ?? date('Y-m-d');
$it  = [];

if ($aba === 'movimentacao') {
    $s = $pdo->prepare(
        "SELECT m.*,
                p.nome_produto,
                CASE WHEN m.id_entrada IS NOT NULL THEN 'entrada' ELSE 'saida' END AS tipo
         FROM movimentacao m
         LEFT JOIN entrada e ON e.id_ordem_entrada = m.id_entrada
         LEFT JOIN saida x   ON x.id_ordem_saida   = m.id_saida
         LEFT JOIN produto p ON p.id_produto = COALESCE(e.id_produto, x.id_produto)
         WHERE m.data BETWEEN ? AND ?
         ORDER BY m.data DESC, m.id_movimentacao DESC"
    );
    $s->execute([$ini, $fim]);
    $it = $s->fetchAll();
} else {
    $it = consulta($pdo, produto_sql() . ' ORDER BY p.nome_produto');
}
?>
<section class="panel">
    <div class="tabs">
        <a class="<?= $aba === 'posicao' ? 'selected' : '' ?>" href="?pagina=relatorios&aba=posicao">Posição atual</a>
        <a class="<?= $aba === 'movimentacao' ? 'selected' : '' ?>" href="?pagina=relatorios&aba=movimentacao">Movimentação</a>
    </div>

    <?php if ($aba === 'movimentacao'): ?>
        <form class="filters">
            <input type="hidden" name="pagina" value="relatorios">
            <input type="hidden" name="aba" value="movimentacao">
            <label>De
                <input type="date" name="inicio" value="<?= h($ini) ?>">
            </label>
            <label>Até
                <input type="date" name="fim" value="<?= h($fim) ?>">
            </label>
            <button class="btn">Filtrar</button>
            <button class="btn primary" type="submit" name="pdf" value="1" formtarget="_blank">📄 Gerar PDF</button>
        </form>
    <?php else: ?>
        <div class="filters">
            <a class="btn primary" target="_blank" href="?pagina=relatorios&aba=posicao&pdf=1">📄 Gerar PDF</a>
            <a class="btn" target="_blank" href="?pagina=relatorios&aba=posicao&pdf=1&so_baixo=1">📄 PDF só abaixo do mínimo</a>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <?php if ($aba === 'movimentacao'): ?>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Observação</th>
                </tr>
                <?php foreach ($it as $m): ?>
                    <tr>
                        <td><?= h($m['data']) ?></td>
                        <td><?= h(ucfirst($m['tipo'])) ?></td>
                        <td><?= h($m['nome_produto']) ?></td>
                        <td><?= $m['quantidade'] ?></td>
                        <td><?= h($m['observacao']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Disponível</th>
                    <th>Mínimo</th>
                    <th>Situação</th>
                </tr>
                <?php foreach ($it as $p): ?>
                    <tr>
                        <td><?= h($p['codigo']) ?></td>
                        <td><?= h($p['nome_produto']) ?></td>
                        <td><?= $p['estoque'] ?></td>
                        <td><?= $p['qtd_minima'] ?></td>
                        <td><?= ((int) $p['estoque'] < (int) $p['qtd_minima']) ? 'Abaixo do mínimo' : 'Normal' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</section>
