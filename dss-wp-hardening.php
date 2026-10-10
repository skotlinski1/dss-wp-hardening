<?php
/**
 * Plugin Name: DSS — Hardening
 * Description: Utwardzenie WordPressa: loginy, XML-RPC, hasła aplikacji, wersja, czas sesji. MU-plugin.
 * Version: 0.7.0
 * License: GPL-2.0-or-later
 *
 * Własny MU-plugin: zakłada aktualne stabilne WordPress (7.0+) oraz PHP 8.3+.
 * Opis haków i instalacja: README.md i docs/ w repozytorium.
 *
 * Zakres (SRP): informacje i wejścia, które pomagają atakującemu. Pytanie kontrolne: „dlaczego usuwamy?”.
 * Bo pomaga atakującemu: tutaj. Bo zbędne albo ciężkie: dss-wp-cleanup. Nagłówki bezpieczeństwa HTTP i
 * limity logowań ustawia serwer, nie ta wtyczka.
 *
 * Układ pliku (każdy dział ma w nim własny nagłówek, a configure() wywołuje go w tej samej kolejności):
 *   1. Ukrywanie loginów: autorzy (/?author=N, archiwa, REST /wp/v2/users, oEmbed, wp-sitemap-users-*.xml,
 *      klasy komentarzy, nazwa autora w kanałach RSS) oraz komunikaty logowania i resetu hasła.
 *   2. Wejścia omijające formularz logowania: XML-RPC i hasła aplikacji.
 *   3. Informacje o systemie: wersja WordPressa (meta generator).
 *   4. Sesje: czas sesji kont edytorskich.
 *
 * Zasada: nic tu nie odcina zalogowanych użytkowników od panelu ani od REST API. Wyjątek to czas sesji kont
 * edytorskich (domyślnie 4 godziny, a z „Zapamiętaj mnie” 12; stałe go zmieniają, a 0 wyłącza).
 */

declare(strict_types=1);

namespace DSS\Hardening;

if (!defined('ABSPATH')) {
	exit;
}

// Wyłącznik awaryjny: define('DSS_WP_HARDENING_DISABLED', true); w wp-config.php, przed wp-settings.php.
// Nic nie rejestruje i niczego nie zmienia w bazie. Służy do szybkiego wyłączenia i do porównań A/B.
if (defined('DSS_WP_HARDENING_DISABLED') && DSS_WP_HARDENING_DISABLED) {
	return;
}

// Po wczytaniu wszystkich wtyczek: haki rejestruje configure().
add_action('plugins_loaded', __NAMESPACE__ . '\\configure');

/** Rejestruje haki; każdy temat ma własną funkcję, tematy są pogrupowane jak działy tego pliku. */
function configure(): void
{
	// 1. Ukrywanie loginów.
	configure_authors();
	configure_login();

	// 2. Wejścia omijające formularz logowania.
	configure_xmlrpc();
	configure_app_passwords();

	// 3. Informacje o systemie.
	configure_version();

	// 4. Sesje.
	configure_sessions();
}

// ==================================================================================================
// 1. UKRYWANIE LOGINÓW
// Login (zwykle taki sam jak slug autora) to połowa danych do logowania. Tu są wszystkie miejsca, które
// zdradzają go niezalogowanemu: archiwa i linki autorów, REST API, oEmbed, mapa witryny, klasy komentarzy,
// nazwa autora w kanałach RSS, komunikaty logowania i resetu hasła.
// ==================================================================================================

// ---- 1a. Autorzy: archiwa, linki, REST, oEmbed, mapa witryny, komentarze, kanały RSS -------------

/**
 * Wyliczanie autorów: każde miejsce, które zdradza login (slug) albo listę użytkowników niezalogowanemu.
 * Zalogowani zachowują dostęp do REST API użytkowników (uprawnienia sprawdza rdzeń).
 */
function configure_authors(): void
{
	// /?author=N (rdzeń przekierowuje na /author/slug/), archiwa /author/slug/ i ich kanały: 404.
	add_filter('request', __NAMESPACE__ . '\\block_author_query');
	// Linki do tych archiwów (motyw, widżety, bloki) zdradzałyby slug i prowadziłyby do 404.
	add_filter('author_link', __NAMESPACE__ . '\\hide_author_link');

	add_filter('rest_endpoints', __NAMESPACE__ . '\\remove_user_endpoints');
	add_filter('oembed_response_data', __NAMESPACE__ . '\\hide_oembed_author');
	add_filter('wp_sitemaps_add_provider', __NAMESPACE__ . '\\remove_users_sitemap', 10, 2);

	// Klasa comment-author-{slug} przy komentarzach zalogowanych użytkowników.
	add_filter('comment_class', __NAMESPACE__ . '\\remove_comment_author_class');

	// Nazwa autora w kanałach RSS, RDF i Atom. Filtr dodaje dopiero żądanie kanału, więc zwykłe strony płacą
	// za to jednym sprawdzeniem flagi.
	add_action('template_redirect', __NAMESPACE__ . '\\hide_feed_author', 5);
}

/**
 * Zamienia zapytanie o autora na stronie (nie w panelu) na 404.
 *
 * @param mixed $vars Zmienne zapytania.
 * @return mixed
 */
function block_author_query($vars)
{
	if (is_admin() || !is_array($vars)) {
		return $vars;
	}
	if (!isset($vars['author']) && !isset($vars['author_name'])) {
		return $vars;
	}

	return ['error' => '404'];
}

/**
 * Zamienia link do archiwum autora na stronie (nie w panelu) na adres strony głównej.
 *
 * @param mixed $link Adres archiwum autora.
 * @return mixed
 */
function hide_author_link($link)
{
	return is_admin() ? $link : home_url('/');
}

/**
 * Usuwa trasy `/wp/v2/users` dla niezalogowanych.
 *
 * @param mixed $endpoints Trasy REST API.
 * @return mixed
 */
function remove_user_endpoints($endpoints)
{
	if (!is_array($endpoints) || is_user_logged_in()) {
		return $endpoints;
	}
	foreach (array_keys($endpoints) as $route) {
		if (str_starts_with((string) $route, '/wp/v2/users')) {
			unset($endpoints[$route]);
		}
	}

	return $endpoints;
}

/**
 * W odpowiedzi oEmbed podaje stronę zamiast autora (tak jak rdzeń dla wpisów bez autora).
 *
 * @param mixed $data Dane odpowiedzi oEmbed.
 * @return mixed
 */
function hide_oembed_author($data)
{
	if (!is_array($data)) {
		return $data;
	}
	$data['author_name'] = get_bloginfo('name');
	$data['author_url'] = get_home_url();

	return $data;
}

/**
 * Wyłącza mapę użytkowników `wp-sitemap-users-*.xml`.
 *
 * @param mixed  $provider Dostawca mapy.
 * @param string $name     Nazwa dostawcy.
 * @return mixed
 */
function remove_users_sitemap($provider, string $name)
{
	return $name === 'users' ? false : $provider;
}

/**
 * Usuwa klasę `comment-author-{slug}` z komentarza.
 *
 * @param mixed $classes Klasy komentarza.
 * @return mixed
 */
function remove_comment_author_class($classes)
{
	if (!is_array($classes)) {
		return $classes;
	}

	return array_values(
		array_filter($classes, static fn($class): bool => !str_starts_with((string) $class, 'comment-author-'))
	);
}

/** Przy żądaniu kanału podmienia nazwę autora na nazwę strony. */
function hide_feed_author(): void
{
	if (is_feed()) {
		add_filter('the_author', __NAMESPACE__ . '\\feed_site_name');
	}
}

/**
 * Nazwa strony zamiast nazwy wyświetlanej autora (`<dc:creator>` w RSS i RDF, `<author><name>` w Atom).
 * Kanały komentarzy podają autora komentarza, nie konto, więc ich to nie dotyczy.
 *
 * @param mixed $author Nazwa wyświetlana autora.
 * @return mixed
 */
function feed_site_name($author)
{
	return get_bloginfo('name');
}

// ---- 1b. Komunikaty logowania i resetu hasła -----------------------------------------------------

/**
 * Logowanie i reset hasła nie zdradzają, czy konto istnieje: jeden komunikat dla złego loginu, e-maila i
 * hasła, a reset hasła dla nieznanego konta kończy się tak samo jak dla istniejącego.
 */
function configure_login(): void
{
	add_filter('wp_login_errors', __NAMESPACE__ . '\\unify_login_errors');
	add_action('lostpassword_post', __NAMESPACE__ . '\\hide_unknown_account', 10, 2);
}

/**
 * Zastępuje komunikaty o nieznanym loginie, e-mailu i złym haśle jednym komunikatem.
 *
 * @param mixed $errors Błędy ekranu logowania.
 * @return mixed
 */
function unify_login_errors($errors)
{
	if (!$errors instanceof \WP_Error) {
		return $errors;
	}
	$found = false;
	foreach (['invalid_username', 'invalid_email', 'incorrect_password'] as $code) {
		if ($errors->get_error_message($code) !== '') {
			$errors->remove($code);
			$found = true;
		}
	}
	if ($found) {
		$errors->add(
			'invalid_credentials',
			'<strong>Błąd:</strong> nieprawidłowa nazwa użytkownika, adres e-mail lub hasło.'
		);
	}

	return $errors;
}

/**
 * Reset hasła na wp-login.php dla nieznanego konta: to samo przekierowanie co po wysłaniu e-maila.
 *
 * @param mixed $errors    Błędy żądania resetu.
 * @param mixed $user_data Znaleziony użytkownik albo false.
 */
function hide_unknown_account($errors, $user_data): void
{
	if ($user_data !== false || ($GLOBALS['pagenow'] ?? '') !== 'wp-login.php') {
		return;
	}
	if ($errors instanceof \WP_Error && $errors->get_error_message('empty_username') !== '') {
		return;
	}

	// Ten sam cel co w wp-login.php po udanym wysłaniu e-maila.
	$redirect_to = !empty($_REQUEST['redirect_to']) ? (string) wp_unslash($_REQUEST['redirect_to']) : '';
	wp_safe_redirect($redirect_to !== '' ? $redirect_to : 'wp-login.php?checkemail=confirm');
	exit;
}

// ==================================================================================================
// 2. WEJŚCIA OMIJAJĄCE FORMULARZ LOGOWANIA
// XML-RPC przyjmuje próby logowania (także wiele naraz), a hasło aplikacji to stałe dane logowania bez
// formularza, wyzwania Cloudflare i 2FA.
// ==================================================================================================

// ---- 2a. XML-RPC ---------------------------------------------------------------------------------

/**
 * XML-RPC: strona go nie używa (aplikacje mobilne i Jetpack nie są podłączone), a `xmlrpc.php` przyjmuje
 * próby logowania i pingbacki. Żądanie kończy się kodem 403, zanim rdzeń zbuduje serwer XML-RPC.
 */
function configure_xmlrpc(): void
{
	if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
		status_header(403);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'XML-RPC jest wyłączone.';
		exit;
	}

	// Nagłówek X-Pingback na wpisach wskazuje xmlrpc.php.
	add_filter('wp_headers', __NAMESPACE__ . '\\remove_pingback_header');
}

/**
 * Usuwa nagłówek `X-Pingback`.
 *
 * @param mixed $headers Nagłówki odpowiedzi.
 * @return mixed
 */
function remove_pingback_header($headers)
{
	if (is_array($headers)) {
		unset($headers['X-Pingback']);
	}

	return $headers;
}

// ---- 2b. Hasła aplikacji -------------------------------------------------------------------------

/**
 * Hasła aplikacji: strona ich nie używa (żadna aplikacja ani usługa nie łączy się z REST API ani XML-RPC
 * hasłem aplikacji), a każde takie hasło to dodatkowe stałe dane logowania do konta, które omijają formularz
 * logowania. Bez nich rdzeń nie przyjmuje Basic Auth z hasłem aplikacji i nie pokazuje sekcji w profilu.
 */
function configure_app_passwords(): void
{
	add_filter('wp_is_application_passwords_available', '__return_false');
}

// ==================================================================================================
// 3. INFORMACJE O SYSTEMIE
// Dane o instalacji, które pomagają dobrać atak.
// ==================================================================================================

/**
 * Wersja WordPressa: meta `generator` w `<head>` i znacznik `<generator>` w kanałach. Filtr obejmuje
 * wszystkie miejsca, w których rdzeń ją wypisuje przez the_generator().
 */
function configure_version(): void
{
	add_filter('the_generator', '__return_empty_string');
}

// ==================================================================================================
// 4. SESJE
// Czas sesji kont edytorskich: domyślnie 4 godziny, a z „Zapamiętaj mnie” 12. Stałe go zmieniają albo
// wyłączają (0).
// ==================================================================================================

/**
 * Czas sesji kont z uprawnieniem `edit_posts` (administrator, redaktor, autor, współpracownik): skradzione
 * ciasteczko takiego konta działa tylko do końca sesji. Domyślnie 4 godziny, a z „Zapamiętaj mnie” 12. Dwie
 * stałe w godzinach (zwykle z `.env`) zmieniają te wartości: DSS_WP_HARDENING_ADMIN_SESSION_HOURS dla
 * logowania bez „Zapamiętaj mnie” i DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS dla logowania z nim. Wartość 0
 * przywraca czas ustawiony przez WordPress (2 dni i 14 dni). Konta bez `edit_posts` (subskrybent, klient
 * sklepu) nie są objęte.
 */
function configure_sessions(): void
{
	if (admin_session_hours(false) === 0 && admin_session_hours(true) === 0) {
		return;
	}

	// Późny priorytet: skraca także wartość ustawioną przez inne filtry. Filtr działa tylko przy logowaniu.
	add_filter('auth_cookie_expiration', __NAMESPACE__ . '\\shorten_session', 99, 3);
}

/**
 * Skraca czas sesji konta z uprawnieniem `edit_posts`. Nigdy go nie wydłuża: wartość większa od ustawionej
 * przez WordPress niczego nie zmienia.
 *
 * @param mixed $length   Czas sesji w sekundach.
 * @param mixed $user_id  ID użytkownika.
 * @param mixed $remember Czy zaznaczono „Zapamiętaj mnie”.
 * @return mixed
 */
function shorten_session($length, $user_id = 0, $remember = false)
{
	if (!is_numeric($length)) {
		return $length;
	}
	$hours = admin_session_hours((bool) $remember);
	if ($hours === 0 || !user_can((int) $user_id, 'edit_posts')) {
		return $length;
	}

	return min((int) $length, $hours * HOUR_IN_SECONDS);
}

/**
 * Liczba godzin sesji kont edytorskich: stała, a bez niej wartość domyślna (4 albo 12).
 *
 * @param bool $remember Czy chodzi o logowanie z „Zapamiętaj mnie”.
 * @return int Liczba godzin; 0 przywraca czas WordPressa.
 */
function admin_session_hours(bool $remember): int
{
	return $remember
		? session_hours('DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS', 12)
		: session_hours('DSS_WP_HARDENING_ADMIN_SESSION_HOURS', 4);
}

/**
 * Czyta stałą z liczbą godzin.
 *
 * @param string $constant Nazwa stałej.
 * @param int    $default  Wartość, gdy stałej nie ma albo nie jest liczbą całkowitą od 0 w górę.
 * @return int Liczba godzin; 0 oznacza „bez skracania”.
 */
function session_hours(string $constant, int $default): int
{
	if (!defined($constant)) {
		return $default;
	}
	$value = constant($constant);
	if (!is_int($value) && !is_string($value)) {
		return $default;
	}
	$hours = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

	return $hours === false ? $default : $hours;
}
