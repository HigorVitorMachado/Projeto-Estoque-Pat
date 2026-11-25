<?php
$titulos = [
    'inicio' => 'Visão geral', 'estoque' => 'Estoque', 'produtos' => 'Produtos',
    'fornecedores' => 'Fornecedores', 'movimentar' => 'Movimentação',
    'pedidos' => 'Pedidos de compra', 'relatorios' => 'Relatórios', 'admin' => 'Administração',
];
?>
<header class="topbar">
    <div><small>PAINEL / <?= h(strtoupper($pagina)) ?></small><h2><?= h($titulos[$pagina] ?? 'Gestão de Estoque') ?></h2></div>
    <div class="user-pill"><?= h($u['nome']) ?></div>
</header>
