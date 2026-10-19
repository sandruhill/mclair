<?php
declare(strict_types=1);

/**
 * Leitor mínimo do formato MaxMind DB (.mmdb), em PHP puro, para a base "IP to City Lite" da DB-IP (CC BY 4.0).
 * Copiado do painel do Sacred Lake Titikaka (código próprio). Usado por mensagens-lib.php para cidade e estado do lead.
 * Especificacao: https://maxmind.github.io/MaxMind-DB/ . So o que o painel precisa: abrir o ficheiro, ler os
 * metadados, percorrer a arvore binaria por IPv4 ou IPv6 e descodificar o registo (mapas, listas, textos, numeros).
 * O ficheiro nunca e carregado inteiro em memoria (127 MB): cada consulta faz umas dezenas de leituras pequenas.
 * O IP nunca sai do servidor: a consulta e local.
 */
final class Mmdb {
    private const MARKER = "\xab\xcd\xefMaxMind.com";
    /** @var resource */
    private $fp;
    private int $nodeCount;
    private int $recordSize;
    private int $nodeBytes;
    private int $ipVersion;
    private int $dataStart;
    private int $ipv4Start = -1;
    public array $meta;

    public static function open(string $path): ?self {
        if (!is_file($path) || !is_readable($path)) { return null; }
        $fp = @fopen($path, 'rb');
        if (!$fp) { return null; }
        try { return new self($fp); }
        catch (Throwable $e) { fclose($fp); error_log('[geo] mmdb invalido: ' . $e->getMessage()); return null; }
    }

    /** @param resource $fp */
    private function __construct($fp) {
        $this->fp = $fp;
        $size = fstat($fp)['size'] ?? 0;
        if ($size < 64) { throw new RuntimeException('ficheiro vazio'); }
        $tail = min($size, 128 * 1024);
        fseek($fp, $size - $tail);
        $buf = (string)fread($fp, $tail);
        $pos = strrpos($buf, self::MARKER);
        if ($pos === false) { throw new RuntimeException('marcador de metadados ausente'); }
        $metaStart = $size - $tail + $pos + strlen(self::MARKER);
        $this->dataStart = $metaStart;   // os metadados sao um registo cujo "inicio da seccao" e o proprio inicio dos metadados
        [$meta] = $this->decode($metaStart);
        if (!is_array($meta) || empty($meta['node_count']) || empty($meta['record_size'])) { throw new RuntimeException('metadados incompletos'); }
        $this->meta = $meta;
        $this->nodeCount = (int)$meta['node_count'];
        $this->recordSize = (int)$meta['record_size'];
        if (!in_array($this->recordSize, [24, 28, 32], true)) { throw new RuntimeException('record_size ' . $this->recordSize); }
        $this->nodeBytes = intdiv($this->recordSize * 2, 8);
        $this->ipVersion = (int)($meta['ip_version'] ?? 6);
        $this->dataStart = $this->nodeCount * $this->nodeBytes + 16;
    }

    public function __destruct() { if (is_resource($this->fp)) { fclose($this->fp); } }

    /** Registo do IP (mapa descodificado) ou null se nao consta ou o IP e invalido. */
    public function lookup(string $ip): ?array {
        $bin = @inet_pton($ip);
        if ($bin === false) { return null; }
        $bits = strlen($bin) * 8;
        $node = 0;
        if ($bits === 32) {
            if ($this->ipVersion === 4) { /* arvore so IPv4: comeca em 0 */ }
            else { $node = $this->ipv4Start(); if ($node < 0) { return null; } }
        } elseif ($this->ipVersion === 4) { return null; }
        for ($i = 0; $i < $bits && $node < $this->nodeCount; $i++) {
            $bit = (ord($bin[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->record($node, $bit);
        }
        if ($node === $this->nodeCount) { return null; }
        if ($node < $this->nodeCount) { return null; }
        $offset = $node - $this->nodeCount - 16 + $this->dataStart;
        [$v] = $this->decode($offset);
        return is_array($v) ? $v : null;
    }

    /** No onde comeca a sub-arvore IPv4 numa base IPv6 (96 bits a zero a partir da raiz). */
    private function ipv4Start(): int {
        if ($this->ipv4Start >= 0) { return $this->ipv4Start; }
        $node = 0;
        for ($i = 0; $i < 96 && $node < $this->nodeCount; $i++) { $node = $this->record($node, 0); }
        return $this->ipv4Start = $node;
    }

    private function record(int $node, int $side): int {
        fseek($this->fp, $node * $this->nodeBytes);
        $b = (string)fread($this->fp, $this->nodeBytes);
        if (strlen($b) < $this->nodeBytes) { throw new RuntimeException('leitura curta no no ' . $node); }
        switch ($this->recordSize) {
            case 24:
                $o = $side === 0 ? 0 : 3;
                return (ord($b[$o]) << 16) | (ord($b[$o + 1]) << 8) | ord($b[$o + 2]);
            case 28:
                if ($side === 0) { return ((ord($b[3]) & 0xF0) << 20) | (ord($b[0]) << 16) | (ord($b[1]) << 8) | ord($b[2]); }
                return ((ord($b[3]) & 0x0F) << 24) | (ord($b[4]) << 16) | (ord($b[5]) << 8) | ord($b[6]);
            default:
                $o = $side === 0 ? 0 : 4;
                return (ord($b[$o]) << 24) | (ord($b[$o + 1]) << 16) | (ord($b[$o + 2]) << 8) | ord($b[$o + 3]);
        }
    }

    private function bytes(int $offset, int $n): string {
        if ($n <= 0) { return ''; }
        fseek($this->fp, $offset);
        $b = (string)fread($this->fp, $n);
        if (strlen($b) < $n) { throw new RuntimeException('leitura curta em ' . $offset); }
        return $b;
    }

    /** Descodifica o campo em $offset. Devolve [valor, offset seguinte]. */
    private function decode(int $offset, int $depth = 0): array {
        if ($depth > 64) { throw new RuntimeException('registo demasiado profundo'); }
        $ctrl = ord($this->bytes($offset, 1)); $offset++;
        $type = $ctrl >> 5;
        if ($type === 1) {   // ponteiro para outro ponto da seccao de dados
            $ss = ($ctrl >> 3) & 3; $vvv = $ctrl & 7;
            if ($ss === 0) { $p = ($vvv << 8) | ord($this->bytes($offset, 1)); $offset += 1; }
            elseif ($ss === 1) { $b = $this->bytes($offset, 2); $p = (($vvv << 16) | (ord($b[0]) << 8) | ord($b[1])) + 2048; $offset += 2; }
            elseif ($ss === 2) { $b = $this->bytes($offset, 3); $p = (($vvv << 24) | (ord($b[0]) << 16) | (ord($b[1]) << 8) | ord($b[2])) + 526336; $offset += 3; }
            else { $b = $this->bytes($offset, 4); $p = (ord($b[0]) << 24) | (ord($b[1]) << 16) | (ord($b[2]) << 8) | ord($b[3]); $offset += 4; }
            [$v] = $this->decode($this->dataStart + $p, $depth + 1);
            return [$v, $offset];
        }
        if ($type === 0) { $type = 7 + ord($this->bytes($offset, 1)); $offset++; }
        $size = $ctrl & 0x1f;
        if ($size === 29) { $size = 29 + ord($this->bytes($offset, 1)); $offset++; }
        elseif ($size === 30) { $b = $this->bytes($offset, 2); $size = 285 + ((ord($b[0]) << 8) | ord($b[1])); $offset += 2; }
        elseif ($size === 31) { $b = $this->bytes($offset, 3); $size = 65821 + ((ord($b[0]) << 16) | (ord($b[1]) << 8) | ord($b[2])); $offset += 3; }
        switch ($type) {
            case 2:   // utf8
            case 4:   // bytes
                return [$this->bytes($offset, $size), $offset + $size];
            case 3:   // double
                $v = unpack('E', $this->bytes($offset, 8))[1]; return [$v, $offset + 8];
            case 15:  // float
                $v = unpack('G', $this->bytes($offset, 4))[1]; return [$v, $offset + 4];
            case 5: case 6: case 9: case 10: {   // uint16/32/64/128 (acima de 63 bits devolve-se como string hex)
                $b = $this->bytes($offset, $size);
                if ($size > 7) { return [bin2hex($b), $offset + $size]; }
                $v = 0; for ($i = 0; $i < $size; $i++) { $v = ($v << 8) | ord($b[$i]); }
                return [$v, $offset + $size];
            }
            case 8: {   // int32
                $b = $this->bytes($offset, $size); $v = 0;
                for ($i = 0; $i < $size; $i++) { $v = ($v << 8) | ord($b[$i]); }
                if ($size === 4 && $v >= 0x80000000) { $v -= 0x100000000; }
                return [$v, $offset + $size];
            }
            case 7: {   // mapa
                $m = [];
                for ($i = 0; $i < $size; $i++) {
                    [$k, $offset] = $this->decode($offset, $depth + 1);
                    [$v, $offset] = $this->decode($offset, $depth + 1);
                    $m[is_scalar($k) ? (string)$k : $i] = $v;
                }
                return [$m, $offset];
            }
            case 11: {   // lista
                $a = [];
                for ($i = 0; $i < $size; $i++) { [$v, $offset] = $this->decode($offset, $depth + 1); $a[] = $v; }
                return [$a, $offset];
            }
            case 14: return [$size === 1, $offset];   // boolean
            case 12: case 13: return [null, $offset]; // cache container / fim
            default: throw new RuntimeException('tipo ' . $type);
        }
    }
}
