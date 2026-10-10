# Działanie

Zakres (SRP): informacje i wejścia, które pomagają atakującemu. Podział między wtyczki DSS: sekcja 5
[`.github/CONTRIBUTING.md`](../.github/CONTRIBUTING.md#5-zakres-i-podział-między-wtyczki).

Zasada: nic tu nie odcina zalogowanych użytkowników od panelu ani od REST API. Wyjątek to czas sesji kont
edytorskich, który działa tylko po ustawieniu stałych (sekcja „Czas sesji”). Wszystkie haki rejestruje
`configure()` na `plugins_loaded`. Nazwy filtrów i kody błędów sprawdzone w kodzie WordPressa 7.1.3.

## Haki aktywne

### XML-RPC

| Hak | Co robi |
|---|---|
| `plugins_loaded` (w `configure()`) | żądanie do `xmlrpc.php` (także `?rsd`) kończy się kodem 403 i tekstem „XML-RPC jest wyłączone.”, zanim rdzeń zbuduje serwer XML-RPC |
| `wp_headers` (filtr) | bez nagłówka `X-Pingback`, który rdzeń wysyła na wpisach z otwartymi pingami |

Z XML-RPC korzystają aplikacja mobilna WordPressa, Jetpack i pingbacki; strona żadnego z nich nie używa.
`xmlrpc.php` przyjmuje próby logowania (także wiele naraz w `system.multicall`), więc to typowy cel ataków na
hasła. Wtyczka zatrzymuje żądanie po załadowaniu WordPressa; blokada na serwerze (LiteSpeed) oszczędza też to
ładowanie.

### Wyliczanie autorów

| Hak | Co robi |
|---|---|
| `request` (filtr) | na stronie (nie w panelu) zapytanie z `author` albo `author_name` daje 404: `/?author=N` (rdzeń przekierowałby na `/author/slug/`), archiwa `/author/slug/`, ich kanały i wyszukiwanie z `&author=N` |
| `author_link` (filtr) | na stronie (nie w panelu) link do archiwum autora prowadzi na stronę główną, więc motyw, widżety i bloki nie zdradzają sluga |
| `rest_endpoints` (filtr) | dla niezalogowanych nie ma tras `/wp/v2/users…` (także przez `?rest_route=`): odpowiedź 404 `rest_no_route`. Zalogowani mają je dalej, uprawnienia sprawdza rdzeń |
| `oembed_response_data` (filtr) | odpowiedź oEmbed podaje nazwę i adres strony zamiast `author_name` i `author_url` autora (tak jak rdzeń dla wpisów bez autora) |
| `wp_sitemaps_add_provider` (filtr) | bez mapy użytkowników: `wp-sitemap-users-*.xml` daje 404 i nie ma jej w `wp-sitemap.xml` |
| `comment_class` (filtr) | bez klasy `comment-author-{slug}` przy komentarzach zalogowanych użytkowników |

Slug autora (`user_nicename`) jest zwykle taki sam jak login, więc każde z tych miejsc podaje atakującemu
połowę danych do logowania.

### Logowanie i reset hasła

| Hak | Co robi |
|---|---|
| `wp_login_errors` (filtr) | komunikaty `invalid_username`, `invalid_email` i `incorrect_password` zastępuje jeden: „Błąd: nieprawidłowa nazwa użytkownika, adres e-mail lub hasło.” |
| `lostpassword_post` (akcja) | reset hasła na `wp-login.php` dla nieznanego loginu lub e-maila kończy się tym samym przekierowaniem (302 na `wp-login.php?checkemail=confirm` albo `redirect_to`) co dla istniejącego konta |

Puste pola dają dalej komunikaty rdzenia („The username field is empty.” itd.), bo niczego nie zdradzają.
Reset hasła wywołany poza `wp-login.php` (np. z listy użytkowników w panelu) działa bez zmian.

### Wersja WordPressa

| Hak | Co robi |
|---|---|
| `the_generator` (filtr) | pusty tekst zamiast `<meta name="generator" content="WordPress X.Y.Z">` w `<head>` i `<generator>` w kanałach; obejmuje każde miejsce, w którym rdzeń wypisuje wersję przez `the_generator()` |

Wersja jest dalej w adresach zasobów rdzenia (`?ver=`), bo od niej zależy odświeżanie cache przeglądarki.

### Hasła aplikacji

| Hak | Co robi |
|---|---|
| `wp_is_application_passwords_available` (filtr) | `false`: rdzeń nie przyjmuje logowania hasłem aplikacji (Basic Auth w REST API i XML-RPC), nie pokazuje sekcji „Hasła aplikacji” w profilu i nie podaje adresu autoryzacji aplikacji w indeksie REST API |

Hasło aplikacji to dodatkowe stałe dane logowania do konta, które omijają formularz logowania (także wyzwanie
Cloudflare i 2FA). Strona z nich nie korzysta: żadna aplikacja ani usługa nie łączy się z nią w ten sposób.
Zalogowany w przeglądarce użytkownik korzysta z REST API dalej (ciasteczko i nonce). Sprawdzone na WordPressie
7.1.3: zapytanie do `/wp-json/wp/v2/settings` z istniejącym hasłem aplikacji administratora daje 200 bez
wtyczki i 401 `rest_forbidden` z wtyczką. Wcześniej utworzone hasła zostają w bazie, ale nie działają.

### Czas sesji

| Hak | Co robi |
|---|---|
| `auth_cookie_expiration` (filtr, priorytet 99, tylko gdy ustawiono stałą) | skraca czas sesji kont z uprawnieniem `edit_posts`: stała `DSS_WP_HARDENING_ADMIN_SESSION_HOURS` dla logowania bez „Zapamiętaj mnie”, `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS` dla logowania z nim |

Stałe są w godzinach i pochodzą zwykle z `.env` ([INSTALACJA.md](INSTALACJA.md#czas-sesji-kont-edytorskich)).
Reguły:

- **Kogo dotyczy:** kont z uprawnieniem `edit_posts`: administrator, redaktor, autor i współpracownik. Konta
  bez niego (subskrybent, klient sklepu) mają czas sesji z WordPressa, czyli 2 dni bez „Zapamiętaj mnie” i 14
  dni z nim.
- **Tylko skraca:** wartość dłuższa niż ta z WordPressa niczego nie zmienia. Stała, której nie ma, albo której
  wartość nie jest dodatnią liczbą całkowitą (`0`, liczba ujemna, ułamek, tekst niebędący liczbą), jest
  pomijana. Gdy żadna z
  dwóch nie jest ustawiona, wtyczka nie rejestruje filtra.
- **Czas liczy się od logowania**, nie od ostatniej aktywności: WordPress nie przedłuża sesji przy pracy.
  Po jej końcu prosi o ponowne zalogowanie. Czas sesji jest zapisany w bazie przy logowaniu, więc trwająca
  sesja zachowuje czas, z jakim powstała; nowa wartość działa od następnego logowania.
- **Karencja rdzenia:** żądania zapisu (POST) i ajax są przyjmowane do godziny po końcu sesji. Wtyczka jej nie
  zmienia, więc najdłuższy czas dla zapisów to czas sesji plus godzina.
- **Ciasteczko w przeglądarce:** bez „Zapamiętaj mnie” jest sesyjne (znika po zamknięciu przeglądarki), z nim
  trwałe. Trwałe ciasteczko przeglądarka trzyma o 12 godzin dłużej niż wynosi czas sesji (zapas rdzenia na
  karencję), ale serwer odrzuca sesję po czasie sesji.
- **Zmiana własnego hasła w profilu** zachowuje oba czasy. Rdzeń rozpoznaje wtedy „Zapamiętaj mnie” po tym, że
  ciasteczko żyje dłużej niż czas sesji bez niego, więc `DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS` ma być
  większe od `DSS_WP_HARDENING_ADMIN_SESSION_HOURS`.

Skradzione ciasteczko działa do końca sesji albo do wylogowania, które usuwa token z bazy. Czas sesji skraca to
okno, ale go nie zamyka: nie zastępuje 2FA, aktualizacji ani wylogowania po pracy.

Sprawdzone na WordPressie 7.1.3 przez prawdziwe logowanie na `wp-login.php` (czas sesji z `session_tokens`):
przy 4 i 12 godzinach administrator, redaktor, autor i współpracownik dostają 4 h bez „Zapamiętaj mnie” i 12 h
z nim, subskrybent i rola z samym uprawnieniem `read` 48 h i 336 h, tyle samo co bez wtyczki. Nie sprawdzono
roli klienta WooCommerce (instalacja próbna nie ma WooCommerce) ani zamiany `.env` na stałe w DSS WP Manage.

## Czego wtyczka nie zasłania

- **Nazwa wyświetlana autora** (`display_name`) w kanałach (`<dc:creator>`) i tam, gdzie wypisuje ją motyw.
  Nie jest loginem, jeśli w profilu użytkownika ustawisz inną nazwę wyświetlaną niż login.
- **ID autora** w REST API wpisów (`author`). Bez trasy `/wp/v2/users` ID nie prowadzi do loginu.
- **Różnica czasu odpowiedzi** przy logowaniu i resecie hasła dla istniejącego i nieistniejącego konta.

## Czego wtyczka nie robi

| Temat | Gdzie |
|---|---|
| nagłówki bezpieczeństwa HTTP, blokada plików i PHP w katalogu uploadu | `.htaccess` na serwerze |
| HTTPS, HSTS, reguły WAF, limit prób logowania | Cloudflare |
| wirtualne łatki na luki we wtyczkach | Patchstack |
| zbędne zasoby i linki w `<head>` (emoji, RSD, RSS) | `dss-wp-cleanup` |
| dane SEO | `dss-wp-seo` |

Ustawienia serwera, Cloudflare i Patchstacka dla strony opisuje repo `dss-wp-site`: `docs/BEZPIECZENSTWO.md` i
`docs/CLOUDFLARE.md`.
