<?php
/**
 * The bare template served (via FullScreenTemplateController) for the
 * candidate login page and any page embedding [wpcbtpro_exam] — no theme
 * header.php/footer.php, just the page's own content.
 */
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('wpcbtpro-fullscreen'); ?>>
<?php while (have_posts()): the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
