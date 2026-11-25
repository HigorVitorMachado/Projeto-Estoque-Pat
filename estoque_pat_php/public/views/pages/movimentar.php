<?php
$produtos = consulta(
    $pdo,
    'SELECT id_produto, nome_produto, codigo, estoque, qtd_minima, unidade_medida
     FROM produto
     ORDER BY nome_produto'
);
?>
<div class="columns">
    <section class="panel">
        <h3>Registrar entrada ou saída</h3>
        <form method="post" class="form-grid single">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="acao" value="movimentar">

            <label>Tipo
                <select name="tipo" id="tipo">
                    <option value="saida">Saída de mercadoria</option>
                    <?php if (tem_modulo('entrada')): ?>
                        <option value="entrada">Entrada de mercadoria</option>
                    <?php endif; ?>
                </select>
            </label>

            <label>Produto
                <select name="id_produto" required>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?= $p['id_produto'] ?>">
                            <?= h($p['nome_produto']) ?> — saldo: <?= h($p['estoque']) ?> <?= h($p['unidade_medida']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Quantidade
                <input type="number" min="1" name="quantidade" required>
            </label>

            <button class="btn primary">Registrar movimentação</button>
        </form>
    </section>

    <section class="panel">
        <h3>Orientações</h3>
        <p>As entradas aumentam o saldo e as saídas diminuem o saldo automaticamente.</p>
        <p>
            Quando uma saída deixar o produto abaixo do mínimo, o sistema cria um pedido de compra
            automaticamente, caso não exista outro pedido em aberto para o produto.
        </p>
    </section>
</div>
