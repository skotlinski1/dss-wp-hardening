# Działanie

Zakres (SRP): informacje i wejścia, które pomagają atakującemu. Podział między wtyczki DSS: sekcja 5
[`.github/CONTRIBUTING.md`](../.github/CONTRIBUTING.md#5-zakres-i-podział-między-wtyczki).

Zasada: nic tu nie odcina zalogowanych użytkowników od panelu ani od REST API. Wszystkie haki rejestruje
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
