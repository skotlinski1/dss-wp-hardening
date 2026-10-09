# Audyt 2FA kluczem sprzętowym: Two Factor + WebAuthn Provider

Planowane logowanie dwuetapowe dla kont administracyjnych tej strony to klucz sprzętowy (FIDO2/WebAuthn,
np. YubiKey) jako jedyny drugi składnik, a do tego kody zapasowe na wypadek zgubienia klucza. Obsługują to
dwie wtyczki z wordpress.org: **Two Factor** (logowanie dwuetapowe, kody zapasowe) i **WebAuthn Provider for
Two Factor** (klucze sprzętowe). Ten dokument spisuje, co w ich kodzie sprawdzono, jedną potwierdzoną słabość
i ustawienia, przy których układ jest bezpieczny. Kod samej wtyczki `dss-wp-hardening` tego nie zawiera;
propozycja kodu jest w sekcji „Jak tego bezpiecznie używać”.

Zakres tej wtyczki to informacje i wejścia, które pomagają atakującemu (sekcja 5
[`.github/CONTRIBUTING.md`](../.github/CONTRIBUTING.md#5-zakres-i-podział-między-wtyczki)). Wymuszenie 2FA i
ograniczenie metod dla administratorów mieści się w tym zakresie: zamyka wejście do panelu. Dlatego osłona
logowania dwuetapowego należy do `dss-wp-hardening`, a nie do motywu ani do wtyczki odchudzającej.

## Wtyczki i wersje

| Wtyczka | Wersja | Testowana przez autora do | Uwaga |
|---|---|---|---|
| [Two Factor](https://wordpress.org/plugins/two-factor/) | 0.17.0 | WordPress 6.9 | społeczność WordPressa, ponad 100 000 instalacji |
| [WebAuthn Provider for Two Factor](https://wordpress.org/plugins/two-factor-provider-webauthn/) | 2.6.1 | WordPress 6.9.4 | jeden autor, ponad 1000 instalacji; biblioteka `madwizard/webauthn` 1.0.0 |

Obie są testowane przez autorów tylko do WordPressa 6.9, a strona ma 7.1.3. Próba (niżej) przeszła na 7.1.3
bez błędów PHP, ale to nie zastępuje śledzenia aktualizacji obu wtyczek.

## Co i jak sprawdzono

- **Kod:** przeczytany kod obu wtyczek w podanych wersjach i kod rdzenia WordPressa 7.1.3, na którym biegła
  próba (`wp-includes/`, `wp-login.php`).
- **Próba w przeglądarce:** WordPress 7.1.3, PHP 8.3, MariaDB, HTTPS na `localhost`, Two Factor 0.17.0 i
  WebAuthn Provider 2.6.1 aktywne. Klucz sprzętowy zastąpił wirtualny klucz WebAuthn z narzędzi Chromium
  (`WebAuthn.addVirtualAuthenticator`, CTAP2, z weryfikacją użytkownika). Prawdziwego YubiKeya w próbie nie
  było, więc fizyczne cechy klucza (PIN na urządzeniu, odporność na klonowanie) są niesprawdzone — zob.
  „Niesprawdzone”.

## Two Factor 0.17.0

### Co działa dobrze

- **Dwie zapory przy logowaniu.** Po poprawnym haśle, gdy konto ma 2FA, filtr `authenticate` (priorytet 31)
  wyłącza wysyłanie ciasteczek logowania (`send_auth_cookies` → `__return_false`), a akcja `wp_login`
  (priorytet `PHP_INT_MAX`) kasuje utworzoną sesję, czyści ciasteczka i pokazuje formularz drugiego etapu.
  Nawet gdyby inna wtyczka przerwała logowanie wcześniej, przeglądarka nie dostanie ciasteczka.
- **Jednorazowy klucz między hasłem a drugim etapem.** 32 losowe bajty (`random_bytes(32)`), w bazie tylko
  skrót (`wp_hash` z kluczem `nonce`), ważny 10 minut, porównanie odporne na pomiar czasu (`hash_equals`), a
  po każdej nieudanej próbie wydawany jest nowy.
- **Logowanie hasłem przez XML-RPC i REST jest zablokowane** dla kont z 2FA (`filter_authenticate` zwraca
  `WP_Error` dla żądań API, o ile logowanie API nie zostało dla konta włączone osobno).
- **Zmiana ustawień 2FA wymaga świeżego potwierdzenia.** `current_user_can_update_two_factor_options()`
  przepuszcza tylko sesję potwierdzoną drugim składnikiem w ostatnich 10 minutach (przy zapisie 20). Starsza
  sesja musi potwierdzić się ponownie.
- **Kody.** Kody zapasowe: domyślnie 10 sztuk, 8 cyfr, w bazie jako skrót `wp_hash_password`, każdy działa
  raz. Kod e-mail: skrót, jednorazowy, ważny 15 minut. TOTP: 6 cyfr, ochrona przed ponownym użyciem tego
  samego kroku czasu (zapamiętany ostatni udany krok).
- **Reset hasła po 30 błędnych kodach drugiego etapu.** Wtyczka traktuje wtedy hasło jako wykradzione, resetuje
  je i wysyła powiadomienie.
- **Przejście na e-mail nie jest ciche przy działającej metodzie.** Wymuszenie e-maila zachodzi tylko wtedy,
  gdy **żadna** z zapisanych metod konta nie jest już zarejestrowana (`two_factor_fallback_provider_for_user`,
  domyślnie `Two_Factor_Email`). Gdy konto ma włączone kody zapasowe, zostaje mu działająca metoda, więc do
  przejścia nie dochodzi. Nieznana albo niedostępna metoda zapasowa kończy się błędem `no_available_2fa_methods`
  (odmowa logowania), a nie logowaniem jednoskładnikowym.

### Słabe miejsca

1. **Najsłabsza włączona metoda decyduje (konfiguracja).** Przy logowaniu metodę można wskazać w żądaniu
   (`$_REQUEST['provider']`), a pozostałe włączone metody są pod „Having problems?”. Jeśli administrator ma
   klucz i dodatkowo e-mail albo TOTP, to ktoś z hasłem i dostępem do skrzynki ominie klucz. Rozwiązanie w
   sekcji „Jak tego bezpiecznie używać”: dla administratorów tylko klucz i kody zapasowe.
2. **Limit prób kodu liczy się na konto, nie na adres IP.** Ktoś, kto zna hasło, może opóźnić logowanie
   właściciela (przerwa rośnie `2^n`, do 15 minut) albo wymusić reset hasła. Reset hasła po wycieku to i tak
   dobra reakcja, a przy kluczu sprzętowym nie ma czego zgadywać.
3. **Limit prób kodu nie jest szczelny przy żądaniach równoległych (kod, potwierdzone próbą).** Licznik
   błędnych prób to odczyt i zapis osobnymi zapytaniami bez blokady, więc kilka żądań wysłanych naraz
   potrafi sprawdzić po kilka kodów na jeden klucz logowania i zgubić część zliczeń, przez co wydłużanie
   przerw i reset hasła zaczynają działać później. Na próbie (Apache) przy 5–30 jednoczesnych żądaniach z
   jednym kluczem logowania sprawdzone zostały 2–3 kody zamiast jednego. Skutek: szybsze zgadywanie krótkiego
   kodu TOTP przez kogoś, kto zna hasło. **Przy kluczu sprzętowym bez znaczenia** (podpisu nie da się zgadnąć),
   a przy 8-cyfrowych kodach zapasowych pomijalne. Dodatkowo ogranicza to reguła Cloudflare na
   `/core/wp-login.php` (formularz 2FA też idzie na ten adres). Ocena: niskie.
4. **Sekret TOTP w bazie jawnym tekstem** (`_two_factor_totp_key`). Kto odczyta bazę, może generować kody.
   Dotyczy tylko TOTP; dla administratorów TOTP nie używamy.

Nie znaleziono w kodzie drogi do wejścia bez hasła albo bez drugiego składnika.

## WebAuthn Provider 2.6.1

### Co działa dobrze

- **Sprawdzenie odpowiedzi klucza** wykonuje biblioteka `madwizard/webauthn` 1.0.0: podpis, dane klienta,
  adres strony, domena klucza (RP ID), obecność użytkownika i zgodność z wyzwaniem.
- **Adres i domena strony** pochodzą z `home_url()` i `COOKIE_DOMAIN`, więc klucz zarejestrowany na innej
  domenie tu nie zadziała. Klucz działa tylko przez HTTPS.
- **Klucze są przypisane do konta** (tabela `webauthn_credentials` z uchwytem użytkownika); klucz innego
  konta nie przejdzie.
- **Wyzwanie jest jednorazowe:** kontekst uwierzytelnienia jest kasowany z meta użytkownika po każdej próbie,
  także nieudanej (blok `finally`).
- **Luka CVE-2026-11883** (obejście 2FA, wersje przed 2.5.6) jest w tej wersji poprawiona.

### Próba w Chromium

| Próba | Wynik |
|---|---|
| rejestracja klucza w profilu | działa |
| logowanie hasłem i kluczem | wpuszczony do panelu |
| logowanie kluczem spoza konta (inny klucz wirtualny) | odrzucone |
| podrobiony podpis (zmieniony bajt) | odrzucone |
| powtórzenie przechwyconej odpowiedzi klucza ze starym kluczem logowania | przekierowanie na stronę główną, bez zalogowania |
| to samo z nowym, ważnym kluczem logowania (wyzwanie już inne) | odrzucone |
| ciasteczko logowania po nieudanych próbach | brak |

### Słabość: dodanie klucza bez świeżego potwierdzenia 2FA (potwierdzona próbą)

Cztery akcje AJAX wtyczki (`webauthn_preregister`, `webauthn_register`, `webauthn_delete_key`,
`webauthn_rename_key`) sprawdzają tylko nonce i uprawnienie `edit_user`. **Nie** sprawdzają
`Two_Factor_Core::current_user_can_update_two_factor_options()`, a po udanej akcji same zapisują w sesji
znacznik świeżego 2FA (`update_current_user_session(['two-factor-login' => time()])`). To omija regułę Two
Factor, która innym drogom (REST kodów zapasowych, pole nazwy klucza w profilu) każe potwierdzić 2FA w
ostatnich 10–20 minutach.

Próba w sesji postarzonej o godzinę (symulacja przejętego ciasteczka zalogowanego administratora):

- nowe kody zapasowe przez REST → **500 `revalidation_required`**, pole nazwy klucza w profilu wyłączone
  (blokada Two Factor działa);
- dodanie obcego klucza przez `admin-ajax.php` → **przeszło** (klucz zapisany w bazie);
- nowe kody zapasowe przez REST zaraz potem → **200, 10 kodów** (dodanie klucza odświeżyło znacznik 2FA);
- logowanie hasłem i obcym kluczem → **wpuszczony**.

Do wykorzystania potrzebna jest przejęta sesja zalogowanego administratora (skradzione ciasteczko, XSS albo
niezablokowany komputer) — czyli dokładnie to, przed czym chroni ponowne potwierdzenie w Two Factor. Skutek:
dostęp kończący się wraz z sesją staje się trwały, jeśli atakujący zna też hasło. To nie jest wejście z
zewnątrz; Cloudflare tego nie zatrzyma, bo to zwykłe żądanie zalogowanego użytkownika. Zamknięcie: osłona w
`dss-wp-hardening` (niżej).

### Drobne uwagi

- **Domyślnie klucz nie musi pytać o PIN** (`user_verification_requirement` = `preferred`). Skradziony klucz
  razem z hasłem wystarczy do wejścia. Ustawienie `required` wymaga PIN-u na urządzeniu.
- **Licznik podpisów:** gdy klucz zgłasza licznik 0 albo zapisany licznik wynosi 0, biblioteka nie sprawdza
  kradzieży po liczniku (zgodnie ze specyfikacją WebAuthn). Wiele kluczy sprzętowych nie zwiększa licznika;
  YubiKeya i tak trudno sklonować.
- **`unserialize()` kontekstu bez `allowed_classes`.** Dane serializuje i odczytuje sama wtyczka z meta
  użytkownika, więc ryzyko jest niskie, dopóki nikt nie pisze do tej meta spoza wtyczki.
- **Nazwa użytkownika WebAuthn to login konta** (`user_login`), chyba że filtrem zmieni się na `user_nicename`.
  Nazwa zapisuje się w kluczu; przy publicznym loginie warto to rozważyć.

## Czego żadne 2FA w WordPressie nie załatwi

- Wtyczki z luką, która pozwala działać bez logowania (na to jest zewnętrzny skaner, np. Patchstack —
  [`BEZPIECZENSTWO.md`](https://github.com/skotlinski1/dss-wp-site) w repo strony).
- Kradzieży ciasteczka już zalogowanej sesji (częściowo łagodzi to osłona niżej).
- Dostępu do bazy albo serwera.

## Jak tego bezpiecznie używać

### Ustawienia bez kodu (Ustawienia → TwoFactor WebAuthn)

- **User Verification Requirement → `required`.** Klucz zawsze pyta o PIN; YubiKey musi mieć ustawiony PIN
  FIDO2. Skradziony klucz bez PIN-u nic nie daje.
- **Authenticator Attachment → `cross-platform`.** Działają tylko klucze zewnętrzne, bez Windows Hello i
  Touch ID.

### Propozycja kodu w `dss-wp-hardening` (nie wdrożona)

Poniższe działa tylko przy aktywnej wtyczce Two Factor (sprawdzenie `class_exists('Two_Factor_Core')`).
Punkty 1–3 są bezpieczne od razu; punkt 4 może odciąć panel i dlatego ma być opcją nieaktywną do włączenia
dopiero po dodaniu kluczy.

1. **Osłona czterech akcji `wp_ajax_webauthn_*`.** Na priorytecie 0, zanim zadziała handler wtyczki, przerwij
   żądanie, gdy `Two_Factor_Core::current_user_can_update_two_factor_options('save')` zwraca fałsz. Zamyka to
   słabość opisaną wyżej. Pierwszy klucz da się dalej dodać na koncie bez 2FA, bo wtedy ta funkcja przepuszcza.
   **Sprawdzone próbą kandydata** (osłona jako tymczasowy MU-plugin): w sesji postarzonej o godzinę wszystkie
   cztery akcje zwróciły 403, liczba kluczy nie wzrosła, a kody zapasowe przez REST dalej dawały
   `revalidation_required`; pierwszy klucz na koncie bez 2FA i drugi klucz w świeżej sesji przeszły; ponowne
   potwierdzenie 2FA (`wp-login.php?action=revalidate_2fa`) znów otwierało dodawanie kluczy.
2. **Dla administratorów tylko klucz i kody zapasowe** (filtr `two_factor_providers_for_user`): bez e-maila i
   TOTP. Usuwa to słabość Two Factor z wyborem słabszej metody w żądaniu. **Sprawdzone próbą** przez
   równoważne ograniczenie w ustawieniach Two Factor: na stronie logowania zostają tylko klucz i „Use a
   recovery code”, a `provider=Two_Factor_Email` w adresie nie pokazuje pola kodu e-mail.
3. **Bez zapasowego logowania e-mailem** (filtr `two_factor_fallback_provider_for_user`): przy włączonych
   kodach zapasowych i tak nie zachodzi, ale zamyka drogę na wszelki wypadek.
4. **Wymuszenie 2FA dla administratorów** (filtr `two_factor_is_required_for_user`): konto administratora bez
   działającej metody 2FA nie zaloguje się do panelu. Two Factor 0.17.0 odrzuca wtedy logowanie, więc to może
   **odciąć panel** — dlatego opcja nieaktywna, włączana po dodaniu kluczy każdemu administratorowi.

Przykład obejmuje administratorów (uprawnienie `manage_options`); klienci sklepu i inne role zostają bez
zmian. Zmień uprawnienie w `limit_admin_two_factor_providers()` (i w zakomentowanym
`require_two_factor_for_admins()`), jeśli ograniczenie ma dotyczyć też innych ról z dostępem do panelu (np.
redaktorów).

Przykładowy kod — **nie jest w `dss-wp-hardening.php`**, pokazany w konwencji repo. Funkcję
`configure_two_factor()` dołączyłoby się do `configure()` obok pozostałych `configure_*()`:

```php
/** Rejestruje haki; każdy temat ma własną funkcję. */
function configure(): void
{
	configure_xmlrpc();
	configure_authors();
	configure_login();
	configure_version();
	configure_app_passwords();
	configure_two_factor();
}

/**
 * Logowanie dwuetapowe (Two Factor + WebAuthn Provider): osłona akcji dodawania kluczy i ograniczenie metod
 * dla administratorów. Działa tylko przy aktywnej wtyczce Two Factor; bez niej nic nie rejestruje.
 */
function configure_two_factor(): void
{
	if (!class_exists('Two_Factor_Core')) {
		return;
	}

	// WebAuthn Provider pozwala dodać, usunąć i przemianować klucz bez świeżego potwierdzenia 2FA, którego
	// Two Factor wymaga przy innych zmianach ustawień. Osłona na priorytecie 0 (przed handlerem wtyczki)
	// przerywa te cztery akcje w sesji bez świeżego 2FA.
	$key_actions = ['webauthn_preregister', 'webauthn_register', 'webauthn_delete_key', 'webauthn_rename_key'];
	foreach ($key_actions as $action) {
		add_action('wp_ajax_' . $action, __NAMESPACE__ . '\\guard_two_factor_change', 0);
	}

	// Konta administracyjne: tylko klucz sprzętowy i kody zapasowe (bez e-maila i TOTP), więc przy logowaniu
	// nie da się wybrać słabszej metody.
	add_filter('two_factor_providers_for_user', __NAMESPACE__ . '\\limit_admin_two_factor_providers', 10, 2);
	// Bez cichego przejścia na kody e-mail, gdyby metoda konta zniknęła.
	add_filter('two_factor_fallback_provider_for_user', __NAMESPACE__ . '\\disable_email_fallback');
}

/**
 * Przerywa zmianę kluczy WebAuthn w sesji bez świeżego potwierdzenia 2FA (ta sama reguła, której Two Factor
 * używa przy zmianie ustawień). Konto bez 2FA ta funkcja przepuszcza, więc pierwszy klucz da się dodać.
 */
function guard_two_factor_change(): void
{
	if (\Two_Factor_Core::current_user_can_update_two_factor_options('save')) {
		return;
	}

	wp_send_json_error('Potwierdź ponownie logowanie dwuetapowe i spróbuj jeszcze raz.', 403);
}

/**
 * Zostawia administratorowi wśród metod 2FA tylko klucz sprzętowy i kody zapasowe.
 *
 * @param mixed $providers Dostawcy 2FA (obiekty pod kluczem nazwy klasy).
 * @param mixed $user      Użytkownik, którego dotyczą.
 * @return mixed
 */
function limit_admin_two_factor_providers($providers, $user)
{
	if (!is_array($providers) || !$user instanceof \WP_User || !user_can($user, 'manage_options')) {
		return $providers;
	}

	$allowed = ['TwoFactor_Provider_WebAuthn', 'Two_Factor_Backup_Codes'];

	return array_intersect_key($providers, array_flip($allowed));
}

/**
 * Wyłącza kody e-mail jako metodę zapasową: pusta wartość oznacza brak metody, więc Two Factor odmawia
 * logowania zamiast wpuścić na jeden składnik.
 *
 * @param mixed $provider Klucz metody zapasowej (pominięty).
 * @return string
 */
function disable_email_fallback($provider): string
{
	return '';
}

/* Wymuszenie 2FA dla kont administracyjnych: bez działającej metody 2FA konto nie zaloguje się do panelu
 * (Two Factor odrzuca wtedy logowanie). Dopisz też wywołanie filtra w configure_two_factor().
 * Włącz, jeśli: każdy administrator ma już skonfigurowany klucz sprzętowy (inaczej odetniesz sobie dostęp). */
// add_filter('two_factor_is_required_for_user', __NAMESPACE__ . '\\require_two_factor_for_admins', 10, 2);
//
// function require_two_factor_for_admins($required, $user)
// {
// 	if ($user instanceof \WP_User && user_can($user, 'manage_options')) {
// 		return true;
// 	}
//
// 	return $required;
// }
```

Klucze dostawców to nazwy klas: `TwoFactor_Provider_WebAuthn` (WebAuthn Provider), `Two_Factor_Backup_Codes`,
`Two_Factor_Email`, `Two_Factor_Totp` (Two Factor). Osłonę z `guard_two_factor_change()` sprawdzono próbą
kandydata w tej sesji (wyżej); pozostałych funkcji nie uruchomiono jako kodu wtyczki — ograniczenie metod
sprawdzono równoważnym ustawieniem Two Factor.

### Praktyka z kluczami

- Zarejestruj **dwa klucze** (główny i zapasowy w bezpiecznym miejscu) i wydrukuj kody zapasowe z Two Factor;
  trzymaj je poza komputerem.
- WebAuthn jest przypisany do domeny i działa tylko przez HTTPS. Klucz z produkcji nie zadziała na Macu ani
  na stagingu — tam rejestruje się go osobno (każde środowisko ma własną bazę).
- Dodatkowa warstwa na później: Cloudflare Access może zasłonić `/core/wp-login.php` i panel osobnym
  logowaniem, z wyjątkiem dla `admin-ajax.php` (korzysta z niego front). To uzupełnienie, nie zamiennik 2FA.

## Niesprawdzone

- Fizyczny YubiKey: próba używała wirtualnego klucza, więc PIN na urządzeniu i odporność na klonowanie nie
  były sprawdzone.
- Zachowanie obu wtyczek na WordPressie nowszym niż 6.9 poza tym, co pokazała próba na 7.1.3.
- Zgłoszenie słabości autorowi WebAuthn Provider: wysyłka na zewnątrz, do decyzji opiekuna.
