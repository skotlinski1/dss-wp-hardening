<?php
/**
 * Plugin Name: DSS — Hardening
 * Description: Utwardzenie WordPressa: wyliczanie autorów, wersja WordPressa, XML-RPC. MU-plugin.
 * Version: 0.5.0
 * License: GPL-2.0-or-later
 *
 * Własny MU-plugin: zakłada aktualne stabilne WordPress (7.0+) oraz PHP 8.3+.
 * Opis haków i instalacja: README.md i docs/ w repozytorium.
 *
 * Zakres (SRP): informacje i wejścia, które pomagają atakującemu: wyliczanie autorów (/?author=N,
 * archiwa autorów, REST /wp/v2/users, author_name w oEmbed, wp-sitemap-users-*.xml, komunikaty
 * logowania i resetu hasła), wersja WordPressa (meta generator), XML-RPC, hasła aplikacji i czas sesji
 * kont edytorskich. Pytanie
 * kontrolne: „dlaczego usuwamy?”. Bo pomaga atakującemu: tutaj. Bo zbędne albo ciężkie: dss-wp-cleanup.
 * Nagłówki bezpieczeństwa HTTP i limity logowań ustawia serwer, nie ta wtyczka.
 *
 * Zasada: nic tu nie odcina zalogowanych użytkowników od panelu ani od REST API. Wyjątek to czas sesji kont
 * edytorskich, który działa tylko po ustawieniu stałych.
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

/** Rejestruje haki; każdy temat ma własną funkcję. */
function configure(): void
{
	configure_xmlrpc();
	configure_authors();
	configure_login();
	configure_version();
	configure_app_passwords();
	configure_sessions();
}

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

/**
 * Wersja WordPressa: meta `generator` w `<head>` i znacznik `<generator>` w kanałach. Filtr obejmuje
 * wszystkie miejsca, w których rdzeń ją wypisuje przez the_generator().
 */
function configure_version(): void
{
	add_filter('the_generator', '__return_empty_string');
}

/**
 * Hasła aplikacji: strona ich nie używa (żadna aplikacja ani usługa nie łączy się z REST API ani XML-RPC
 * hasłem aplikacji), a każde takie hasło to dodatkowe stałe dane logowania do konta, które omijają formularz
 * logowania. Bez nich rdzeń nie przyjmuje Basic Auth z hasłem aplikacji i nie pokazuje sekcji w profilu.
 */
function configure_app_passwords(): void
{
	add_filter('wp_is_application_passwords_available', '__return_false');
}

/**
 * Czas sesji kont z uprawnieniem `edit_posts` (administrator, redaktor, autor, współpracownik): skradzione
 * ciasteczko takiego konta działa tylko do końca sesji. Dwie stałe w godzinach (zwykle z `.env`):
 * DSS_WP_HARDENING_ADMIN_SESSION_HOURS dla logowania bez „Zapamiętaj mnie” i
 * DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS dla logowania z nim. Bez stałej (albo z wartością niedodatnią) dany
 * czas zostaje taki, jak ustawia WordPress. Konta bez `edit_posts` (subskrybent, klient sklepu) nie są
 * objęte.
 */
function configure_sessions(): void
{
	if (session_hours('DSS_WP_HARDENING_ADMIN_SESSION_HOURS') === 0
		&& session_hours('DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS') === 0) {
		return;
	}

	// Późny priorytet: skraca także wartość ustawioną przez inne filtry. Filtr działa tylko przy logowaniu.
	add_filter('auth_cookie_expiration', __NAMESPACE__ . '\\shorten_session', 99, 3);
}

/**
 * Skraca czas sesji konta z uprawnieniem `edit_posts`. Nigdy go nie wydłuża: stała większa od wartości
 * ustawionej przez WordPress niczego nie zmienia.
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
	$hours = session_hours(
		$remember ? 'DSS_WP_HARDENING_ADMIN_REMEMBER_HOURS' : 'DSS_WP_HARDENING_ADMIN_SESSION_HOURS'
	);
	if ($hours === 0 || !user_can((int) $user_id, 'edit_posts')) {
		return $length;
	}

	return min((int) $length, $hours * HOUR_IN_SECONDS);
}

/**
 * Czyta stałą z liczbą godzin.
 *
 * @param string $constant Nazwa stałej.
 * @return int Liczba godzin albo 0, gdy stałej nie ma lub nie jest dodatnią liczbą całkowitą.
 */
function session_hours(string $constant): int
{
	if (!defined($constant)) {
		return 0;
	}
	$value = constant($constant);
	if (!is_int($value) && !is_string($value)) {
		return 0;
	}
	$hours = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

	return $hours === false ? 0 : $hours;
}
