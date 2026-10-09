# DSS — Security

Własny MU-plugin dla WordPressa (`dss-wp-security.php`, paczka Composera `dss/wp-security`), który ukrywa
informacje i zamyka wejścia, które pomagają atakującemu. To narzędzie na własne potrzeby, bez gwarancji
zgodności z innymi konfiguracjami.

**Stan: wersja 0.1.0 nie rejestruje żadnych haków i niczego nie zmienia na stronie.** Plan zakresu:
[docs/DZIALANIE.md](docs/DZIALANIE.md).

**Dokumentacja:** [docs/](docs/README.md). Instalacja przez Composera w projekcie DSS WP Manage:
[COMPOSER.md](COMPOSER.md).

## Wymagania

Najnowszy stabilny WordPress (7.0+), PHP 8.3+. Szczegóły: [docs/INSTALACJA.md](docs/INSTALACJA.md#wymagania).

## Instalacja

Skopiuj `dss-wp-security.php` do `wp-content/mu-plugins/` (MU-plugin nie wymaga aktywacji) albo zainstaluj
Composerem w projekcie DSS WP Manage ([COMPOSER.md](COMPOSER.md)). Wyłącznik awaryjny w `wp-config.php` przed
`wp-settings.php`:

```php
define('DSS_WP_SECURITY_DISABLED', true);
```

## Licencja

GPL-2.0-or-later.
