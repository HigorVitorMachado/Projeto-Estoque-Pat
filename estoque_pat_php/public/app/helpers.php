<?php

/** Executa uma consulta preparada e devolve todas as linhas. */
function consulta(PDO $pdo, string $sql, array $args = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** Formata um ID como código de exibição (1 → 0001). */
function cod($id): string
{
    return str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/** SELECT base de produtos (com fornecedor e flag "abaixo do mínimo"). */
function produto_sql(): string
{
    return "SELECT p.*,
                   f.razao_social,
                   (p.estoque < p.qtd_minima) AS abaixo_minimo
            FROM produto p
            LEFT JOIN fornecedor f ON f.id_fornecedor = p.id_fornecedor";
}

$perfis = [
    'comum'         => 'Usuário comum',
    'estagiario'    => 'Estagiário',
    'responsavel'   => 'Responsável do setor',
    'administrador' => 'Administrador',
];

/** Caixinhas de módulos (usadas no cadastro de setor e de usuário). */
function modulos_checkboxes(?string $csv): string
{
    $marcados = modulos_lista($csv);
    $html = '<fieldset class="modulos"><legend>Módulos liberados</legend>';
    foreach (MODULOS as $k => $rotulo) {
        $html .= '<label class="check"><input type="checkbox" name="modulos[]" value="' . h($k) . '"'
               . (in_array($k, $marcados, true) ? ' checked' : '') . '> <span>' . h($rotulo) . '</span></label>';
    }
    return $html . '</fieldset>';
}
