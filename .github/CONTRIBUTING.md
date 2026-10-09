# Zasady pracy nad DSS — Hardening

Ten plik jest dla opiekuna i Claude'a. Claude przygotowuje zmianę i PR. Opiekun klika merge i zakłada tag.

Każda zmiana paczki wchodzi do `main` jako **osobna wersja `X.Y.Z`** z własnym tagiem i wpisem w
`CHANGELOG.md`. Paczka to to, co instaluje Composer: `dss-wp-hardening.php`, `composer.json` i `LICENSE`
(reszta ma `export-ignore` w `.gitattributes`). Numer wersji to `Version:` w nagłówku `dss-wp-hardening.php`.

Zmiana tylko w plikach z `export-ignore` (`README.md`, `COMPOSER.md`, `CHANGELOG.md`, `CLAUDE.md`, `docs/`,
`.github/`) nie dostaje wersji, tagu ani wpisu. Najbliższe wydanie wspomina ją jednym punktem: listę daje `git
log --oneline OSTATNI_TAG..origin/main`.

## 1. Jaki numer: X, Y czy Z?

Numer ustalasz przed PR-em. Idź po kolei i zatrzymaj się na pierwszym trafieniu:

1. Strona po aktualizacji musi coś zmienić ręcznie albo zacznie działać inaczej niż dotąd? → **X**
2. Nowa możliwość, a strona działa bez zmian? → **Y**
3. Wszystko inne → **Z**

Zwiększając X zerujesz Y i Z, zwiększając Y zerujesz Z (np. z 1.4.7: X daje 2.0.0, Y daje 1.5.0, Z daje
1.4.8). Przy kilku zmianach w PR-ze liczy się najwyższy numer. W razie wątpliwości wybierz wyższy.

| Zmiana | Numer |
|---|---|
| nowy aktywny hak albo filtr (strona zaczyna działać inaczej) | X |
| usunięty aktywny hak, przeniesiony do innej wtyczki albo zmiana zakresu | X |
| usunięta lub przemianowana stała (np. wyłącznik awaryjny), wyższe minimalne PHP albo WordPress | X |
| nowa opcja nieaktywna (zakomentowana) albo nowa stała, która niczego nie zmienia bez ustawienia | Y |
| poprawka haka, który przestał działać po zmianie w rdzeniu (nazwa haka, priorytet) | Z |
| komentarze, refaktoryzacja, `composer.json` bez zmiany wymagań | Z |

Wersje `0.Y.Z` na czas przed pierwszym wdrożeniem: żadna strona nie działa jeszcze na tej wtyczce, więc zmiana
z wierszy X albo Y wchodzi jako **Y** (z sekcją „Aktualizacja” w `CHANGELOG.md`, gdy strona musi coś zrobić),
a reszta jako **Z**. Bez migracji i bez pytania o X. Wymaganie `^0.Y` w `composer.json` strony obejmuje tylko
jedno Y, więc nowe Y wymaga zmiany wymagania w repo `dss-wp-site`. Wersję `1.0.0` wydajesz, gdy pierwsza
strona ma zacząć działać na tej wtyczce; wtedy usuń ten akapit.

## 2. Jak wygląda zmiana

1. Gałąź od `main`, jeden commit na jedną logiczną zmianę.
2. Zmiana widoczna dla strony ma poprawkę w dokumentacji: `README.md` (skrót), `docs/` (szczegóły) i
   `COMPOSER.md`, jeśli dotyczy instalacji.
3. Na górze `CHANGELOG.md` sekcja `## X.Y.Z — RRRR-MM-DD`. Wpis ma być krótki: jeden punkt na zmianę, jedno
   lub dwa zdania o tym, co zmienia się dla strony. Sekcja „Aktualizacja” tylko wtedy, gdy strona musi coś
   zrobić. Wyniki sprawdzeń to najwyżej jedno zdanie, a szczegóły idą do opisu PR.
4. PR do `main` według szablonu, z numerem wersji i jednym zdaniem, dlaczego X, Y albo Z (albo „bez wersji”).
5. Dwa PR-y naraz: ten, który wchodzi później, bierze kolejny numer.
6. Bez zgodności wstecz i migracji: zmiana nie zachowuje starych nazw, stałych ani zachowań i nie dodaje
   kodu, który je wykrywa albo przenosi. Co strona musi zrobić przy aktualizacji, mówi tylko wpis w
   `CHANGELOG.md` (numer wersji dalej według sekcji 1).
7. Dokumentacja (`README.md`, `COMPOSER.md`, `docs/`, komentarze w kodzie) opisuje tylko stan bieżący, jakby
   to była pierwsza wersja: bez „od wersji X”, „wcześniej”, „zmieniono”, opisów migracji i starych nazw.
   Historia, także dawne pomiary, jest wyłącznie w `CHANGELOG.md`.
8. Języki: nazwa wtyczki i identyfikatory w kodzie po angielsku; komentarze, dokumentacja, `CHANGELOG.md`,
   opisy commitów oraz tytuł i opis PR po polsku.

## 3. Po merge'u: tag

Tag zakłada opiekun, bo Claude nie ma uprawnień do pushowania tagów. Zaraz po merge'u PR-a z nową wersją
Claude podaje gotowe polecenia z numerem wersji i commitem. Opiekun wykonuje je w swoim lokalnym klonie repo.
Polecenia są połączone `&&`, więc gdy `cd` się nie uda, nic więcej się nie wykona:

```shell
cd ~/Projects/dss-wp-hardening && git switch main && git pull && git show COMMIT_MERGE:dss-wp-hardening.php | grep "Version:"
git tag -a X.Y.Z -m "DSS Hardening X.Y.Z" COMMIT_MERGE && git push origin X.Y.Z && git ls-remote --tags origin | grep -F "refs/tags/X.Y.Z"
```

`COMMIT_MERGE` to commit, którym PR wszedł do `main` (Claude podaje jego pełny numer). Pierwsza linia ma
wypisać `Version: X.Y.Z`. Jeśli wypisze inny numer, nie uruchamiaj drugiej. Ostatnie polecenie wypisuje dwie
linie: `refs/tags/X.Y.Z` (obiekt tagu) i `refs/tags/X.Y.Z^{}`, która ma zawierać `COMMIT_MERGE`. Nie skracaj
go do `git ls-remote --tags origin X.Y.Z`: z nazwą tagu część wersji Gita nie wypisuje linii `^{}`. Potem
Claude sprawdza, że tag jest widoczny na GitHubie.

Nazwa to dokładnie `X.Y.Z`: trzy liczby, bez `v`. Composer czyta wersje z tagów. Tagu nie przenosimy, błąd
naprawia kolejna wersja.

## 4. Sprawdzenia przed merge'em

- **CI na PR** (GitHub Actions) sprawdza składnię `dss-wp-hardening.php` na PHP 8.3 i 8.5 oraz `composer
  validate --strict`. O merge prosisz dopiero przy zielonym CI. Czerwone CI naprawiasz w tym samym PR.
- **Zmiana haków wymaga próby na prawdziwym WordPressie** z klasycznym motywem: aktywny hak ma być widoczny w
  `has_filter()` / `has_action()` (odpięty: zniknąć), skutek ma być widoczny w odpowiedzi (HTML, nagłówki, kod
  odpowiedzi), a strona (front i panel) odpowiadać bez błędów PHP. Porównuj z wtyczką i bez niej
  (`DSS_WP_HARDENING_DISABLED`). Wynik idzie do opisu PR i do `docs/`. CI tego nie sprawdza.
- Nazwy i priorytety haków rdzenia sprawdzaj w kodzie WordPressa, na którym robisz próbę (`wp-includes/`), a
  nie z pamięci.

## 5. Zakres i podział między wtyczki

Zakres tej wtyczki (SRP): informacje i wejścia, które pomagają atakującemu. Każda funkcja ma jedno miejsce:

| Co | Gdzie |
|---|---|
| Gutenberg i bloki rdzenia | `dss-no-blocks` |
| zbędne funkcje i zasoby WooCommerce, także jego bloki | `dss-disable-woo-bloatware` (`dss/lean-woocommerce`) |
| zbędne albo ciężkie wyjście i zasoby rdzenia (wydajność) | `dss-wp-cleanup` |
| informacje i wejścia, które pomagają atakującemu | `dss-wp-hardening` |
| dane dla wyszukiwarek i serwisów społecznościowych | `dss-wp-seo` |
| obrazy, ich warianty i kopie na CDN | `dss-media-suite` |
| wygląd (HTML, CSS, szablony) | motyw `dss-wp-theme` |

Pytanie kontrolne przy usuwaniu czegoś z WordPressa: **dlaczego usuwamy?** Bo zbędne albo ciężkie:
`dss-wp-cleanup`. Bo pomaga atakującemu: `dss-wp-hardening`. Gdy funkcja pasuje do dwóch miejsc albo do
żadnego, Claude przedstawia opiekunowi propozycję przed napisaniem kodu.

## 6. Kod

- Wtyczka ma być lekka: bez monitoringu, powiadomień, ekranów ustawień i zbędnych funkcji. Nie zapisuje
  niczego w bazie przy zwykłym żądaniu. Tylko publiczne API WordPressa (filtry, akcje, `remove_action` /
  `remove_filter`).
- Aktywne są tylko haki, które strona naprawdę wykonuje. Pozostałe zostają zakomentowane, z powodem i
  informacją, kiedy je włączyć.
- Podział w pliku jest tematyczny, nie kontekstowy: haki rejestrują się w `configure()` przez funkcje
  `configure_*()` z jednym tematem każda.
- Każdy plik PHP: `declare(strict_types=1);`, przestrzeń nazw `DSS\Hardening` i wyjście, gdy nie ma stałej
  `ABSPATH`. Wyjście HTML przez `esc_html()`, `esc_attr()`, `esc_url()`, JSON przez `wp_json_encode()`.
- Wcięcia tabulatorami, linie do ok. 110 znaków. Komentarze: domyślnie zwykłe `//`. `/* ... */` (jedna
  gwiazdka) tylko wtedy, gdy zaraz pod nim jest zakomentowany kod w liniach `//` (opcja nieaktywna: powód i
  „Włącz, jeśli”). Służy wyłącznie do odróżnienia opisu od wyłączonego kodu; nie używaj go do zwykłych opisów.
  Opcje nieaktywne rozdzielaj pustą linią. `/** ... */` tylko dla docblocków funkcji i pliku. W komentarzu
  blokowym nie wpisuj dosłownego `*/`.
- W katalogu głównym tylko jeden plik z nagłówkiem `Plugin Name:` (`dss-wp-hardening.php`): DSS WP Manage
  odrzuca paczkę z inną ich liczbą.
- Nigdy nie commituj tokenów, `auth.json` ani sekretów.
