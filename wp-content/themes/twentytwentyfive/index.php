<?php
/**
 * Fallback theme template for the local WordPress container.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<div id="page">
    <main id="primary">
        <?php if ( have_posts() ) : ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>">
                    <h1><?php the_title(); ?></h1>
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <article>
                <h1>WordPress is ready</h1>
                <p>Your site is running, and the fallback theme is active because the bind-mounted <?php echo esc_html( WP_CONTENT_DIR ); ?> directory hides the default bundled theme.</p>
                <p>Use the WordPress dashboard to choose a custom theme or install content.</p>
            </article>
        <?php endif; ?>
    </main>
</div>
<?php get_footer();
