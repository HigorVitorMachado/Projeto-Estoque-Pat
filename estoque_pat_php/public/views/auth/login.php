<div class="login-wrap">
    <form class="login-card" method="post" action="">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="acao" value="login">
        <div class="brand-mark">PAT</div>
        <h1>Gestão de Estoque</h1>
        <p>Acesse sua conta para continuar</p>
        <?php if (!empty($erro)): ?>
            <div class="alert error"><?= h($erro) ?></div>
        <?php endif; ?>
        <label>Login<input name="login" required autofocus autocomplete="username"></label>
        <label>Senha<input type="password" name="senha" required autocomplete="current-password"></label>
        <button class="btn primary full" type="submit">Entrar</button>
    </form>
</div>
