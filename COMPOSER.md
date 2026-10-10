# Instalacja przez Composera w projekcie DSS WP Manage

Ta instrukcja pokazuje krok po kroku, jak dodać wtyczkę `dss-wp-hardening.php` z tego prywatnego repozytorium do projektu witryny, który używa [DSS WP Manage](https://github.com/skotlinski1/dss-wp-manage). Po wykonaniu kroków wtyczka będzie instalowana Composerem i ładowana automatycznie, a nowe wersje wdrożysz jednym poleceniem.

Ogólne zasady podłączania prywatnych pakietów DSS opisują tutoriale w repo `dss-wp-manage` (katalog `docs/pl/`: `TUTORIAL-prywatny-mu-plugin-i-motyw.md` oraz `TUTORIAL-prywatne-repozytorium-w-composerze.md`). Tu jest to samo, ale tylko dla tej wtyczki.

## Najpierw: gdzie co robisz

W instrukcji pojawiają się trzy miejsca. Przy każdym kroku jest napisane, w którym pracujesz:

| Nazwa w instrukcji | Co to jest | Przykład |
|---|---|---|
| **repo wtyczki** | katalog z kodem wtyczki na Twoim komputerze (to repozytorium `dss-wp-hardening`) | `~/Projects/dss-wp-hardening` |
| **projekt witryny** | katalog Twojej strony, w którym jest jej `composer.json` | `~/Projects/example.com` |
| **serwer** | komputer, na którym działa strona (staging, produkcja) | zalogowany przez SSH |

Polecenia wpisujesz w terminalu. Zawsze najpierw przejdź do właściwego katalogu (`cd ...`), bo to samo polecenie w złym katalogu zrobi coś innego albo się nie uda. W przykładach `example.com` zastąp nazwą katalogu swojej strony.

## Zanim zaczniesz: lista kontrolna

Zaznacz każdy punkt. Jeśli któregoś nie masz, zrób to najpierw.

- [ ] Projekt witryny już działa z DSS WP Manage (zainstalowany, jest `.env`, `composer install` przechodzi bez błędu). Jeśli nie, zacznij od dokumentacji w repo `dss-wp-manage`.
- [ ] W projekcie witryny jest zainstalowany Composer 2 (`composer --version` pokazuje 2.x).
- [ ] Masz dostęp do konta GitHub, na którym jest to repo.

## Krok 1. Oznacz wersję tagiem (repo wtyczki)

Composer widzi wersje tylko jako **tagi Git**, czyli nazwane etykiety na commicie. Bez tagu Composer nie znajdzie żadnej wersji.

**Wersja, którą chcesz zainstalować, ma już tag?** Sprawdź na GitHubie: repo → **Tags**. Jeśli tak, ten krok jest zrobiony: przejdź do kroku 2. Wykonujesz go przy każdej nowej wersji (np. `0.4.0`).

**Nie masz jeszcze repo wtyczki na komputerze?** Pobierz je (tylko raz):

```bash
mkdir -p ~/Projects
cd ~/Projects
git clone https://github.com/skotlinski1/dss-wp-hardening.git
```

Git zapyta o **Username** (`skotlinski1`) i **Password**. Jako hasło wklej token GitHub. Do wypchnięcia tagu token musi mieć uprawnienie **Contents: Read and write** dla tego repo (token tylko do odczytu z kroku 2 nie wystarczy). Terminal nic nie pokazuje podczas wklejania, to normalne.

Procedura tagu (szczegóły w `.github/CONTRIBUTING.md`, sekcja 3). `COMMIT_MERGE` to pełny numer commita, którym pull request z nową wersją wszedł do `main` (podaje go Claude po merge'u albo `git log --oneline -3` na `main`), a `X.Y.Z` to numer z `Version:`:

```bash
cd ~/Projects/dss-wp-hardening && git switch main && git pull && git show COMMIT_MERGE:dss-wp-hardening.php | grep "Version:"
git tag -a X.Y.Z -m "DSS Hardening X.Y.Z" COMMIT_MERGE && git push origin X.Y.Z && git ls-remote --tags origin | grep -F "refs/tags/X.Y.Z"
```

- Pierwsza linia ma wypisać ` * Version: X.Y.Z`. Inny numer: nie uruchamiaj drugiej. Tagi w DSS nie mają przedrostka `v`.
- Ostatnie polecenie wypisuje dwie linie: `refs/tags/X.Y.Z` i `refs/tags/X.Y.Z^{}`; ta druga ma zaczynać się od `COMMIT_MERGE`.

Jeśli polecenie `git tag` odpowie, że tag już istnieje, nie nadpisuj go. Podnieś wersję w pliku wtyczki, zrób commit i pull request, a po zmergowaniu utwórz tag z nowym numerem.

## Krok 2. Token do prywatnego repo (GitHub i projekt witryny)

Repo jest prywatne, więc Composer musi się przed GitHubem uwierzytelnić. Służy do tego **token**, czyli długie hasło tylko do odczytu.

**Masz już token do innych prywatnych repo DSS** (np. `dss-wp-manage`)? Nie twórz nowego. Wejdź do jego ustawień (GitHub → zdjęcie profilu → **Settings** → **Developer settings** → **Personal access tokens** → **Fine-grained tokens** → nazwa tokenu → **Edit**), w **Repository access** dopisz `dss-wp-hardening` i zapisz. Potem przejdź od razu do kroku 3 (plik `auth.json` już masz, nic w nim nie zmieniasz).

### 2a. Nowy token (jeśli go jeszcze nie masz)

1. GitHub → zdjęcie profilu → **Settings** → **Developer settings** → **Personal access tokens** → **Fine-grained tokens** → **Generate new token**.
2. **Token name:** np. `composer-dss`.
3. **Expiration:** wybierz datę wygaśnięcia i **zapisz ją w kalendarzu**. Po tej dacie `composer install` na serwerze przestanie działać i trzeba będzie wygenerować nowy token.
4. **Resource owner:** Twoje konto.
5. **Repository access:** *Only select repositories* i wybierz `dss-wp-hardening` (oraz inne repo, które Composer ma pobierać, np. `dss-wp-manage`).
6. **Permissions** → **Repository permissions** → **Contents** → ustaw **Read-only**. Pozostałe zostaw na „No access”.
7. Kliknij **Generate token** i od razu skopiuj token (zaczyna się od `github_pat_`). GitHub pokazuje go tylko raz.

### 2b. Plik `auth.json` (projekt witryny)

Jeśli w katalogu projektu witryny masz już plik `auth.json` z `github-oauth` (bo działają inne prywatne repo), pomiń ten punkt. W przeciwnym razie:

1. Przejdź do projektu witryny:
   ```bash
   cd ~/Projects/example.com
   ```
2. Utwórz plik `auth.json` obok `composer.json` z taką zawartością (wstaw swój token w miejsce `github_pat_TWOJ_TOKEN`):
   ```json
   {
     "github-oauth": {
       "github.com": "github_pat_TWOJ_TOKEN"
     }
   }
   ```
3. Ogranicz dostęp do pliku:
   ```bash
   chmod 600 auth.json
   ```
4. Dopisz `/auth.json` do pliku `.gitignore` projektu, żeby token nigdy nie trafił do Git. Nie wpisuj tokenu do `composer.json`.

### 2c. Serwer

`composer install` działa też na serwerze, więc **ten sam plik `auth.json`** musi leżeć w katalogu projektu na serwerze (obok `composer.json`). Zamiast pliku możesz ustawić zmienną środowiskową `COMPOSER_AUTH` z taką samą treścią JSON. Uprawnienia `chmod 600` ustaw też tam.

## Krok 3. Podłącz wtyczkę (projekt witryny)

Wszystko w tym kroku robisz w katalogu projektu witryny (`cd ~/Projects/example.com`).

### 3a. Dopisz repozytorium do `composer.json`

Otwórz `composer.json` projektu witryny i znajdź tablicę `"repositories"`. Dopisz do niej jedną nową pozycję. Pamiętaj o przecinku po poprzedniej pozycji. Przykład (nowa jest druga):

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/skotlinski1/dss-wp-manage.git" },
  { "type": "vcs", "url": "https://github.com/skotlinski1/dss-wp-hardening.git" }
]
```

Jeśli masz tam inne pozycje (np. `wpackagist.org`), zostaw je bez zmian.

### 3b. Sprawdź, czy są ustawienia katalogu MU-pluginów

W tym samym `composer.json` znajdź sekcję `"extra"`. Mają w niej być dwa wpisy, które wskazują ten sam katalog. Jeśli już masz jakiś własny MU-plugin, zwykle są. Jeśli ich nie ma, dopisz:

```json
"extra": {
  "installer-paths": {
    "public/app/mu/{$name}/": ["type:wordpress-muplugin"]
  },
  "dss-wp-manage": {
    "mu-plugins-dir": "mu"
  }
}
```

Jeśli sekcje `installer-paths` i `dss-wp-manage` już istnieją, **nie twórz drugiej kopii**, tylko dopisz do nich brakujące wpisy. Ścieżki w Twoim projekcie mogą być inne niż w przykładzie (zależą od `wordpress-content-dir`); ważne, żeby `installer-paths` i `mu-plugins-dir` wskazywały ten sam katalog. Wzór kompletnego `composer.json` projektu to plik `examples/composer.json.example` w repo `dss-wp-manage`.

### 3c. Zainstaluj wtyczkę

```bash
composer require dss/wp-hardening:^0.4
```

`dss/wp-hardening` to nazwa pakietu (pole `name` w `composer.json` wtyczki, nie nazwa repo na GitHubie). `^0.4` znaczy: dowolna wersja 0.4.x od 0.4.0 w górę (przy numerach 0.Y.Z każde nowe Y wymaga zmiany wymagania, zob. krok 6).

Po udanej instalacji w wyniku zobaczysz m.in. linię `Installing dss/wp-hardening (0.4.0)`, a potem etapy DSS kończące się informacją o sukcesie. Composer zmieni `composer.json` (doda wpis w `require`) i `composer.lock`.

DSS po instalacji sam uruchamia swoje etapy, więc w projekcie musi być poprawny `.env`. Bez niego polecenie zakończy się błędem DSS (`Cannot read environment file`), a wtyczka i tak zostanie zainstalowana. Uzupełnij `.env` według dokumentacji `dss-wp-manage` i uruchom `composer dss-wp-manage`.

### 3d. Co się stało

- Wtyczka leży w `public/app/mu/wp-hardening/` (nazwa katalogu to część nazwy pakietu po ukośniku).
- DSS dopisał jej plik `wp-hardening/dss-wp-hardening.php` do wygenerowanego pliku `public/app/mu/dss-wp-manage-mu-loader.php`. **Nie musisz pisać własnego pliku ładującego**, DSS robi to sam. (WordPress ładuje tylko pliki leżące bezpośrednio w katalogu MU, nie w podkatalogach, dlatego ten loader jest potrzebny.)
- Jeśli loadera nie ma albo nie zawiera `wp-hardening`, uruchom w projekcie witryny: `composer dss-wp-manage build-mu-loader`.

### 3e. Pilnuj `.gitignore` projektu

Katalog `public/app/mu/wp-hardening/` ma być w `.gitignore` projektu witryny (przywraca go `composer install`). W repo strony zapisujesz tylko `composer.json` i `composer.lock`. Nie zmieniaj plików w `public/app/mu/wp-hardening/` ręcznie: przepadną przy aktualizacji.

## Krok 4. Sprawdź, czy działa (projekt witryny)

1. Sprawdź, że Composer widzi pakiet:
   ```bash
   composer show dss/wp-hardening
   ```
   Ma pokazać `versions : * 0.4.0` oraz `path` kończący się na `public/app/mu/wp-hardening`.
2. Sprawdź wpis w loaderze:
   ```bash
   grep wp-hardening public/app/mu/dss-wp-manage-mu-loader.php
   ```
   Ma wypisać linię z `wp-hardening/dss-wp-hardening.php`.
3. Sprawdź stronę: front i panel odpowiadają bez błędów. Co jeszcze sprawdzić, zależy od tego, co wtyczka robi w
   danej wersji: [docs/DZIALANIE.md](docs/DZIALANIE.md).

Uwaga: na liście **Wtyczki → Must-Use** zobaczysz tylko „DSS WP Manage MU plugins loader”, a nie osobną pozycję `DSS — Hardening`. To normalne, wtyczka jest ładowana przez loader (w opisie loadera jest lista załadowanych plików).

## Krok 5. Wdróż na serwer

1. W projekcie witryny zrób commit zmienionych plików `composer.json` i `composer.lock` (i ewentualnie `.gitignore`) i wyślij je do repo projektu, tak jak zwykle wdrażasz stronę (szczegóły w tutorialu DSS „od Maca do serwera”).
2. Na serwerze, w katalogu projektu, po pobraniu nowych plików:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   Na serwerze **nie** uruchamiaj `composer update`. `install` instaluje dokładnie te wersje, które są w `composer.lock`.
3. Na serwerze muszą być: plik `.env` i plik `auth.json` (albo `COMPOSER_AUTH`) z tokenem z kroku 2.
4. Powtórz sprawdzenie z kroku 4 na stronie na serwerze.

## Krok 6. Nowa wersja wtyczki w przyszłości

1. **Repo wtyczki:** wprowadź zmiany, podnieś `Version:` w `dss-wp-hardening.php` (np. na `0.4.1`), zrób commit i pull request, zmerguj do `main`.
2. **Repo wtyczki:** utwórz tag jak w kroku 1.
3. **Projekt witryny:**
   ```bash
   cd ~/Projects/example.com
   composer update dss/wp-hardening
   ```
   Ograniczenie `^0.4` pozwala tylko na wersje 0.4.x. Na 0.5.0 (i każde kolejne Y, także 1.0.0) trzeba najpierw zmienić wymaganie w `composer.json` (np. na `^0.5`).
4. Sprawdź jak w kroku 4, zrób commit `composer.json` i `composer.lock` i wdróż jak w kroku 5.

Nie używaj `"dev-main"` zamiast tagów na produkcji: Composer sklonuje wtedy całe repo i w `public/app/mu/wp-hardening/` pojawi się katalog `.git` z historią kodu.

## Krok 7. Usunięcie wtyczki

W projekcie witryny:

```bash
composer remove dss/wp-hardening
```

Composer usunie katalog `public/app/mu/wp-hardening/`, wpis z `composer.json` i `composer.lock`, a DSS odświeży loader. Zrób commit `composer.json` i `composer.lock`.

## Najczęstsze problemy

| Objaw (komunikat) | Co zrobić |
|---|---|
| `Root composer.json requires dss/wp-hardening, it could not be found in any version` | Brak repo w `repositories` (krok 3a), nazwa w `require` inna niż `dss/wp-hardening`, brak tagu (krok 1) albo brak dostępu do repo (zły lub wygasły token). |
| `Could not authenticate against github.com` albo pytanie o hasło w terminalu | Brak `auth.json` (albo `COMPOSER_AUTH`) w katalogu projektu, najczęściej na serwerze, albo token wygasł lub nie obejmuje tego repo (krok 2). |
| `found dss/wp-hardening[…] but it does not match the constraint` | W repo nie ma tagu pasującego do wymagania (np. wpisałeś `^0.5`, a są tylko 0.4.x). Zmień wymaganie albo utwórz tag. |
| `must contain exactly one main PHP file with a Plugin Name header … found 2` (albo `found 0`) | W katalogu głównym wtyczki są dwa pliki z `Plugin Name:` albo żaden. Popraw repo wtyczki, wydaj nowy tag i zrób `composer update dss/wp-hardening`. |
| Wtyczka leży w `wp-content/mu-plugins/wp-hardening`, a nie w `public/app/mu/` | Brak `installer-paths` albo nie pasuje do `mu-plugins-dir` (krok 3b). DSS podaje regułę do wklejenia. |
| Wtyczka ładuje się dwa razy | Główny plik jest też w `autoload.files` pakietu. Tu go tam nie ma, więc nie dodawaj. |
| `Cannot read environment file` | W projekcie nie ma `.env` (zob. krok 3c). |
| `your php version (…) does not satisfy that requirement` | W projekcie jest PHP starsze niż 8.3. |

## Co przetestowano, a co nie

- **Przetestowano:** zawartość archiwum, które instaluje Composer (`git archive` z `.gitattributes`): `LICENSE`, `composer.json` i `dss-wp-hardening.php`; reguła DSS WP Manage 5.3.0 wyboru pliku głównego (`MuPluginList::mainPluginFiles()`) zwraca dla niego dokładnie jeden plik, `dss-wp-hardening.php`; `composer validate --strict`.
- **Nie przetestowano:** pełnego `composer require` w projekcie DSS WP Manage (z loaderem wygenerowanym przez DSS) ani pobierania z prywatnego GitHuba z tokenem. Kroki 2, 3 i 5 opierają się na tutorialach DSS i dokumentacji Composera. Jeśli któryś komunikat różni się od opisanego, ufaj temu, co pokazuje Twój terminal, i sprawdź tutorial DSS.
