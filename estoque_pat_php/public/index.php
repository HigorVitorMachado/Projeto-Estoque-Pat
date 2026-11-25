<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require __DIR__ . '/app/helpers.php';

$pagina = $_GET['pagina'] ?? (usuario() ? 'inicio' : 'login');
$paginasPermitidas = ['login', 'inicio', 'estoque', 'produtos', 'fornecedores', 'movimentar', 'pedidos', 'relatorios', 'admin'];
if (!in_array($pagina, $paginasPermitidas, true)) {
    $pagina = 'nao_encontrada';
}
$erro = null;

require __DIR__ . '/app/actions.php';

if ($pagina === 'login' && usuario()) {
    redir('inicio');
}
if ($pagina !== 'login') {
    exige_login();
}

$u = usuario();

// Relatórios em PDF: responde com o arquivo, sem o layout HTML.
if ($pagina === 'relatorios' && isset($_GET['pdf'])) {
    require __DIR__ . '/app/relatorio_pdf.php';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$arquivoPagina = __DIR__ . '/views/pages/' . $pagina . '.php';
if (!is_file($arquivoPagina)) {
    $arquivoPagina = __DIR__ . '/views/pages/nao_encontrada.php';
}

require __DIR__ . '/views/layout.php';
