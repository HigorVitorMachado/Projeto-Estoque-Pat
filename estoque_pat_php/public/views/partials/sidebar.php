<aside class="sidebar">
    <div class="brand"><span>PAT</span><div><b>Gestão de Estoque</b><small>Controle de materiais</small></div></div>
    <nav>
        <a class="<?= $pagina === 'inicio' ? 'active' : '' ?>" href="?pagina=inicio">⌂ <span>Início</span></a>
        <a class="<?= $pagina === 'estoque' ? 'active' : '' ?>" href="?pagina=estoque">▦ <span>Estoque</span></a>
        <?php if (tem_modulo('produtos')): ?>
            <a class="<?= $pagina === 'produtos' ? 'active' : '' ?>" href="?pagina=produtos">▤ <span>Produtos</span></a>
        <?php endif; ?>
        <?php if (tem_modulo('fornecedores')): ?>
            <a class="<?= $pagina === 'fornecedores' ? 'active' : '' ?>" href="?pagina=fornecedores">♧ <span>Fornecedores</span></a>
        <?php endif; ?>
        <a class="<?= $pagina === 'movimentar' ? 'active' : '' ?>" href="?pagina=movimentar">⇄ <span>Movimentar</span></a>
        <a class="<?= $pagina === 'pedidos' ? 'active' : '' ?>" href="?pagina=pedidos">▣ <span>Pedidos</span></a>
        <?php if (tem_modulo('relatorios', ['responsavel'])): ?>
            <a class="<?= $pagina === 'relatorios' ? 'active' : '' ?>" href="?pagina=relatorios">▥ <span>Relatórios</span></a>
        <?php endif; ?>
        <?php if (tem_perfil([])): ?>
            <a class="<?= $pagina === 'admin' ? 'active' : '' ?>" href="?pagina=admin">⚙ <span>Administração</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-user">
        <b><?= h($u['nome']) ?></b>
        <small><?= h($perfis[$u['perfil']] ?? $u['perfil']) ?></small>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="acao" value="sair">
            <button class="logout" type="submit">↪ Sair</button>
        </form>
    </div>
</aside>
