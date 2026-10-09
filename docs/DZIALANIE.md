# Działanie

## Stan

Wersja 0.1.0 nie rejestruje żadnych haków: `configure()` na `plugins_loaded` jest pusta. Strona działa tak
samo z wtyczką i bez niej.

## Zakres

Zakres (SRP): informacje i wejścia, które pomagają atakującemu. Podział między wtyczki DSS: sekcja 5
[`.github/CONTRIBUTING.md`](../.github/CONTRIBUTING.md#5-zakres-i-podział-między-wtyczki).

## Plan

Tematy, które mają tu trafić. To plan, a nie opis działania: nic z tej tabeli nie jest jeszcze napisane ani
sprawdzone. Każdy temat przed napisaniem kodu sprawdzamy w kodzie rdzenia WordPressa (nazwy i priorytety
haków), a po napisaniu na prawdziwym WordPressie.

| Temat | Co |
|---|---|
| **wyliczanie autorów** | `/?author=N`, archiwa `/author/slug/` i klasa `author-*` w `<body>`, REST `/wp-json/wp/v2/users`, `author_name` w oEmbed, `wp-sitemap-users-*.xml` |
| **komunikaty logowania i resetu hasła** | ten sam komunikat dla złego loginu i złego hasła |
| **wersja WordPressa** | meta `generator` w `<head>` i w kanałach |
| **XML-RPC** | `xmlrpc.php`, gdy strona go nie używa |

## Czego wtyczka nie robi

| Temat | Gdzie |
|---|---|
| nagłówki bezpieczeństwa HTTP (CSP, HSTS, `X-Frame-Options`) | konfiguracja serwera (LiteSpeed) |
| limity prób logowania, firewall | serwer albo usługa przed serwerem |
| zbędne zasoby i linki w `<head>` (emoji, RSD, RSS) | `dss-wp-cleanup` |
| dane SEO | `dss-wp-seo` |
