<?php
$sql = 'SELECT pc.id_pedido, pc.quantidade, pc.status, pc.data, pc.id_produto,
               COALESCE(p.nome_produto, pc.nome_produto) AS nome_produto, p.codigo,
               u.nome AS solicitante, s.nome_setor
        FROM pedido_compra pc
        LEFT JOIN produto p ON p.id_produto = pc.id_produto
        LEFT JOIN usuario u ON u.id_usuario = pc.id_usuario
        LEFT JOIN setor s ON s.id_setor = u.id_setor';
$args = [];

// Quem não é do setor de compras só enxerga os próprios pedidos e os do seu setor.
if (!tem_modulo('pedidos')) {
    $sql .= ' WHERE (pc.id_usuario = ? OR u.id_setor = ?)';
    $args = [$u['id_usuario'], $u['id_setor']];
}
$sql .= ' ORDER BY pc.id_pedido DESC LIMIT 300';

$it       = consulta($pdo, $sql, $args);
$produtos = consulta($pdo, 'SELECT id_produto, nome_produto FROM produto ORDER BY nome_produto');
$ehCompras = tem_modulo('pedidos');
?>
<div class="columns">
    <section class="panel">
        <h3>Solicitar compra</h3>
        <form method="post" class="form-grid single">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="acao" value="criar_pedido">

            <div class="campo">
                <span>Produto</span>
                <div class="busca-produto">
                    <input type="text" name="nome_produto" id="nome_produto" maxlength="100" required
                           autocomplete="off" placeholder="DIGITE O NOME DO PRODUTO">
                    <button type="button" class="btn lupa" id="btn_lupa"
                            title="Pesquisar produto já cadastrado" aria-label="Pesquisar produto já cadastrado">🔍</button>
                    <div class="resultados" id="resultados" hidden></div>
                </div>
                <small class="hint" id="msg_produto"></small>
            </div>
            <label>Quantidade
                <input type="number" name="quantidade" min="1" required>
            </label>

            <button class="btn primary">Criar pedido</button>
        </form>
    </section>

    <section class="panel">
        <h3>Pedidos de compra</h3>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Pedido</th>
                    <th>Produto</th>
                    <th>Qtd.</th>
                    <th>Solicitante</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
                <?php foreach ($it as $p): ?>
                    <tr>
                        <td>
                            #<?= h(cod($p['id_pedido'])) ?>
                            <small class="sub"><?= h($p['data']) ?></small>
                        </td>
                        <td>
                            <?= h($p['nome_produto']) ?>
                            <?php if (!$p['id_produto']): ?>
                                <small class="sub">Produto novo: será cadastrado ao concluir</small>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['quantidade'] ?></td>
                        <td><?= h($p['solicitante'] ?? '-') ?></td>
                        <td>
                            <span class="tag <?= h($p['status']) ?>"><?= h(STATUS_PEDIDO[$p['status']] ?? ucfirst((string) $p['status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($ehCompras && $p['status'] !== 'concluido'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                    <input type="hidden" name="acao" value="status_pedido">
                                    <input type="hidden" name="id_pedido" value="<?= $p['id_pedido'] ?>">
                                    <select name="status" class="status-select" data-atual="<?= h($p['status']) ?>"
                                            data-qtd="<?= (int) $p['quantidade'] ?>"
                                            onchange="if (this.value === 'concluido' && !confirm('Concluir o pedido adiciona ' + this.dataset.qtd + ' unidade(s) ao estoque. Confirmar?')) { this.value = this.dataset.atual; return; } this.form.submit();">
                                        <?php foreach (STATUS_PEDIDO as $k => $rotulo): ?>
                                            <option value="<?= $k ?>"
                                                <?= $p['status'] === $k ? 'selected' : '' ?>
                                                <?= ($k === 'concluido' && $p['status'] !== 'autorizado') ? 'disabled' : '' ?>>
                                                <?= h($rotulo) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button class="btn small">Salvar</button></noscript>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>
</div>

<script>
(function () {
    const produtos = <?= json_encode(array_map(fn($x) => $x['nome_produto'], $produtos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const inp = document.getElementById('nome_produto');
    const btn = document.getElementById('btn_lupa');
    const box = document.getElementById('resultados');
    const msg = document.getElementById('msg_produto');

    const norm = t => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();

    function atualizaMensagem() {
        const v = norm(inp.value);
        if (!v) { msg.textContent = ''; return; }
        const existe = produtos.some(n => norm(n) === v);
        msg.textContent = existe
            ? '✔ Produto já cadastrado.'
            : 'Produto novo: será cadastrado automaticamente quando o pedido for concluído.';
    }

    function lista() {
        const v = norm(inp.value);
        const achados = produtos.filter(n => norm(n).includes(v)).slice(0, 50);
        box.innerHTML = '';
        if (!achados.length) {
            const vazio = document.createElement('div');
            vazio.className = 'vazio';
            vazio.textContent = 'Nenhum produto cadastrado encontrado.';
            box.appendChild(vazio);
        }
        achados.forEach(nome => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = nome.toUpperCase();
            b.addEventListener('click', () => {
                inp.value = nome.toUpperCase();
                box.hidden = true;
                atualizaMensagem();
                inp.focus();
            });
            box.appendChild(b);
        });
    }

    inp.addEventListener('input', () => {
        const pos = inp.selectionStart;
        inp.value = inp.value.toUpperCase();
        inp.setSelectionRange(pos, pos);
        atualizaMensagem();
        if (!box.hidden) lista();
    });

    btn.addEventListener('click', () => {
        box.hidden = !box.hidden;
        if (!box.hidden) lista();
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.busca-produto')) box.hidden = true;
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') box.hidden = true;
    });
})();
</script>
