# Historia zmian

Format wzorowany na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersjonowanie
[SemVer](https://semver.org/lang/pl/). Najnowsze wpisy na górze. Tagi bez przedrostka `v`, numer taki sam jak
`Version:` w `dss-wp-security.php`. Zmiany tylko w plikach spoza paczki (dokumentacja, `.github/`) nie dostają
numeru: wspomina je najbliższe wydanie.

## 0.2.0 — 2026-10-09

- XML-RPC wyłączone: `xmlrpc.php` odpowiada 403, bez nagłówka `X-Pingback`.
- Wyliczanie autorów: `/?author=N`, archiwa autorów i ich kanały dają 404, linki do archiwów prowadzą na
  stronę główną, trasy REST `/wp/v2/users` tylko dla zalogowanych, bez mapy użytkowników, autora w oEmbed i
  klasy `comment-author-{slug}`.
- Logowanie i reset hasła: jeden komunikat dla złego loginu, e-maila i hasła; reset dla nieznanego konta
  kończy się jak dla istniejącego.
- Bez wersji WordPressa w meta `generator` i w kanałach.

## 0.1.0 — 2026-10-09

- Pierwsza wersja: pusty MU-plugin `dss-wp-security.php` (nagłówek, wyłącznik awaryjny
  `DSS_WP_SECURITY_DISABLED`, pusta `configure()`), paczka Composera `dss/wp-security`. Wtyczka niczego nie
  zmienia na stronie.
- Zasady pracy jak w pozostałych wtyczkach DSS: `.github/CONTRIBUTING.md` (numer wersji, zakres i podział
  między wtyczki, tagi), szablon PR, CI ze sprawdzeniem składni na PHP 8.3 i 8.5, `CLAUDE.md`, dokumentacja w
  `docs/` i `COMPOSER.md`.
