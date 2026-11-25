# Gestão de Estoque — PHP + HTML + CSS + MySQL (XAMPP)

Aplicação web convertida para PHP 8+, HTML, CSS e JavaScript, utilizando MySQL/MariaDB fornecido pelo XAMPP.

## Requisitos

- XAMPP com Apache e MySQL/MariaDB.
- PHP 8+ com as extensões `pdo` e `pdo_mysql` habilitadas.
- Navegador atualizado.

## Instalação no XAMPP

1. Extraia a pasta `estoque_pat_php` dentro de `C:\xampp\htdocs\`.
2. Abra o Painel de Controle do XAMPP e inicie **Apache** e **MySQL**.
3. Acesse `http://localhost/phpmyadmin`.
4. Clique em **Novo**, crie um banco chamado `estoque` e selecione a codificação `utf8mb4_unicode_ci`.
5. Selecione o banco `estoque`, abra a aba **Importar** e importe `C:\xampp\htdocs\estoque_pat_php\database\schema.sql`.
6. Confira a conexão no `config.php`. A configuração padrão do XAMPP é host `127.0.0.1`, porta `3306`, usuário `root` e senha vazia. Se você definiu uma senha para o usuário MySQL, informe-a na variável `$password` de `config.php` ou configure `DB_PASSWORD` no ambiente.
7. O próprio `schema.sql` já cadastra os setores iniciais e o administrador. Opcionalmente, acesse `http://localhost/estoque_pat_php/public/setup.php` para conferir/completar esses dados (é seguro rodar mais de uma vez).
8. Entre em `http://localhost/estoque_pat_php/public/` com login `admin` e senha `admin123`.
9. Altere a senha do administrador e, após a configuração, exclua `public/setup.php`.

## Criar outro administrador

No terminal, dentro da pasta do projeto:

```text
C:\xampp\php\php.exe criar_admin.php "Seu Nome" seu_login sua_senha
```

Se o login já existir, ele é promovido a administrador (perfil Administrador) e a senha é atualizada. Apague `criar_admin.php` depois de usar.

## Banco de dados

O script `database/schema.sql` cria as tabelas `SETOR`, `FORNECEDOR`, `USUARIO`, `PRODUTO`, `PEDIDO_COMPRA`, `ENTRADA`, `SAIDA` e `MOVIMENTACAO`, com chaves estrangeiras e mecanismo InnoDB.

## Estrutura

```text
estoque_pat_php/
├── config.php
├── database/schema.sql
├── public/
│   ├── assets/style.css
│   ├── index.php
│   └── setup.php
└── README.md
```

## Observações

- A aplicação utiliza PDO com consultas preparadas e `pdo_mysql`.
- O XAMPP normalmente instala MariaDB como substituto compatível com MySQL; o projeto usa recursos compatíveis com ambos.
- O usuário `root` sem senha é uma configuração comum de instalação local do XAMPP, mas não deve ser mantida em produção.
- Antes de publicar o sistema na internet, configure credenciais fortes, HTTPS, permissões adequadas e remova o instalador `setup.php`.
- Esta é uma conversão inicial; teste os fluxos do sistema no seu ambiente antes de utilizar dados reais.
