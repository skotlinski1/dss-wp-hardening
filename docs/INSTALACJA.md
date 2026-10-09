# Instalacja i wdrożenie

## Wymagania

- **WordPress:** najnowszy stabilny (7.0+). Wtyczka nie sprawdza wersji.
- **PHP 8.3+** (wymaga tego `composer.json`). Składnię sprawdza CI na PHP 8.3 i 8.5.

## Instalacja ręczna

Skopiuj `dss-wp-security.php` do `wp-content/mu-plugins/`. MU-plugin nie wymaga aktywacji. W katalogu
MU-pluginów może być tylko jedna kopia pliku: druga zadeklarowałaby te same funkcje w przestrzeni nazw
`DSS\Security` i zakończyła każde żądanie błędem krytycznym.

## Instalacja przez Composera

W projekcie DSS WP Manage paczka `dss/wp-security` (typ `wordpress-muplugin`, wersja z tagu Git bez `v`) jest
instalowana do katalogu MU-pluginów i ładowana przez loader DSS. Instrukcja krok po kroku:
[COMPOSER.md](../COMPOSER.md).

## Wyłącznik awaryjny

W `wp-config.php` przed `wp-settings.php`:

```php
define('DSS_WP_SECURITY_DISABLED', true);
```

Wtyczka niczego wtedy nie rejestruje i niczego nie zmienia w bazie, więc strona wraca do zachowania rdzenia.
Usunięcie linii przywraca działanie. Ta sama stała służy do porównań z wtyczką i bez niej.

## Sprawdzenie po wdrożeniu

Po wdrożeniu i po każdej większej aktualizacji WordPressa, w oknie prywatnym przeglądarki (bez logowania):

1. `/?author=1` i `/wp-json/wp/v2/users` odpowiadają 404, a `/wp-sitemap.xml` nie zawiera
   `wp-sitemap-users-1.xml`.
2. `/xmlrpc.php` odpowiada 403 („XML-RPC jest wyłączone.”).
3. W źródle strony głównej nie ma `name="generator"`.
4. Logowanie z nieistniejącym loginem i z istniejącym loginem i złym hasłem pokazuje ten sam komunikat.
5. Po zalogowaniu panel działa, a lista użytkowników się otwiera.

Filtry są na miejscu (brak błędów PHP niczego nie potwierdza):

```bash
wp eval 'var_dump(has_filter("request", "DSS\Security\block_author_query"), has_filter("the_generator", "__return_empty_string"));'
```

Obie wartości mają być `10`.
