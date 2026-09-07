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
<!-- Grow Your Business -->
		<?php if ( $cfg['widgets']['services'] ?? true ) : ?>
		<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'zap', 20 ); ?>
						Grow Your Business
					</h2>
					<span class="g6-dashboard__section-badge">Add-On Services</span>
				</div>
				<div class="g6-services">
					<?php foreach ( $cfg['services'] as $svc ) : ?>
						<div class="g6-service<?php echo $svc['highlight'] ? ' g6-service--highlight' : ''; ?>">
							<div class="g6-service__icon"><?php echo g6_icon( $svc['icon'] ); ?></div>
							<h3 class="g6-service__name"><?php echo esc_html( $svc['name'] ); ?></h3>
							<p class="g6-service__desc"><?php echo esc_html( $svc['description'] ); ?></p>
							<a href="<?php echo esc_url( $svc['cta_url'] ); ?>" class="g6-service__cta" target="_blank" rel="noopener">
								<?php echo esc_html( $svc['cta_label'] ); ?> &rarr;
							</a>
						</div>
					<?php endforeach; ?>
				</div>
		</div>
		<?php endif; ?>
