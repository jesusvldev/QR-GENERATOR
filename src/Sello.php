<?php

namespace Sello;

/**
 * Sello — librería QR.
 *
 * @author  Jesus <jesusvldev@gmail.com>
 * @license MIT
 *
 *   use Sello\Sello;
 *   $qr = Sello::create('https://ejemplo.com');
 *   echo $qr->svg();
 *   $qr->save('codigo.png');
 */
class Sello
{
    public const VERSION = '1.0.0';

    public static function create($text, array $options = [])
    {
        return QrCode::from($text, $options);
    }

    public static function crear($text, array $options = [])
    {
        return self::create($text, $options);
    }

    public static function url($href, array $options = [])
    {
        if ($href === null || $href === '') {
            throw new \InvalidArgumentException('Pasa una URL.');
        }
        $value = preg_match('/^(https?:|mailto:|sms:|tel:|WIFI:)/i', $href) ? $href : 'https://' . $href;
        return self::create($value, $options);
    }

    public static function wifi(array $config)
    {
        if (empty($config['ssid'])) {
            throw new \InvalidArgumentException('WiFi necesita un ssid.');
        }
        $type = $config['type'] ?? (!empty($config['password']) ? 'WPA' : 'nopass');
        $hidden = !empty($config['hidden']) ? 'true' : 'false';
        $body = 'WIFI:T:' . $type . ';S:' . self::wifiEscape($config['ssid']) . ';';
        $pass = $type === 'nopass' ? '' : 'P:' . self::wifiEscape($config['password'] ?? '') . ';';
        return self::create($body . $pass . 'H:' . $hidden . ';;', $config);
    }

    public static function vcard(array $contact)
    {
        if (empty($contact['name'])) {
            throw new \InvalidArgumentException('El contacto necesita un name.');
        }
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . $contact['name'],
            'FN:' . $contact['name'],
        ];
        if (!empty($contact['org'])) {
            $lines[] = 'ORG:' . $contact['org'];
        }
        if (!empty($contact['phone'])) {
            $lines[] = 'TEL;TYPE=CELL:' . $contact['phone'];
        }
        if (!empty($contact['email'])) {
            $lines[] = 'EMAIL:' . $contact['email'];
        }
        if (!empty($contact['url'])) {
            $lines[] = 'URL:' . $contact['url'];
        }
        $lines[] = 'END:VCARD';
        return self::create(implode("\n", $lines), $contact);
    }

    public static function email($address, array $extra = [])
    {
        if ($address === null || $address === '') {
            throw new \InvalidArgumentException('Pasa un correo.');
        }
        $url = 'mailto:' . $address;
        $q = [];
        if (!empty($extra['subject'])) {
            $q[] = 'subject=' . rawurlencode($extra['subject']);
        }
        if (!empty($extra['body'])) {
            $q[] = 'body=' . rawurlencode($extra['body']);
        }
        if ($q) {
            $url .= '?' . implode('&', $q);
        }
        return self::create($url, $extra);
    }

    public static function sms($number, array $extra = [])
    {
        if ($number === null || $number === '') {
            throw new \InvalidArgumentException('Pasa un número de teléfono.');
        }
        $url = 'sms:' . $number;
        if (!empty($extra['message'])) {
            $url .= '?body=' . rawurlencode($extra['message']);
        }
        return self::create($url, $extra);
    }

    public static function tel($number, array $options = [])
    {
        if ($number === null || trim((string) $number) === '') {
            throw new \InvalidArgumentException('Pasa un número de teléfono.');
        }
        return self::create('tel:' . preg_replace('/[\s()-]/', '', (string) $number), $options);
    }

    public static function geo($lat, $lng = null, array $options = [])
    {
        if (is_array($lat)) {
            $options = $lat;
            $lat = $options['lat'] ?? null;
            $lng = $options['lng'] ?? null;
        }
        if ($lat === null || $lng === null) {
            throw new \InvalidArgumentException('Pasa lat y lng.');
        }
        return self::create('geo:' . $lat . ',' . $lng, $options);
    }

    public static function toDataUri($value, $mime = null)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new \InvalidArgumentException('Pasa un base64 o un data URI.');
        }
        if (stripos($raw, 'data:') === 0) {
            return $raw;
        }
        if ($mime === null) {
            if (strpos($raw, '/9j/') === 0) {
                $mime = 'image/jpeg';
            } elseif (strpos($raw, 'iVBOR') === 0) {
                $mime = 'image/png';
            } elseif (strpos($raw, 'R0lGOD') === 0) {
                $mime = 'image/gif';
            } elseif (strpos($raw, 'PHN2Zy') === 0 || strpos($raw, 'PD94') === 0) {
                $mime = 'image/svg+xml';
            } else {
                $mime = 'image/png';
            }
        }
        return 'data:' . $mime . ';base64,' . preg_replace('/\s+/', '', $raw);
    }

    public static function toBase64($value)
    {
        $raw = (string) $value;
        $mark = strpos($raw, 'base64,');
        return $mark === false ? preg_replace('/\s+/', '', $raw) : substr($raw, $mark + 7);
    }

    public static function img($source, array $overrides = [])
    {
        if ($source instanceof QrCode) {
            return $source->img($overrides);
        }
        $uri = self::toDataUri($source, $overrides['mime'] ?? null);
        return '<img alt="QR" src="' . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function pintar($source, array $overrides = [])
    {
        return self::img($source, $overrides);
    }

    public static function normalizeOptions(array $options)
    {
        $style = $options['moduleStyle'] ?? 'smooth';
        $allowed = ['square' => 1, 'rounded' => 1, 'dots' => 1, 'smooth' => 1];
        if (!isset($allowed[$style])) {
            throw new \InvalidArgumentException('Estilo inválido. Usa square, rounded, dots o smooth.');
        }
        return [
            'errorCorrection' => $options['errorCorrection'] ?? 'M',
            'version' => $options['version'] ?? null,
            'mask' => array_key_exists('mask', $options) ? $options['mask'] : null,
            'mode' => $options['mode'] ?? null,
            'size' => $options['size'] ?? 320,
            'margin' => array_key_exists('margin', $options) ? $options['margin'] : 2,
            'color' => $options['color'] ?? '#161412',
            'background' => $options['background'] ?? '#f6f1e8',
            'moduleStyle' => $style,
        ];
    }

    private static function wifiEscape($value)
    {
        return preg_replace('/([\\\\;,:\"])/', '\\\\$1', (string) $value);
    }
}
