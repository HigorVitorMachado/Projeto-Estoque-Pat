<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de Estoque | PAT</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($pagina === 'login'): ?>
    <?php require __DIR__ . '/auth/login.php'; ?>
<?php else: ?>
    <div class="layout">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="main">
            <?php require __DIR__ . '/partials/header.php'; ?>
            <?php if ($flash): ?>
                <div class="alert <?= h($flash['tipo']) ?>"><?= h($flash['msg']) ?></div>
            <?php endif; ?>
            <?php if (!empty($erro)): ?>
                <div class="alert error"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php require $arquivoPagina; ?>
            <footer>Gestão de Estoque · PAT Itapetininga</footer>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
