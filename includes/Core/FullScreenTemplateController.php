<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

/**
 * A candidate mid-exam shouldn't have the site's normal navigation, admin
 * bar, or footer competing for attention (or offering an easy way to wander
 * off) — the login page and any page embedding [wpcbtpro_exam] render
 * through a bare template instead of the active theme's header.php/footer.php,
 * so only the candidate card and exam content show. wp_head()/wp_footer()
 * still fire (enqueued styles/scripts, SEO meta, etc. all still work), just
 * without the theme wrapping its own header/nav/footer markup around it.
 */
final class FullScreenTemplateController
{
    private const SHORTCODES = ['wpcbtpro_login', 'wpcbtpro_exam'];

    public function register(): void
    {
        add_filter('template_include', [$this, 'maybeUseFullScreenTemplate']);
    }

    public function maybeUseFullScreenTemplate(string $template): string
    {
        if (!is_singular()) {
            return $template;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $template;
        }

        foreach (self::SHORTCODES as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                add_filter('show_admin_bar', '__return_false');
                return WPCBTPRO_PATH . 'public/views/full-screen-template.php';
            }
        }

        return $template;
    }
}
