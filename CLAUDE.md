# Zasady pracy w tym repo

Pojedynczy MU-plugin `dss-wp-hardening.php` (paczka `dss/wp-hardening`): ukrywa informacje i zamyka wejścia,
które pomagają atakującemu. Plan zakresu i to, co należy do innych repo, są w `docs/DZIALANIE.md` i w sekcji 5
`.github/CONTRIBUTING.md`.

Zasady zmian są w `.github/CONTRIBUTING.md` i obowiązują także w sesji Claude: numer wersji, `CHANGELOG.md`,
języki, sprawdzenia przed merge'em, tagi i konwencje kodu (komentarze `//`, `/* */`, `/** */`). Ten plik
dodaje tylko to, co dotyczy samej sesji.

## Przebieg w sesji

- Pracuj na gałęzi sesji. Jeśli jej PR jest już scalony, zacznij ją od nowa od aktualnego `main`.
- Sam otwórz PR do `main`, bez pytania o zgodę. Nie scalaj PR-ów.
- Po pushu poczekaj, aż CI na PR się skończy, i podaj wynik. O merge proś dopiero przy zielonym CI.
- Po merge'u PR-a z nową wersją od razu podaj polecenia do tagu z sekcji 3 `CONTRIBUTING.md`, z pełnym hashem
  commita scalającego (z `git log` na `origin/main`).
- Nie dbaj o zgodność ze starymi wersjami ani o migracje, a dokumentację pisz tak, jakby bieżąca wersja była
  pierwsza (punkty 6 i 7 sekcji 2 `CONTRIBUTING.md`). Historia jest tylko w `CHANGELOG.md`.
- Opisów w dokumentacji nie przepisuj z pamięci: każde twierdzenie o haku ma wynikać z kodu wtyczki, z kodu
  rdzenia WordPressa albo z próby na prawdziwym WordPressie. Czego nie sprawdzono, oznacz jako niesprawdzone.
- Z użytkownikiem rozmawiaj po polsku.

## WordPress w sesji

Sposób na testowy WordPress z MariaDB i WP-CLI jest w `CLAUDE.md` repo `skotlinski1/dss-media-suite` (sekcja
„Testy”), a żądanie frontu z CLI i pomiar HTML w `CLAUDE.md` repo `skotlinski1/dss-no-blocks` (sekcja
„WordPress w sesji”). Do próby tej wtyczki:

- Klasyczny motyw bez `theme.json`, np. Twenty Twenty (`wp theme activate twentytwenty`).
- Wtyczkę podłącz plikiem w `wp-content/mu-plugins/` z `require_once` pliku z repo, a porównanie bez niej rób
  przez `define('DSS_WP_HARDENING_DISABLED', true)` (w `wp eval` przez `--exec`).
- Po próbie przywróć motyw i usuń plik z `mu-plugins/`.

## Nie rób bez pytania

- Nie dodawaj funkcji spoza zakresu (sekcja 5 `CONTRIBUTING.md` mówi, które repo ją dostaje).
- Nie dodawaj jako domyślnych blokad, które mogą odciąć dostęp do panelu (logowanie, REST API dla
  zalogowanych, `admin-ajax.php`).
- Nie zapisuj tokenów, `auth.json` ani sekretów w repo.
