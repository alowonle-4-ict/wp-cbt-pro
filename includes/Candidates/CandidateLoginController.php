<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

/**
 * A [wpcbtpro_login] shortcode for a branded candidate sign-in page — no
 * WordPress admin chrome, no "register" or "lost password for wp-admin"
 * links wp-login.php carries. The exam runtime's own "please log in"
 * prompt (ExamRuntimeController::renderLoginPrompt()) links here once an
 * institution has published a page with this shortcode, and falls back to
 * the ordinary wp_login_url() until then — nothing forces this page to
 * exist.
 *
 * Login is processed on template_redirect, not inside the shortcode
 * callback: by the time a shortcode runs (inside the_content, itself
 * inside the theme's page template), the page's HTML has already started
 * streaming, so wp_signon()'s auth cookie and any wp_safe_redirect() would
 * silently fail exactly the way admin_menu callbacks did in wp-admin
 * (§ same header-timing issue, frontend side). template_redirect fires
 * before the theme template outputs anything, the same role admin_init
 * plays for wp-admin.
 */
final class CandidateLoginController
{
    private const NONCE_ACTION = 'wpcbtpro_candidate_login';
    private const NONCE_FIELD = 'wpcbtpro_login_nonce';
    private const LOGIN_PAGE_OPTION = 'wpcbtpro_login_page_id';

    private ?string $pendingError = null;

    public function __construct(private readonly CurrentCandidateResolver $candidateResolver)
    {
    }

    public function register(): void
    {
        add_shortcode('wpcbtpro_login', [$this, 'renderShortcode']);
        add_action('template_redirect', [$this, 'maybeProcessLogin']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
    }

    public function maybeEnqueueAssets(): void
    {
        global $post;
        if (!($post instanceof \WP_Post) || !has_shortcode($post->post_content, 'wpcbtpro_login')) {
            return;
        }

        wp_enqueue_style('wpcbtpro-exam', WPCBTPRO_URL . 'public/css/exam.css', [], WPCBTPRO_VERSION);
    }

    /**
     * The dedicated candidate login page's URL, if an institution has
     * published one (self-registered the first time the shortcode
     * rendered — see renderShortcode()), else the ordinary WP login page.
     * ExamRuntimeController's "please log in" prompt uses this rather than
     * hard-coding wp_login_url(), so a site with no such page keeps
     * working exactly as before.
     */
    public static function loginUrl(string $redirectTo): string
    {
        $pageId = (int) get_option(self::LOGIN_PAGE_OPTION);
        $page = $pageId > 0 ? get_post($pageId) : null;

        if ($page === null || $page->post_status !== 'publish') {
            return wp_login_url($redirectTo);
        }

        return add_query_arg('redirect_to', rawurlencode($redirectTo), get_permalink($page));
    }

    public function maybeProcessLogin(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- wp_verify_nonce() below is the real check; this only decides whether to run it.
        if ((wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- wp_verify_nonce() sanitizes internally; it only ever compares against known hash strings.
        if (!wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            $this->pendingError = __('Your session expired. Please try again.', 'wp-cbt-pro');
            return;
        }

        $login = sanitize_text_field(wp_unslash($_POST['wpcbtpro_login_user'] ?? ''));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a password must survive verbatim; sanitize_text_field() would mangle a legitimate password containing tags/whitespace, and wp_authenticate() inside wp_signon() rejects anything that doesn't match the stored hash anyway.
        $password = (string) wp_unslash($_POST['wpcbtpro_login_pass'] ?? '');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated against esc_url_raw()/an internal redirect below, never output raw.
        $redirectTo = (string) wp_unslash($_POST['redirect_to'] ?? '');

        $result = wp_signon(['user_login' => $login, 'user_password' => $password, 'remember' => true], is_ssl());

        if (is_wp_error($result)) {
            $this->pendingError = __('Incorrect username/email or password.', 'wp-cbt-pro');
            return;
        }

        wp_safe_redirect($redirectTo !== '' ? $redirectTo : home_url('/'));
        exit;
    }

    public function renderShortcode($atts): string
    {
        if (is_singular() && (int) get_option(self::LOGIN_PAGE_OPTION) !== get_the_ID()) {
            update_option(self::LOGIN_PAGE_OPTION, get_the_ID());
        }

        if (is_user_logged_in()) {
            return $this->renderAlreadyLoggedIn();
        }

        ob_start();
        $error = $this->pendingError;
        // A failed login attempt redisplays this same page from a POST, not a GET —
        // fall back to the posted redirect_to too (only ever echoed into a hidden
        // field below, which esc_attr() covers) so a retry still lands back where
        // it should. Read-only either way: this only decides where to send the
        // candidate after a successful login, not a state change.
        // phpcs:disable WordPress.Security.NonceVerification
        $redirectTo = isset($_POST['redirect_to'])
            ? esc_url_raw(wp_unslash($_POST['redirect_to']))
            : (isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '');
        // phpcs:enable WordPress.Security.NonceVerification
        include WPCBTPRO_PATH . 'public/views/candidate-login.php';
        return (string) ob_get_clean();
    }

    private function renderAlreadyLoggedIn(): string
    {
        $candidate = $this->candidateResolver->resolve();
        $logoutUrl = wp_logout_url(get_permalink() ?: home_url('/'));

        if ($candidate === null) {
            return '<p class="wpcbtpro-notice">' . sprintf(
                /* translators: %s: log out link */
                esc_html__('You\'re signed in, but this account isn\'t registered as an exam candidate. %s', 'wp-cbt-pro'),
                '<a href="' . esc_url($logoutUrl) . '">' . esc_html__('Log out', 'wp-cbt-pro') . '</a>'
            ) . '</p>';
        }

        return '<p class="wpcbtpro-notice">' . sprintf(
            /* translators: 1: candidate name, 2: log out link */
            esc_html__('You\'re signed in as %1$s. %2$s', 'wp-cbt-pro'),
            esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])),
            '<a href="' . esc_url($logoutUrl) . '">' . esc_html__('Log out', 'wp-cbt-pro') . '</a>'
        ) . '</p>';
    }
}
