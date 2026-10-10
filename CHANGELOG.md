# Historia zmian

Format wzorowany na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersjonowanie
[SemVer](https://semver.org/lang/pl/). Najnowsze wpisy na górze. Tagi bez przedrostka `v`, numer taki sam jak
`Version:` w `dss-wp-hardening.php`. Zmiany tylko w plikach spoza paczki (dokumentacja, `.github/`) nie dostają
numeru: wspomina je najbliższe wydanie.

## 0.7.0 — 2026-10-10

- Czas sesji kont edytorskich (administrator, redaktor, autor, współpracownik) jest domyślnie skrócony: 4
  godziny, a z „Zapamiętaj mnie” 12 (WordPress daje 2 dni i 14 dni). Stałe
  `DSS_WP_HARDENING_ADMIN_SESSION_HOURS` i `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS` zmieniają te wartości, a `0`
  w obu przywraca czas WordPressa. Błędna wartość stałej oznacza wartość domyślną. Klienci sklepu bez zmian.

### Aktualizacja

- W `composer.json` strony: `"dss/wp-hardening": "^0.7"`.
- Konta edytorskie są wylogowywane po 4 godzinach od logowania (po 12 z „Zapamiętaj mnie”). Na Macu, jeśli to
  przeszkadza, wpisz w `.env` `0` w obu stałych (i dopisz ich nazwy do `DSS_WP_MANAGE_ENV_TO_CONST` jako `INT`).

## 0.6.0 — 2026-10-10

- Kanały RSS 2.0, RDF i Atom podają nazwę strony zamiast nazwy wyświetlanej autora (`<dc:creator>`,
  `<author><name>`). Zabezpiecza stronę na wypadek, gdy kanały zostaną włączone (`dss-wp-cleanup` wyłącza je
  domyślnie). Filtr dodaje dopiero żądanie kanału.

### Aktualizacja

- W `composer.json` strony: `"dss/wp-hardening": "^0.6"`.

## 0.5.0 — 2026-10-10

- Opcjonalny czas sesji kont edytorskich: stałe `DSS_WP_HARDENING_ADMIN_SESSION_HOURS` (logowanie bez
  „Zapamiętaj mnie”) i `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS` (z nim), w godzinach, skracają sesję kont z
  uprawnieniem `edit_posts` (administrator, redaktor, autor, współpracownik). Klienci sklepu zostają przy
  czasie z WordPressa, a wartość dłuższa niż ta z WordPressa niczego nie zmienia. Bez stałych wtyczka niczego
  nie zmienia.
- W projekcie DSS WP Manage obie nazwy trzeba dopisać do `DSS_WP_MANAGE_ENV_TO_CONST` jako `INT`
  (opis: `docs/INSTALACJA.md`).
- Plik `dss-wp-hardening.php` uporządkowany tematycznie w cztery działy z nagłówkami: ukrywanie loginów
  (autorzy, komunikaty logowania), wejścia omijające formularz logowania (XML-RPC, hasła aplikacji), informacje
  o systemie (wersja) i sesje. `docs/DZIALANIE.md` ma ten sam podział. Działanie bez zmian.

## 0.4.0 — 2026-10-09

- Hasła aplikacji wyłączone (`wp_is_application_passwords_available` = false): rdzeń nie przyjmuje logowania
  hasłem aplikacji w REST API i XML-RPC i nie pokazuje tej sekcji w profilu. Wcześniej utworzone hasła przestają
  działać. Na stronie z Patchstackiem tę opcję wyłącza się w Patchstacku (zob. `docs/BEZPIECZENSTWO.md` w repo
  `dss-wp-site`).

## 0.3.0 — 2026-10-09

- Nowa nazwa: repo `dss-wp-hardening`, paczka `dss/wp-hardening`, plik `dss-wp-hardening.php`, przestrzeń nazw
  `DSS\Hardening`, wyłącznik `DSS_WP_HARDENING_DISABLED`, nazwa wtyczki `DSS — Hardening`. Działanie bez zmian.
  „Hardening” lepiej opisuje zakres: wtyczka zmniejsza powierzchnię ataku, a nie jest pełną wtyczką
  bezpieczeństwa (firewall, limity logowań i nagłówki HTTP zostają po stronie serwera).

### Aktualizacja

- W `composer.json` strony: repozytorium `https://github.com/skotlinski1/dss-wp-hardening.git` i
  `"dss/wp-hardening": "^0.3"` zamiast `dss/wp-security`; token GitHub obejmuje repo pod nową nazwą.
- Stała `DSS_WP_SECURITY_DISABLED` nie działa; wyłącznik to `DSS_WP_HARDENING_DISABLED`.

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
