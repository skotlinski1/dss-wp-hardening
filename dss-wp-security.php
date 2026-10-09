<?php
/**
 * Plugin Name: DSS — Security
 * Description: Utwardzenie WordPressa: wyliczanie autorów, wersja WordPressa, XML-RPC. MU-plugin.
 * Version: 0.1.0
 * License: GPL-2.0-or-later
 *
 * Własny MU-plugin: zakłada aktualne stabilne WordPress (7.0+) oraz PHP 8.3+.
 * Opis, instalacja i plan: README.md i docs/ w repozytorium.
 *
 * Zakres (SRP): informacje i wejścia, które pomagają atakującemu: wyliczanie autorów (/?author=N,
 * archiwa autorów, REST /wp/v2/users, author_name w oEmbed, wp-sitemap-users-*.xml, komunikaty
 * logowania i resetu hasła), wersja WordPressa (meta generator) i XML-RPC. Pytanie kontrolne:
 * „dlaczego usuwamy?”. Bo pomaga atakującemu: tutaj. Bo zbędne albo ciężkie: dss-wp-cleanup.
 * Nagłówki bezpieczeństwa HTTP i limity logowań ustawia serwer, nie ta wtyczka.
 */

declare(strict_types=1);

namespace DSS\Security;

if (!defined('ABSPATH')) {
	exit;
}

// Wyłącznik awaryjny: define('DSS_WP_SECURITY_DISABLED', true); w wp-config.php, przed wp-settings.php.
// Nic nie rejestruje i niczego nie zmienia w bazie. Służy do szybkiego wyłączenia i do porównań A/B.
if (defined('DSS_WP_SECURITY_DISABLED') && DSS_WP_SECURITY_DISABLED) {
	return;
}

// Po wczytaniu wszystkich wtyczek: haki rejestruje configure().
add_action('plugins_loaded', __NAMESPACE__ . '\\configure');

/** Rejestruje haki wtyczki. Na razie żadnych: wtyczka niczego nie zmienia. */
function configure(): void
{
}
