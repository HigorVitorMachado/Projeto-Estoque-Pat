<?php
declare(strict_types=1);

/**
 * Gera os relatórios em PDF.
 *   ?pagina=relatorios&pdf=1&aba=posicao[&so_baixo=1]
 *   ?pagina=relatorios&pdf=1&aba=movimentacao&inicio=AAAA-MM-DD&fim=AAAA-MM-DD
 * Chamado pelo index.php antes de qualquer HTML; exige o módulo "Relatórios".
 */

exige_modulo('relatorios', ['responsavel']);
require_once __DIR__ . '/pdf.php';

function data_br(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('d/m/Y', $t) : '';
}

function data_valida(?string $d, string $padrao): string
{
    return ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false) ? $d : $padrao;
}

$aba    = ($_GET['aba'] ?? 'posicao') === 'movimentacao' ? 'movimentacao' : 'posicao';
$rodape = 'Gerado em ' . date('d/m/Y H:i') . ' por ' . (usuario()['nome'] ?? '');

if ($aba === 'movimentacao') {
    $ini = data_valida($_GET['inicio'] ?? null, date('Y-m-d', strtotime('-30 days')));
    $fim = data_valida($_GET['fim'] ?? null, date('Y-m-d'));
    if ($ini > $fim) {
        [$ini, $fim] = [$fim, $ini];
    }

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
    $dados = $s->fetchAll();

    $entradas = 0;
    $saidas   = 0;
    $linhas   = [];
    foreach ($dados as $m) {
        $q = (int) $m['quantidade'];
        if ($m['tipo'] === 'entrada') {
            $entradas += $q;
        } else {
            $saidas += $q;
        }
        $linhas[] = [
            data_br($m['data']),
            $m['tipo'] === 'entrada' ? 'Entrada' : 'Saída',
            (string) $m['nome_produto'],
            (string) $q,
            (string) $m['observacao'],
        ];
    }

    $pdf = new PdfRelatorio('Relatório de Movimentação', 'Período: ' . data_br($ini) . ' a ' . data_br($fim), $rodape);
    $pdf->resumo([
        ['MOVIMENTAÇÕES', count($dados)],
        ['UNIDADES QUE ENTRARAM', $entradas],
        ['UNIDADES QUE SAÍRAM', $saidas],
    ]);
    $pdf->tabela(
        [['Data', 12], ['Tipo', 10], ['Produto', 34], ['Qtd.', 8, 'R'], ['Observação', 36]],
        $linhas
    );
    $arquivo = 'relatorio-movimentacao-' . $ini . '_a_' . $fim . '.pdf';
} else {
    $soBaixo = !empty($_GET['so_baixo']);
    $dados   = consulta($pdo, produto_sql() . ' ORDER BY p.nome_produto');

    $total = 0;
    $baixo = 0;
    $un    = 0;
    $linhas = [];
    foreach ($dados as $p) {
        $abaixo = (int) $p['estoque'] < (int) $p['qtd_minima'];
        $total++;
        $un += (int) $p['estoque'];
        if ($abaixo) {
            $baixo++;
        }
        if ($soBaixo && !$abaixo) {
            continue;
        }
        $linhas[] = [
            (string) $p['codigo'],
            (string) $p['nome_produto'],
            (string) ($p['unidade_medida'] ?? ''),
            (string) (int) $p['estoque'],
            (string) (int) $p['qtd_minima'],
            $abaixo ? 'Abaixo do mínimo' : 'Normal',
            'destaque' => $abaixo,
        ];
    }

    $pdf = new PdfRelatorio(
        'Posição Atual do Estoque',
        $soBaixo ? 'Somente produtos abaixo do mínimo' : 'Todos os produtos cadastrados',
        $rodape
    );
    $pdf->resumo([
        ['PRODUTOS CADASTRADOS', $total],
        ['ABAIXO DO MÍNIMO', $baixo],
        ['UNIDADES EM ESTOQUE', $un],
    ]);
    $pdf->tabela(
        [['Código', 11], ['Produto', 38], ['Unid.', 9], ['Disponível', 12, 'R'], ['Mínimo', 10, 'R'], ['Situação', 20]],
        $linhas
    );
    $arquivo = 'relatorio-estoque-' . date('Y-m-d') . '.pdf';
}

$bytes = $pdf->gerar();

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $arquivo . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, no-store');
echo $bytes;
exit;
