# Sello

Librería PHP para códigos QR. Sin dependencias. Se instala con Composer, como PHPMailer.

```bash
composer require sello/qr
```

```php
use Sello\Sello;

$qr = Sello::create('https://tu-web.com');

echo $qr->svg();
$qr->save('codigo.png');
$qr->save('codigo.svg');
echo $qr->img();
```

Si aún no está en Packagist, en el `composer.json` de tu proyecto:

```json
{
  "repositories": [
    { "type": "path", "url": "../QR GENERATOR" }
  ],
  "require": {
    "sello/qr": "*"
  }
}
```

## Uso

```php
Sello::create('lo que sea');
Sello::create(['pedido' => 42, 'mesa' => 'A3']);
Sello::url('tu-web.com');
Sello::tel('+34600000000');
Sello::sms('600000000', ['message' => 'Hola']);
Sello::email('ana@correo.com', ['subject' => 'Hola']);
Sello::geo(40.4168, -3.7038);
Sello::wifi(['ssid' => 'Casa', 'password' => 'secreto']);
Sello::vcard(['name' => 'Ana Pérez', 'phone' => '+34600000000']);

$base64 = $qr->base64();
echo Sello::img($base64);
```

## Salida

| Método | Qué devuelve |
|---|---|
| `svg()` | SVG |
| `png()` | PNG binario |
| `base64()` | base64 del PNG |
| `dataUri()` | `data:image/png;base64,...` |
| `img()` | etiqueta `<img>` |
| `save('qr.png')` | guarda PNG o SVG |
| `text()` | QR en texto |

## Opciones

| Opción | Default | Qué hace |
|---|---|---|
| `errorCorrection` | `'M'` | `L` `M` `Q` `H` |
| `moduleStyle` | `'smooth'` | `smooth` `square` `rounded` `dots` |
| `color` | `'#161412'` | tinta |
| `background` | `'#f6f1e8'` | papel |
| `size` | `320` | píxeles de salida |
| `margin` | `2` | zona silenciosa |
| `version` | auto | 1–40 si quieres forzarla |

## Publicar (para que cualquiera haga `composer require`)

1. Crea un repo **público** en GitHub (por ejemplo `sello-qr`).
2. Sube este proyecto:
   ```bash
   git init
   git add .
   git commit -m "Sello QR 1.0.0"
   git remote add origin https://github.com/TU_USUARIO/sello-qr.git
   git push -u origin main
   ```
3. Entra en [packagist.org](https://packagist.org), inicia sesión con GitHub y pulsa **Submit**.
4. Pega la URL del repo. Packagist mostrará el paquete `sello/qr` con tu nombre.

A partir de ahí, en cualquier proyecto:

```bash
composer require sello/qr
```

Si el nombre `sello/qr` ya está cogido en Packagist, cambia el `"name"` de `composer.json` a `jesusvldev/sello` (o el usuario que uses en GitHub) y vuelve a enviar.

---

Creado por **Jesus** (`jesusvldev@gmail.com`). Licencia MIT.
