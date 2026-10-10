# DSS — Hardening

Własny MU-plugin dla WordPressa (`dss-wp-hardening.php`, paczka Composera `dss/wp-hardening`), który ukrywa
informacje i zamyka wejścia, które pomagają atakującemu. To narzędzie na własne potrzeby, bez gwarancji
zgodności z innymi konfiguracjami.

Zbędne zasoby i linki w `<head>` (emoji, RSD, RSS) usuwa `dss-wp-cleanup`. Nagłówki bezpieczeństwa HTTP,
limity prób logowania i reguły WAF ustawiają serwer i Cloudflare (opis dla strony: `docs/BEZPIECZENSTWO.md` i
`docs/CLOUDFLARE.md` w repo `dss-wp-site`).

**Dokumentacja:** [docs/](docs/README.md). Instalacja przez Composera w projekcie DSS WP Manage:
[COMPOSER.md](COMPOSER.md).

## Co robi

- `xmlrpc.php` odpowiada 403, bez nagłówka `X-Pingback`.
- Niezalogowany nie pozna loginów (slugów) autorów: `/?author=N`, archiwa autorów i ich kanały dają 404,
  linki do archiwów prowadzą na stronę główną, nie ma tras REST `/wp/v2/users` ani mapy
  `wp-sitemap-users-*.xml`, oEmbed i klasy komentarzy nie podają autora.
- Logowanie i reset hasła nie zdradzają, czy konto istnieje.
- Bez wersji WordPressa w meta `generator` i w kanałach.
- Bez haseł aplikacji (logowanie aplikacji do REST API i XML-RPC z pominięciem formularza logowania).
- Opcjonalnie krótsza sesja kont edytorskich (administrator, redaktor, autor, współpracownik), osobno z
  „Zapamiętaj mnie” i bez niego; klienci sklepu zostają przy czasie z WordPressa. Wyłączona, dopóki nie
  ustawisz stałych `DSS_WP_HARDENING_ADMIN_SESSION_HOURS` i `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS`
  ([docs/INSTALACJA.md](docs/INSTALACJA.md#czas-sesji-kont-edytorskich)).

Zalogowani mają dalej panel i REST API. Każdy hak z powodem i to, czego wtyczka nie zasłania:
[docs/DZIALANIE.md](docs/DZIALANIE.md).

## Wymagania

Najnowszy stabilny WordPress (7.0+), PHP 8.3+. Szczegóły: [docs/INSTALACJA.md](docs/INSTALACJA.md#wymagania).

## Instalacja

Skopiuj `dss-wp-hardening.php` do `wp-content/mu-plugins/` (MU-plugin nie wymaga aktywacji) albo zainstaluj
Composerem w projekcie DSS WP Manage ([COMPOSER.md](COMPOSER.md)). Wyłącznik awaryjny w `wp-config.php` przed
`wp-settings.php`:

```php
define('DSS_WP_HARDENING_DISABLED', true);
```

## Licencja

GPL-2.0-or-later.
