<?php
declare(strict_types=1);

session_start();

/* ------------------------------------------------------------------
 * Configuração do banco de dados
 * ------------------------------------------------------------------ */

function envv(string $key, string $default): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

$host     = envv('DB_HOST', '127.0.0.1');
$port     = envv('DB_PORT', '3306');
$name     = envv('DB_NAME', 'estoque');
$user     = envv('DB_USER', 'root');
$password = getenv('DB_PASSWORD');

if ($password === false) {
    $password = '';
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // CORREÇÃO: o schema.sql cria as colunas em MAIÚSCULAS (ESTOQUE, QTD_MINIMA...),
        // e o MySQL devolve o nome exatamente como foi criado. Com CASE_LOWER todas as
        // chaves dos arrays chegam em minúsculas ($p['estoque'], $p['qtd_minima'] etc.).
        PDO::ATTR_CASE               => PDO::CASE_LOWER,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível conectar ao MySQL. Inicie o MySQL no XAMPP e confira o banco e as configurações em config.php.');
}

/* ------------------------------------------------------------------
 * Funções utilitárias
 * ------------------------------------------------------------------ */

/** Escapa texto para exibição segura em HTML. */
function h($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ---- Sessão e permissões ---- */

function usuario(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exige_login(): void
{
    if (!usuario()) {
        header('Location: ?pagina=login');
        exit;
    }
}

/** O perfil "administrador" tem acesso a tudo; os demais precisam estar na lista. */
function tem_perfil(array $perfis): bool
{
    $u = usuario();
    return $u && ($u['perfil'] === 'administrador' || in_array($u['perfil'], $perfis, true));
}

/* ---- Permissões por módulo (configuradas no cadastro de setor e de usuário) ---- */

/** Módulos cuja liberação é controlada pelas caixinhas de setor/usuário. */
const MODULOS = [
    'produtos'     => 'Produtos (cadastro e edição)',
    'fornecedores' => 'Fornecedores (cadastro e edição)',
    'entrada'      => 'Entrada de mercadoria',
    'pedidos'      => 'Gerenciar pedidos de compra (todos os setores)',
    'relatorios'   => 'Relatórios',
];

/** Status possíveis de um pedido de compra (valor gravado => rótulo exibido). */
const STATUS_PEDIDO = [
    'pendente'   => 'Pendente',
    'autorizado' => 'Autorizado',
    'recusado'   => 'Recusado',
    'concluido'  => 'Concluído',
];

/** Migração única por sessão: aprovado → autorizado, recebido → concluido. */
function migrar_status_pedido(PDO $pdo): void
{
    if (!empty($_SESSION['status_ok'])) {
        return;
    }
    $pdo->exec("UPDATE pedido_compra SET status = 'autorizado' WHERE status = 'aprovado'");
    $pdo->exec("UPDATE pedido_compra SET status = 'concluido' WHERE status = 'recebido'");
    $_SESSION['status_ok'] = true;
}

/** Pedido pode ser de produto ainda não cadastrado: guarda o nome digitado em pedido_compra. */
function garantir_coluna_pedido(PDO $pdo): void
{
    if (!empty($_SESSION['pedido_col_ok'])) {
        return;
    }
    try {
        $pdo->query('SELECT nome_produto FROM pedido_compra LIMIT 1');
    } catch (PDOException $e) {
        $pdo->exec('ALTER TABLE pedido_compra ADD COLUMN NOME_PRODUTO VARCHAR(100) NULL');
    }
    $_SESSION['pedido_col_ok'] = true;
}

/** Converte "a,b,c" em array, mantendo só módulos válidos. */
function modulos_lista(?string $csv): array
{
    $l = array_filter(array_map('trim', explode(',', (string) $csv)));
    return array_values(array_intersect($l, array_keys(MODULOS)));
}

/**
 * Migração única por sessão: perfil "ti" vira "administrador" e o perfil "compras"
 * (removido) vira "comum", mantendo o acesso aos módulos que ele tinha.
 */
function migrar_perfis(PDO $pdo): void
{
    if (!empty($_SESSION['perfis_ok'])) {
        return;
    }
    $todos = implode(',', array_keys(MODULOS));
    $pdo->exec("UPDATE usuario SET perfil = 'administrador' WHERE perfil = 'ti'");
    $pdo->prepare("UPDATE usuario SET modulos = COALESCE(modulos, ?), perfil = 'comum' WHERE perfil = 'compras'")->execute([$todos]);

    // Atualiza o perfil de quem já estava logado antes da migração.
    if (!empty($_SESSION['usuario']['id_usuario'])) {
        $q = $pdo->prepare('SELECT perfil FROM usuario WHERE id_usuario = ?');
        $q->execute([$_SESSION['usuario']['id_usuario']]);
        $p = $q->fetchColumn();
        if ($p !== false) {
            $_SESSION['usuario']['perfil'] = $p;
        }
    }
    $_SESSION['perfis_ok'] = true;
}

/** Cria as colunas MODULOS (setor/usuario) em bancos antigos e migra o acesso existente. */
function garantir_colunas_modulos(PDO $pdo): void
{
    if (!empty($_SESSION['modulos_ok'])) {
        return;
    }
    try {
        $pdo->query('SELECT modulos FROM setor LIMIT 1');
        $pdo->query('SELECT modulos FROM usuario LIMIT 1');
    } catch (PDOException $e) {
        $todos = implode(',', array_keys(MODULOS));
        foreach (['setor', 'usuario'] as $t) {
            try {
                $pdo->exec("ALTER TABLE {$t} ADD COLUMN MODULOS VARCHAR(255) NULL");
            } catch (PDOException $e2) {
                // coluna já existe
            }
        }
        // Migração única: mantém o acesso que o setor Compras / perfil compras já tinham.
        $pdo->prepare("UPDATE setor SET modulos = ? WHERE LOWER(nome_setor) = 'compras' AND modulos IS NULL")->execute([$todos]);
        $pdo->prepare("UPDATE usuario SET modulos = ? WHERE perfil = 'compras' AND modulos IS NULL")->execute([$todos]);
    }
    $_SESSION['modulos_ok'] = true;
}

/** Módulos do usuário logado = módulos do setor + módulos individuais (lidos do banco a cada requisição). */
function modulos_do_usuario(): array
{
    static $cache = null;
    global $pdo;
    $u = usuario();
    if (!$u) {
        return [];
    }
    if ($cache === null) {
        garantir_colunas_modulos($pdo);
        $q = $pdo->prepare(
            'SELECT s.modulos AS m_setor, u.modulos AS m_user
             FROM usuario u LEFT JOIN setor s ON s.id_setor = u.id_setor
             WHERE u.id_usuario = ?'
        );
        $q->execute([$u['id_usuario']]);
        $r = $q->fetch() ?: [];
        $cache = array_values(array_unique(array_merge(
            modulos_lista($r['m_setor'] ?? ''),
            modulos_lista($r['m_user'] ?? '')
        )));
    }
    return $cache;
}

/** Administrador acessa tudo; os demais precisam do módulo (via setor ou usuário) ou de um dos perfis informados. */
function tem_modulo(string $modulo, array $perfis = []): bool
{
    $u = usuario();
    if (!$u) {
        return false;
    }
    if ($u['perfil'] === 'administrador' || in_array($u['perfil'], $perfis, true)) {
        return true;
    }
    return in_array($modulo, modulos_do_usuario(), true);
}

function exige_modulo(string $modulo, array $perfis = []): void
{
    if (!tem_modulo($modulo, $perfis)) {
        http_response_code(403);
        exit('Você não tem permissão para acessar esta função.');
    }
}

function exige_perfil(array $perfis): void
{
    if (!tem_perfil($perfis)) {
        http_response_code(403);
        exit('Você não tem permissão para acessar esta função.');
    }
}

/* ---- Mensagens, CSRF e redirecionamento ---- */

function flash(string $msg, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validar_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token de segurança inválido. Atualize a página.');
    }
}

function redir(string $pagina): never
{
    header('Location: ?pagina=' . urlencode($pagina));
    exit;
}

/* ---- Leitura e validação de campos do formulário ---- */

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function inteiro(string $key, int $min = 0): int
{
    $v = filter_var(post($key), FILTER_VALIDATE_INT);
    if ($v === false || $v < $min) {
        throw new RuntimeException("O campo {$key} deve ser um número inteiro maior ou igual a {$min}.");
    }
    return $v;
}

function num_decimal(string $key): float
{
    $v = str_replace(',', '.', (string) post($key, '0'));
    if (!is_numeric($v) || (float) $v < 0) {
        throw new RuntimeException("O campo {$key} deve ser um número válido.");
    }
    return (float) $v;
}

function obrigatorio(string $key): string
{
    $v = trim((string) post($key, ''));
    if ($v === '') {
        throw new RuntimeException("O campo {$key} é obrigatório.");
    }
    return $v;
}

/* Garante colunas/perfis em bancos criados antes desta versão.
 * Em banco ainda vazio (antes do setup.php) as tabelas não existem: ignora o erro. */
try {
    garantir_colunas_modulos($pdo);
    migrar_perfis($pdo);
    migrar_status_pedido($pdo);
    garantir_coluna_pedido($pdo);
} catch (PDOException $e) {
    // tabelas ainda não criadas
}
