<?php

namespace Sello;

class QrCode
{
    /** @var string */
    public $text;
    /** @var int[][] */
    public $modules;
    /** @var int */
    public $version;
    /** @var string */
    public $mode;
    /** @var string */
    public $errorCorrection;
    /** @var int */
    public $mask;
    /** @var array */
    private $options;

    public function __construct($text, array $encoded, array $options)
    {
        $this->text = $text;
        $this->modules = $encoded['modules'];
        $this->version = $encoded['version'];
        $this->mode = $encoded['mode'];
        $this->errorCorrection = $encoded['errorCorrection'];
        $this->mask = $encoded['mask'];
        $this->options = $options;
    }

    public static function from($text, array $options = [])
    {
        if ($text === null) {
            throw new \InvalidArgumentException('Pasa un texto, URL u otro contenido para el QR.');
        }
        if (is_array($text) || is_object($text)) {
            $value = json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $value = (string) $text;
        }
        $opts = Sello::normalizeOptions($options);
        return new self($value, Encoder::encode($value, $opts), $opts);
    }

    public function matrix()
    {
        $copy = [];
        foreach ($this->modules as $row) {
            $copy[] = $row;
        }
        return $copy;
    }

    public function svg(array $overrides = [])
    {
        return Drawer::markup($this->modules, $this->merged($overrides));
    }

    public function text($dark = '██', $light = '  ')
    {
        $m = $this->modules;
        $margin = $this->options['margin'];
        $n = count($m);
        $dim = $n + $margin * 2;
        $lines = [];
        for ($r = 0; $r < $dim; $r++) {
            $line = '';
            for ($c = 0; $c < $dim; $c++) {
                $rr = $r - $margin;
                $cc = $c - $margin;
                $bit = $rr >= 0 && $cc >= 0 && $rr < $n && $cc < $n && $m[$rr][$cc];
                $line .= $bit ? $dark : $light;
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    public function __toString()
    {
        return $this->text();
    }

    public function png(array $overrides = [])
    {
        return Drawer::bitmap($this->modules, $this->merged($overrides));
    }

    public function dataUri(array $overrides = [])
    {
        return 'data:image/png;base64,' . base64_encode($this->png($overrides));
    }

    public function base64(array $overrides = [])
    {
        return base64_encode($this->png($overrides));
    }

    public function save($path, array $overrides = [])
    {
        $lower = strtolower($path);
        $data = substr($lower, -4) === '.svg' ? $this->svg($overrides) : $this->png($overrides);
        if (file_put_contents($path, $data) === false) {
            throw new \RuntimeException('No se pudo guardar ' . $path);
        }
        return $path;
    }

    public function img(array $overrides = [])
    {
        $opts = $this->merged($overrides);
        return '<img alt="QR" width="' . (int) $opts['size'] . '" height="' . (int) $opts['size'] . '" src="' . htmlspecialchars($this->dataUri($overrides), ENT_QUOTES, 'UTF-8') . '">';
    }

    private function merged(array $overrides)
    {
        return Sello::normalizeOptions(array_merge($this->options, $overrides));
    }
}
