<?php
declare(strict_types=1);

/**
 * Gerador de PDF simples, em PHP puro (sem Composer e sem bibliotecas externas).
 * Usa as fontes padrão Helvetica / Helvetica-Bold (já embutidas em qualquer leitor de PDF),
 * com acentuação em português (codificação WinAnsi / Windows-1252).
 *
 * Recursos: cabeçalho e rodapé com "Página X de Y", caixas de resumo e tabelas com
 * quebra de linha automática, cabeçalho repetido a cada página e linhas zebradas.
 */
class PdfRelatorio
{
    private const LARGURA = 595.28;   // A4 retrato, em pontos
    private const ALTURA  = 841.89;
    private const MARGEM  = 40.0;

    /** Larguras (em 1/1000 do corpo) dos caracteres 32..255 da Helvetica. */
    private const W_REGULAR = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 278,
        278, 278, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 278, 611, 278,
        278, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 278, 500, 667,
        278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 278, 737, 333,
        400, 584, 278, 278, 333, 556, 537, 278, 333, 278, 365, 556, 834, 834, 834, 611,
        667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500,
    ];

    /** Larguras da Helvetica-Bold. */
    private const W_NEGRITO = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 278,
        278, 278, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 278, 611, 278,
        278, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 278, 500, 667,
        278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 278, 737, 333,
        400, 584, 278, 278, 333, 611, 556, 278, 333, 278, 365, 556, 834, 834, 834, 611,
        722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278,
        611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556,
    ];

    private array $paginas = [];   // conteúdo (stream) de cada página
    private string $buf = '';      // conteúdo da página atual
    private float $y = 0.0;
    private string $titulo;
    private string $subtitulo;
    private string $rodapeEsq;

    public function __construct(string $titulo, string $subtitulo = '', string $rodapeEsq = '')
    {
        $this->titulo    = $titulo;
        $this->subtitulo = $subtitulo;
        $this->rodapeEsq = $rodapeEsq;
        $this->novaPagina();
    }

    /* ------------------------------ Texto ------------------------------ */

    /** Converte UTF-8 para Windows-1252 (o que o PDF espera). */
    private static function cp1252(string $s): string
    {
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    private static function escapar(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /** Largura do texto (UTF-8) em pontos. */
    public function largura(string $texto, float $tam, bool $negrito = false): float
    {
        $tab = $negrito ? self::W_NEGRITO : self::W_REGULAR;
        $w = 0;
        $t = self::cp1252($texto);
        for ($i = 0, $n = strlen($t); $i < $n; $i++) {
            $c = ord($t[$i]);
            $w += ($c >= 32) ? ($tab[$c - 32] ?? 556) : 0;
        }
        return $w * $tam / 1000;
    }

    /** Quebra o texto em linhas que caibam em $larg pontos. */
    public function quebrar(string $texto, float $larg, float $tam, bool $negrito = false): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }
        $linhas = [];
        $atual  = '';
        foreach (explode(' ', $texto) as $palavra) {
            // Palavra maior que a coluna: corta em pedaços.
            while ($this->largura($palavra, $tam, $negrito) > $larg && mb_strlen($palavra) > 1) {
                $corte = mb_strlen($palavra);
                while ($corte > 1 && $this->largura(mb_substr($palavra, 0, $corte), $tam, $negrito) > $larg) {
                    $corte--;
                }
                if ($atual !== '') {
                    $linhas[] = $atual;
                    $atual = '';
                }
                $linhas[] = mb_substr($palavra, 0, $corte);
                $palavra  = mb_substr($palavra, $corte);
            }
            $tentativa = ($atual === '') ? $palavra : $atual . ' ' . $palavra;
            if ($this->largura($tentativa, $tam, $negrito) <= $larg) {
                $atual = $tentativa;
            } else {
                $linhas[] = $atual;
                $atual = $palavra;
            }
        }
        if ($atual !== '') {
            $linhas[] = $atual;
        }
        return $linhas ?: [''];
    }

    private function cor(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    /** Escreve um texto com a linha de base em $yBase (medido a partir do topo da página). */
    private function texto(float $x, float $yBase, string $txt, float $tam, bool $negrito = false, array $rgb = [32, 41, 56]): void
    {
        $fonte = $negrito ? 'F2' : 'F1';
        $this->buf .= sprintf(
            "BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n",
            $fonte, $tam, $this->cor($rgb), $x, self::ALTURA - $yBase, self::escapar(self::cp1252($txt))
        );
    }

    private function retangulo(float $x, float $yTopo, float $w, float $h, array $rgb): void
    {
        $this->buf .= sprintf(
            "%s rg %.2F %.2F %.2F %.2F re f\n",
            $this->cor($rgb), $x, self::ALTURA - $yTopo - $h, $w, $h
        );
    }

    private function linha(float $x1, float $y, float $x2, array $rgb, float $esp = 0.5): void
    {
        $this->buf .= sprintf(
            "%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $this->cor($rgb), $esp, $x1, self::ALTURA - $y, $x2, self::ALTURA - $y
        );
    }

    /* ----------------------------- Páginas ----------------------------- */

    private function larguraUtil(): float
    {
        return self::LARGURA - 2 * self::MARGEM;
    }

    private function novaPagina(): void
    {
        if ($this->buf !== '') {
            $this->paginas[] = $this->buf;
        }
        $this->buf = '';
        $m = self::MARGEM;

        // Faixa de cabeçalho
        $this->retangulo(0, 0, self::LARGURA, 64, [23, 36, 58]);
        $this->texto($m, 30, 'PAT · Gestão de Estoque', 9, false, [158, 172, 194]);
        $this->texto($m, 50, $this->titulo, 17, true, [255, 255, 255]);
        $this->y = 82;

        if ($this->subtitulo !== '') {
            $this->texto($m, $this->y, $this->subtitulo, 9, false, [100, 112, 130]);
            $this->y += 16;
        }
    }

    private function garantirEspaco(float $altura): bool
    {
        if ($this->y + $altura > self::ALTURA - 50) {
            $this->novaPagina();
            return true;
        }
        return false;
    }

    /* ------------------------- Blocos do relatório ------------------------- */

    /** Linha de caixas de resumo: [['Rótulo', 'Valor'], ...]. */
    public function resumo(array $itens): void
    {
        $n = count($itens);
        if ($n === 0) {
            return;
        }
        $this->garantirEspaco(52);
        $gap = 10.0;
        $w   = ($this->larguraUtil() - $gap * ($n - 1)) / $n;
        $x   = self::MARGEM;
        foreach ($itens as [$rotulo, $valor]) {
            $this->retangulo($x, $this->y, $w, 44, [243, 246, 251]);
            $this->texto($x + 10, $this->y + 16, (string) $rotulo, 8, false, [120, 134, 154]);
            $this->texto($x + 10, $this->y + 36, (string) $valor, 15, true, [32, 41, 56]);
            $x += $w + $gap;
        }
        $this->y += 44 + 16;
    }

    public function subtituloSecao(string $txt): void
    {
        $this->garantirEspaco(30);
        $this->texto(self::MARGEM, $this->y + 10, $txt, 11, true, [32, 41, 56]);
        $this->y += 20;
    }

    /**
     * Tabela.
     * $colunas: [['Título', largura relativa, 'L'|'R'|'C'], ...]
     * $linhas:  array de arrays de textos; para destacar uma linha em vermelho, use a chave 'destaque' => true.
     */
    public function tabela(array $colunas, array $linhas): void
    {
        $tam   = 8.5;
        $padX  = 5.0;
        $padY  = 5.0;
        $entre = 11.0; // altura de cada linha de texto
        $soma  = array_sum(array_column($colunas, 1));
        $larg  = [];
        foreach ($colunas as $i => $c) {
            $larg[$i] = $this->larguraUtil() * $c[1] / $soma;
        }

        $cabecalho = function () use ($colunas, $larg, $tam, $padX): void {
            $alt = 22;
            $this->retangulo(self::MARGEM, $this->y, $this->larguraUtil(), $alt, [41, 62, 93]);
            $x = self::MARGEM;
            foreach ($colunas as $i => $c) {
                $t = mb_strtoupper($c[0], 'UTF-8');
                $tx = $this->alinhar($x, $larg[$i], $t, 7.5, true, $c[2] ?? 'L', $padX);
                $this->texto($tx, $this->y + 14.5, $t, 7.5, true, [255, 255, 255]);
                $x += $larg[$i];
            }
            $this->y += $alt;
        };

        $this->garantirEspaco(22 + 30);
        $cabecalho();

        if (!$linhas) {
            $this->texto(self::MARGEM + $padX, $this->y + 18, 'Nenhum registro encontrado.', 9, false, [120, 134, 154]);
            $this->y += 30;
            return;
        }

        foreach ($linhas as $k => $linha) {
            $destaque = !empty($linha['destaque']);
            // Quebra cada célula e descobre a altura da linha.
            $cel = [];
            $max = 1;
            foreach ($colunas as $i => $c) {
                $cel[$i] = $this->quebrar((string) ($linha[$i] ?? ''), $larg[$i] - 2 * $padX, $tam);
                $max = max($max, count($cel[$i]));
            }
            $alt = $max * $entre + 2 * $padY;

            if ($this->garantirEspaco($alt)) {
                $cabecalho();
            }

            if ($k % 2 === 1) {
                $this->retangulo(self::MARGEM, $this->y, $this->larguraUtil(), $alt, [247, 249, 252]);
            }
            $x = self::MARGEM;
            foreach ($colunas as $i => $c) {
                foreach ($cel[$i] as $n => $txt) {
                    $tx = $this->alinhar($x, $larg[$i], $txt, $tam, false, $c[2] ?? 'L', $padX);
                    $this->texto(
                        $tx, $this->y + $padY + ($n + 1) * $entre - 2.5, $txt, $tam, $destaque,
                        $destaque ? [190, 60, 45] : [32, 41, 56]
                    );
                }
                $x += $larg[$i];
            }
            $this->y += $alt;
            $this->linha(self::MARGEM, $this->y, self::MARGEM + $this->larguraUtil(), [232, 236, 242]);
        }
        $this->y += 8;
    }

    private function alinhar(float $x, float $larguraCol, string $txt, float $tam, bool $neg, string $al, float $pad): float
    {
        $w = $this->largura($txt, $tam, $neg);
        return match ($al) {
            'R'     => $x + $larguraCol - $pad - $w,
            'C'     => $x + ($larguraCol - $w) / 2,
            default => $x + $pad,
        };
    }

    /* ------------------------------ Saída ------------------------------ */

    /** Monta o arquivo PDF e devolve seus bytes. */
    public function gerar(): string
    {
        $paginas = $this->paginas;
        $paginas[] = $this->buf;
        $total = count($paginas);

        // Rodapé em todas as páginas (agora que o total é conhecido).
        $cinza = $this->cor([130, 142, 160]);
        foreach ($paginas as $i => $conteudo) {
            $pag = 'Página ' . ($i + 1) . ' de ' . $total;
            $rod = sprintf("%s RG 0.5 w %.2F 36 m %.2F 36 l S\n", $this->cor([220, 226, 235]), self::MARGEM, self::LARGURA - self::MARGEM);
            $rod .= sprintf("BT /F1 8 Tf %s rg %.2F 24 Td (%s) Tj ET\n", $cinza, self::MARGEM, self::escapar(self::cp1252($this->rodapeEsq)));
            $rod .= sprintf("BT /F1 8 Tf %s rg %.2F 24 Td (%s) Tj ET\n", $cinza, self::LARGURA - self::MARGEM - $this->largura($pag, 8), self::escapar(self::cp1252($pag)));
            $paginas[$i] = $conteudo . $rod;
        }

        // Objetos: 1 catálogo, 2 páginas, 3 fonte regular, 4 fonte negrito, depois (página, conteúdo) por página.
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $total; $i++) {
            $kids[] = (5 + 2 * $i) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $comprime = function_exists('gzcompress');
        foreach ($paginas as $i => $conteudo) {
            $np = 5 + 2 * $i;
            $objs[$np] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::LARGURA, self::ALTURA, $np + 1
            );
            $dados = $comprime ? gzcompress($conteudo, 6) : $conteudo;
            $objs[$np + 1] = '<< /Length ' . strlen($dados) . ($comprime ? ' /Filter /FlateDecode' : '') . " >>\nstream\n" . $dados . "\nendstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $pos = [];
        foreach ($objs as $n => $corpo) {
            $pos[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $corpo . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($objs as $n => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $pos[$n]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}
