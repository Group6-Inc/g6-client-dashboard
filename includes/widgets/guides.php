<?php
/**
 * One dashboard widget. Included by includes/dashboard.php in the order
 * the site has chosen, so this file owns the widget's markup and nothing
 * owns its position.
 *
 * Included, not required_once: it inherits the caller's scope, so $cfg
 * and anything the dashboard computed above are simply here.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- How-to Guides -->
		<?php if ( $cfg['widgets']['guides'] ?? true ) : ?>
		<div class="g6-card g6-card--full">
			<div class="g6-dashboard__section-header">
				<h2 class="g6-dashboard__section-title">
					<?php echo g6_icon( 'book-open', 20 ); ?>
					How-To Guides &amp; Resources
				</h2>
			</div>
			<div class="g6-guides">
				<?php foreach ( $cfg['guides'] as $guide ) : ?>
					<a href="<?php echo esc_attr( $guide['url'] ); ?>" class="g6-guide" target="_blank" rel="noopener">
						<div class="g6-guide__icon"><?php echo g6_icon( $guide['icon'] ); ?></div>
						<div>
							<p class="g6-guide__title"><?php echo esc_html( $guide['title'] ); ?></p>
							<p class="g6-guide__desc"><?php echo esc_html( $guide['description'] ); ?></p>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
