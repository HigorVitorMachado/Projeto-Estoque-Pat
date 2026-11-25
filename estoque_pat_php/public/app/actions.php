<?php

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validar_csrf();
        $acao = post('acao', '');

        /* ---------------------------- Login ---------------------------- */
        if ($acao === 'login') {
            $q = $pdo->prepare(
                'SELECT u.id_usuario, u.nome, u.login, u.senha, u.status, u.perfil, u.id_setor, s.nome_setor
                 FROM usuario u
                 LEFT JOIN setor s ON s.id_setor = u.id_setor
                 WHERE u.login = ?'
            );
            $q->execute([obrigatorio('login')]);
            $u = $q->fetch();

            if (!$u || !password_verify((string) post('senha'), $u['senha'])) {
                throw new RuntimeException('Login ou senha inválidos.');
            }
            if ($u['status'] !== 'ativo') {
                throw new RuntimeException('Usuário inativo. Procure o setor de TI.');
            }

            unset($u['senha']);
            session_regenerate_id(true);
            $_SESSION['usuario'] = $u;
            redir('inicio');
        }

        // A partir daqui, todas as ações exigem usuário logado.
        exige_login();

        if ($acao === 'sair') {
            session_destroy();
            header('Location: ?pagina=login');
            exit;
        }

        switch ($acao) {

            /* ------------------------ Produto ------------------------ */
            case 'salvar_produto':
                exige_modulo('produtos');

                $id    = (int) post('id', 0);
                // O código NÃO é lido do formulário: é gerado pelo sistema e nunca alterado.
                $dados = [
                    obrigatorio('nome_produto'),
                    trim((string) post('descricao')),
                    trim((string) post('unidade_medida')),
                    inteiro('qtd_minima'),
                    num_decimal('preco'),
                    trim((string) post('classificacao')),
                    post('id_fornecedor') ?: null,
                ];

                if ($id) {
                    $s = $pdo->prepare(
                        'UPDATE produto
                         SET nome_produto = ?, descricao = ?, unidade_medida = ?,
                             qtd_minima = ?, preco = ?, classificacao = ?, id_fornecedor = ?
                         WHERE id_produto = ?'
                    );
                    $s->execute([...$dados, $id]);
                } else {
                    $pdo->beginTransaction();

                    $s = $pdo->prepare(
                        'INSERT INTO produto
                             (nome_produto, descricao, unidade_medida, qtd_minima,
                              estoque, preco, classificacao, id_fornecedor)
                         VALUES (?, ?, ?, ?, 0, ?, ?, ?)'
                    );
                    $s->execute($dados);

                    // Código automático: baseado no ID gerado (0001, 0002, ...).
                    $novoId = (int) $pdo->lastInsertId();
                    $pdo->prepare('UPDATE produto SET codigo = ? WHERE id_produto = ?')
                        ->execute([cod($novoId), $novoId]);

                    $pdo->commit();
                }

                flash('Produto salvo com sucesso.');
                redir('produtos');

            /* ---------------------- Fornecedor ----------------------- */
            case 'salvar_fornecedor':
                exige_modulo('fornecedores');

                $id = (int) post('id', 0);
                $d  = [
                    obrigatorio('razao_social'),
                    obrigatorio('cnpj'),
                    trim((string) post('contato')),
                    trim((string) post('endereco')),
                ];

                if ($id) {
                    $s = $pdo->prepare(
                        'UPDATE fornecedor
                         SET razao_social = ?, cnpj = ?, contato = ?, endereco = ?
                         WHERE id_fornecedor = ?'
                    );
                    $s->execute([...$d, $id]);
                } else {
                    $s = $pdo->prepare(
                        'INSERT INTO fornecedor (razao_social, cnpj, contato, endereco)
                         VALUES (?, ?, ?, ?)'
                    );
                    $s->execute($d);
                }

                flash('Fornecedor salvo.');
                redir('fornecedores');

            /* ----------------------- Usuário ------------------------- */
            case 'salvar_usuario':
                exige_perfil([]);

                $id     = (int) post('id', 0);
                $nome   = obrigatorio('nome');
                $login  = obrigatorio('login');
                $perfil = obrigatorio('perfil');
                $status = obrigatorio('status');
                $setor  = post('id_setor') ?: null;
                $senha  = (string) post('senha');
                $modulos = implode(',', modulos_lista(implode(',', (array) post('modulos', []))));

                $perfisValidos = ['comum', 'estagiario', 'responsavel', 'administrador'];
                if (!in_array($perfil, $perfisValidos, true) || !in_array($status, ['ativo', 'inativo'], true)) {
                    throw new RuntimeException('Perfil ou status inválido.');
                }

                if ($id) {
                    // Edição: a senha só é alterada se vier preenchida.
                    if ($senha !== '' && strlen($senha) < 6) {
                        throw new RuntimeException('A senha deve ter ao menos 6 caracteres.');
                    }

                    $sql  = 'UPDATE usuario SET nome = ?, login = ?, perfil = ?, status = ?, id_setor = ?, modulos = ?'
                          . ($senha !== '' ? ', senha = ?' : '')
                          . ' WHERE id_usuario = ?';
                    $args = [$nome, $login, $perfil, $status, $setor, $modulos];

                    if ($senha !== '') {
                        $args[] = password_hash($senha, PASSWORD_BCRYPT);
                    }
                    $args[] = $id;

                    $pdo->prepare($sql)->execute($args);
                } else {
                    if (strlen($senha) < 6) {
                        throw new RuntimeException('A senha deve ter ao menos 6 caracteres.');
                    }

                    $pdo->prepare(
                        'INSERT INTO usuario (nome, login, senha, perfil, status, id_setor, modulos)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$nome, $login, password_hash($senha, PASSWORD_BCRYPT), $perfil, $status, $setor, $modulos]);
                }

                flash('Usuário salvo.');
                redir('admin');

            /* ------------------------- Setor ------------------------- */
            case 'salvar_setor':
                exige_perfil([]);

                $id = (int) post('id', 0);
                $d  = [
                    obrigatorio('nome_setor'),
                    trim((string) post('responsavel')),
                    implode(',', modulos_lista(implode(',', (array) post('modulos', [])))),
                ];

                if ($id) {
                    $pdo->prepare('UPDATE setor SET nome_setor = ?, responsavel = ?, modulos = ? WHERE id_setor = ?')
                        ->execute([...$d, $id]);
                } else {
                    $pdo->prepare('INSERT INTO setor (nome_setor, responsavel, modulos) VALUES (?, ?, ?)')
                        ->execute($d);
                }

                flash('Setor salvo.');
                redir('admin');

            /* -------------------- Movimentação ----------------------- */
            case 'movimentar':
                $tipo = obrigatorio('tipo');
                $prod = inteiro('id_produto', 1);
                $qtd  = inteiro('quantidade', 1);
                $uid  = usuario()['id_usuario'];

                if (!in_array($tipo, ['entrada', 'saida'], true)) {
                    throw new RuntimeException('Tipo de movimentação inválido.');
                }
                if ($tipo === 'entrada') {
                    exige_modulo('entrada');
                }

                $pdo->beginTransaction();

                // Trava a linha do produto para evitar saldo inconsistente em acessos simultâneos.
                $s = $pdo->prepare('SELECT estoque, qtd_minima FROM produto WHERE id_produto = ? FOR UPDATE');
                $s->execute([$prod]);
                $p = $s->fetch();

                if (!$p) {
                    throw new RuntimeException('Produto não encontrado.');
                }
                if ($tipo === 'saida' && $qtd > (int) $p['estoque']) {
                    throw new RuntimeException('Estoque insuficiente. Disponível: ' . $p['estoque']);
                }

                if ($tipo === 'entrada') {
                    $s = $pdo->prepare('INSERT INTO entrada (quantidade, data, id_produto) VALUES (?, CURRENT_DATE, ?)');
                    $s->execute([$qtd, $prod]);
                    $eid = $pdo->lastInsertId();

                    $pdo->prepare('UPDATE produto SET estoque = estoque + ? WHERE id_produto = ?')
                        ->execute([$qtd, $prod]);

                    $pdo->prepare(
                        'INSERT INTO movimentacao (quantidade, data, observacao, id_entrada)
                         VALUES (?, CURRENT_DATE, ?, ?)'
                    )->execute([$qtd, 'Entrada de mercadoria', $eid]);
                } else {
                    $s = $pdo->prepare('INSERT INTO saida (quantidade, data, id_usuario, id_produto) VALUES (?, CURRENT_DATE, ?, ?)');
                    $s->execute([$qtd, $uid, $prod]);
                    $sid = $pdo->lastInsertId();

                    $pdo->prepare('UPDATE produto SET estoque = estoque - ? WHERE id_produto = ?')
                        ->execute([$qtd, $prod]);

                    $pdo->prepare(
                        'INSERT INTO movimentacao (quantidade, data, observacao, id_saida)
                         VALUES (?, CURRENT_DATE, ?, ?)'
                    )->execute([$qtd, 'Saída de mercadoria', $sid]);

                    // Se o saldo ficou abaixo do mínimo, abre um pedido de compra automático
                    // (somente se ainda não houver pedido pendente/autorizado para o produto).
                    $saldo = (int) $p['estoque'] - $qtd;
                    if ($saldo < (int) $p['qtd_minima']) {
                        $s = $pdo->prepare(
                            "SELECT 1 FROM pedido_compra
                             WHERE id_produto = ? AND status IN ('pendente', 'autorizado')
                             LIMIT 1"
                        );
                        $s->execute([$prod]);

                        if (!$s->fetchColumn()) {
                            $qtdPedido = (int) $p['qtd_minima'] * 2 - $saldo;

                            $pdo->prepare(
                                "INSERT INTO pedido_compra (quantidade, status, data, id_usuario, id_produto)
                                 VALUES (?, 'pendente', CURRENT_DATE, ?, ?)"
                            )->execute([$qtdPedido, $uid, $prod]);
                        }
                    }
                }

                $pdo->commit();
                flash(ucfirst($tipo) . ' registrada com sucesso.');
                redir('movimentar');

            /* ----------------------- Pedidos ------------------------- */
            case 'criar_pedido':
                // O usuário digita o nome (sempre em MAIÚSCULAS). Se o produto já existe, o pedido
                // fica ligado a ele; se não existe, será cadastrado quando o pedido for concluído.
                $nome = mb_strtoupper(obrigatorio('nome_produto'), 'UTF-8');
                $qtd  = inteiro('quantidade', 1);

                if (mb_strlen($nome, 'UTF-8') > 100) {
                    throw new RuntimeException('O nome do produto deve ter no máximo 100 caracteres.');
                }

                $s = $pdo->prepare('SELECT id_produto FROM produto WHERE nome_produto = ? LIMIT 1');
                $s->execute([$nome]);
                $prod = $s->fetchColumn() ?: null;

                $pdo->prepare(
                    "INSERT INTO pedido_compra (quantidade, status, data, id_usuario, id_produto, nome_produto)
                     VALUES (?, 'pendente', CURRENT_DATE, ?, ?, ?)"
                )->execute([$qtd, usuario()['id_usuario'], $prod, $nome]);

                flash($prod ? 'Pedido criado.' : 'Pedido criado. O produto será cadastrado quando o pedido for concluído.');
                redir('pedidos');

            case 'status_pedido':
                // Somente quem tem o módulo "Gerenciar pedidos" (setor Compras / administrador).
                exige_modulo('pedidos');

                $id   = inteiro('id_pedido', 1);
                $novo = obrigatorio('status');

                if (!array_key_exists($novo, STATUS_PEDIDO)) {
                    throw new RuntimeException('Status inválido.');
                }

                $pdo->beginTransaction();

                $s = $pdo->prepare('SELECT status, quantidade, id_produto, nome_produto FROM pedido_compra WHERE id_pedido = ? FOR UPDATE');
                $s->execute([$id]);
                $p = $s->fetch();

                if (!$p) {
                    throw new RuntimeException('Pedido não encontrado.');
                }
                if ($p['status'] === $novo) {
                    $pdo->rollBack();
                    redir('pedidos');
                }
                // Pedido concluído já entrou no estoque: não pode mais ser alterado.
                if ($p['status'] === 'concluido') {
                    throw new RuntimeException('Pedido concluído não pode ser alterado.');
                }
                // Só conclui (e dá entrada no estoque) um pedido que foi autorizado.
                if ($novo === 'concluido' && $p['status'] !== 'autorizado') {
                    throw new RuntimeException('Autorize o pedido antes de concluí-lo.');
                }

                if ($novo === 'concluido') {
                    $idProduto = $p['id_produto'];

                    // Produto ainda não cadastrado: cadastra automaticamente ao concluir o pedido.
                    if (!$idProduto) {
                        $nomeNovo = mb_strtoupper(trim((string) $p['nome_produto']), 'UTF-8');
                        if ($nomeNovo === '') {
                            throw new RuntimeException('Pedido sem produto informado.');
                        }

                        // Se alguém já cadastrou esse nome nesse meio tempo, reaproveita o cadastro.
                        $s = $pdo->prepare('SELECT id_produto FROM produto WHERE nome_produto = ? LIMIT 1');
                        $s->execute([$nomeNovo]);
                        $idProduto = $s->fetchColumn() ?: null;

                        if (!$idProduto) {
                            $pdo->prepare(
                                'INSERT INTO produto (nome_produto, qtd_minima, estoque, preco)
                                 VALUES (?, 0, 0, 0)'
                            )->execute([$nomeNovo]);
                            $idProduto = (int) $pdo->lastInsertId();

                            // Código automático, igual ao cadastro manual (0001, 0002, ...).
                            $pdo->prepare('UPDATE produto SET codigo = ? WHERE id_produto = ?')
                                ->execute([cod($idProduto), $idProduto]);
                        }

                        $pdo->prepare('UPDATE pedido_compra SET id_produto = ? WHERE id_pedido = ?')
                            ->execute([$idProduto, $id]);
                    }

                    $s = $pdo->prepare('INSERT INTO entrada (quantidade, data, id_pedido, id_produto) VALUES (?, CURRENT_DATE, ?, ?)');
                    $s->execute([$p['quantidade'], $id, $idProduto]);
                    $eid = $pdo->lastInsertId();

                    $pdo->prepare('UPDATE produto SET estoque = estoque + ? WHERE id_produto = ?')
                        ->execute([$p['quantidade'], $idProduto]);

                    $pdo->prepare(
                        'INSERT INTO movimentacao (quantidade, data, observacao, id_entrada)
                         VALUES (?, CURRENT_DATE, ?, ?)'
                    )->execute([$p['quantidade'], 'Recebimento do pedido #' . $id, $eid]);
                }

                $pdo->prepare('UPDATE pedido_compra SET status = ? WHERE id_pedido = ?')
                    ->execute([$novo, $id]);

                $pdo->commit();
                flash('Status do pedido atualizado.');
                redir('pedidos');
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $erro = $e->getMessage();
}
