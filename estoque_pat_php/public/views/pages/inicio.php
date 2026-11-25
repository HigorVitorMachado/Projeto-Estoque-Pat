<?php
$total = (int) $pdo->query('SELECT count(*) FROM produto')->fetchColumn();
$baixo = (int) $pdo->query('SELECT count(*) FROM produto WHERE estoque < qtd_minima')->fetchColumn();
$ped   = (int) $pdo->query("SELECT count(*) FROM pedido_compra WHERE status IN ('pendente', 'autorizado')")->fetchColumn();
$forn  = (int) $pdo->query('SELECT count(*) FROM fornecedor')->fetchColumn();

$it = consulta($pdo, produto_sql() . ' WHERE p.estoque < p.qtd_minima ORDER BY p.nome_produto LIMIT 8');
?>
<div class="stats">
    <div class="stat">
        <small>Produtos cadastrados</small>
        <strong><?= $total ?></strong>
        <span>Itens no catálogo</span>
    </div>
    <div class="stat warning">
        <small>Abaixo do mínimo</small>
        <strong><?= $baixo ?></strong>
        <span>Precisam de atenção</span>
    </div>
    <div class="stat">
        <small>Pedidos em aberto</small>
        <strong><?= $ped ?></strong>
        <span>Pendentes ou autorizados</span>
    </div>
    <div class="stat">
        <small>Fornecedores</small>
        <strong><?= $forn ?></strong>
        <span>Cadastros ativos</span>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Produtos que precisam de reposição</h3>
            <p>Itens com saldo abaixo da quantidade mínima</p>
        </div>
        <a class="btn" href="?pagina=estoque">Ver estoque</a>
    </div>

    <div class="table-wrap">
        <table>
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
                    <td><?= h($p['estoque']) ?> <?= h($p['unidade_medida']) ?></td>
                    <td><?= h($p['qtd_minima']) ?></td>
                    <td><span class="tag danger">Abaixo do mínimo</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$it): ?>
                <tr>
                    <td colspan="5" class="empty">Todos os produtos estão dentro do mínimo.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</section>
