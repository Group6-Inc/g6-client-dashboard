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
<!-- Featured Video -->
		<?php
		$embed_url = ! empty( $cfg['video_url'] ) ? g6_get_video_embed_url( $cfg['video_url'] ) : '';
		if ( ( $cfg['widgets']['video'] ?? false ) && $embed_url ) :
		?>
		<div class="g6-card">
			<div class="g6-dashboard__section-header">
				<h2 class="g6-dashboard__section-title">
					<?php echo g6_icon( 'play-circle', 20 ); ?>
					<?php echo esc_html( $cfg['video_title'] ?: 'How to Use Your WordPress Site' ); ?>
				</h2>
			</div>
			<div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:var(--g6-radius);">
				<iframe
					src="<?php echo esc_url( $embed_url ); ?>"
					style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;"
					allowfullscreen
					loading="lazy">
				</iframe>
			</div>
		</div>
		<?php endif; ?>
