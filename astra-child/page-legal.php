<?php
/**
 * Template Name: Legal Page
 * 
 * Custom template for Terms of Service and Privacy Policy pages
 * 
 * @package Astra Child
 * @since 1.0.0
 */

get_header(); ?>

<div class="ast-container">
    <div id="primary" class="content-area primary">
        
        <?php astra_primary_content_top(); ?>
        
        <main id="main" class="site-main">
            
            <?php astra_content_top(); ?>
            
            <?php while ( have_posts() ) : the_post(); ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    
                    <?php astra_entry_content_before(); ?>
                    
                    <div class="entry-content clear" <?php echo astra_attr( 'article-content-blog-layout' ); ?>>
                        <?php
                        // Display the page content
                        the_content();
                        
                        // Display edit link for admin users
                        if ( is_user_logged_in() && current_user_can( 'edit_pages' ) ) {
                            echo '<div class="legal-edit-link" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center;">';
                            edit_post_link( 
                                __( 'Edit this page', 'astra-child' ),
                                '<span class="edit-link" style="font-size: 14px; color: #666;">',
                                '</span>'
                            );
                            echo '</div>';
                        }
                        ?>
                    </div><!-- .entry-content .clear -->
                    
                    <?php astra_entry_content_after(); ?>
                    
                </article><!-- #post-## -->
                
                <?php
                // Display breadcrumbs if supported
                if ( function_exists( 'astra_entry_footer' ) ) {
                    astra_entry_footer();
                }
                ?>
                
            <?php endwhile; ?>
            
            <?php astra_content_bottom(); ?>
            
        </main><!-- #main -->
        
        <?php astra_primary_content_bottom(); ?>
        
    </div><!-- #primary -->
</div><!-- .ast-container -->

<?php get_footer(); ?> 