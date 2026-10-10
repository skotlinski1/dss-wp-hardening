# Instalacja i wdrożenie

## Wymagania

- **WordPress:** najnowszy stabilny (7.0+). Wtyczka nie sprawdza wersji.
- **PHP 8.3+** (wymaga tego `composer.json`). Składnię sprawdza CI na PHP 8.3 i 8.5.

## Instalacja ręczna

Skopiuj `dss-wp-hardening.php` do `wp-content/mu-plugins/`. MU-plugin nie wymaga aktywacji. W katalogu
MU-pluginów może być tylko jedna kopia pliku: druga zadeklarowałaby te same funkcje w przestrzeni nazw
`DSS\Hardening` i zakończyła każde żądanie błędem krytycznym.

## Instalacja przez Composera

W projekcie DSS WP Manage paczka `dss/wp-hardening` (typ `wordpress-muplugin`, wersja z tagu Git bez `v`) jest
instalowana do katalogu MU-pluginów i ładowana przez loader DSS. Instrukcja krok po kroku:
[COMPOSER.md](../COMPOSER.md).

## Wyłącznik awaryjny

W `wp-config.php` przed `wp-settings.php`:

```php
define('DSS_WP_HARDENING_DISABLED', true);
```

Wtyczka niczego wtedy nie rejestruje i niczego nie zmienia w bazie, więc strona wraca do zachowania rdzenia.
Usunięcie linii przywraca działanie. Ta sama stała służy do porównań z wtyczką i bez niej.

## Czas sesji kont edytorskich

Opcjonalne i domyślnie wyłączone. Dwie stałe w godzinach skracają sesję kont z uprawnieniem `edit_posts`
(administrator, redaktor, autor, współpracownik); klienci sklepu zostają przy czasie z WordPressa. Reguły:
[DZIALANIE.md](DZIALANIE.md#4-czas-sesji).

| Stała | Dla logowania | Przykład |
|---|---|---|
| `DSS_WP_HARDENING_ADMIN_SESSION_HOURS` | bez „Zapamiętaj mnie” | `4` |
| `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS` | z „Zapamiętaj mnie” (większa od poprzedniej) | `12` |

W projekcie DSS WP Manage ustaw je w `.env` i dopisz do deklaracji `DSS_WP_MANAGE_ENV_TO_CONST` (jest tylko
jedna na plik; kolejna linia zastąpiłaby poprzednią):

```
DSS_WP_HARDENING_ADMIN_SESSION_HOURS=4
DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS=12
DSS_WP_MANAGE_ENV_TO_CONST="
    DSS_WP_HARDENING_ADMIN_SESSION_HOURS:INT
    DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS:INT
"
```

Przy ręcznej instalacji: `define('DSS_WP_HARDENING_ADMIN_SESSION_HOURS', 4);` w `wp-config.php` przed
`wp-settings.php`. Wyłącznik awaryjny wyłącza także tę funkcję.

## Sprawdzenie po wdrożeniu

Po wdrożeniu i po każdej większej aktualizacji WordPressa, w oknie prywatnym przeglądarki (bez logowania):

1. `/?author=1` i `/wp-json/wp/v2/users` odpowiadają 404, a `/wp-sitemap.xml` nie zawiera
   `wp-sitemap-users-1.xml`.
2. `/xmlrpc.php` odpowiada 403 („XML-RPC jest wyłączone.”).
3. W źródle strony głównej nie ma `name="generator"`.
4. Logowanie z nieistniejącym loginem i z istniejącym loginem i złym hasłem pokazuje ten sam komunikat.
5. Po zalogowaniu panel działa, a lista użytkowników się otwiera.
6. Jeśli ustawiono czas sesji: po zalogowaniu na konto administratora `wp user meta get ID session_tokens`
   pokazuje `expiration` o podaną liczbę godzin późniejsze niż `login`.

Filtry są na miejscu (brak błędów PHP niczego nie potwierdza):

```bash
wp eval 'var_dump(has_filter("request", "DSS\Hardening\block_author_query"), has_filter("the_generator", "__return_empty_string"));'
```

Obie wartości mają być `10`.
