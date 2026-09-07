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
<!-- Project Status -->
		<?php
		// The portal is the only source for this — there is no Airtable
		// equivalent — so it needs a token whatever the Support Hours
		// setting says.
		$_pj = ( $cfg['widgets']['projects'] ?? false ) && function_exists( 'g6_api_get_live_projects' )
			? g6_api_get_live_projects( g6_portal_token( $cfg ) )
			: [];
		?>
		<?php if ( $_pj ) : ?>
		<div class="g6-card">
			<div class="g6-dashboard__section-header">
				<h2 class="g6-dashboard__section-title">
					<?php echo g6_icon( 'trending-up', 20 ); ?>
					<?php echo count( $_pj ) === 1 ? 'Your project' : 'Your projects'; ?>
				</h2>
			</div>

			<?php foreach ( $_pj as $_p ) : ?>
				<?php
				$_total   = max( 0, (int) ( $_p['steps_total'] ?? 0 ) );
				$_done    = min( $_total, max( 0, (int) ( $_p['steps_done'] ?? 0 ) ) );
				$_target  = ! empty( $_p['target_on'] ) ? strtotime( $_p['target_on'] ) : null;
				$_updated = ! empty( $_p['updated_at'] ) ? strtotime( $_p['updated_at'] ) : null;
				?>
				<div class="g6-project">
					<div class="g6-project__head">
						<p class="g6-project__name"><?php echo esc_html( $_p['name'] ?? 'Project' ); ?></p>
						<p class="g6-project__stage">
							<?php echo esc_html( $_p['status_label'] ?? '' ); ?>
							<?php if ( $_target ) : ?>
								&middot; aiming for <?php echo esc_html( date_i18n( 'j F Y', $_target ) ); ?>
							<?php endif; ?>
						</p>
					</div>

					<?php if ( $_total > 0 ) : ?>
						<div class="g6-project__steps">
							<div class="g6-project__dots" role="img"
								 aria-label="<?php echo esc_attr( sprintf( '%d of %d steps done', $_done, $_total ) ); ?>">
								<?php for ( $i = 0; $i < $_total; $i++ ) : ?>
									<span class="g6-project__dot<?php echo $i < $_done ? ' is-done' : ''; ?>"></span>
								<?php endfor; ?>
							</div>
							<span class="g6-project__count">
								<?php echo (int) $_done; ?> of <?php echo (int) $_total; ?> steps done
							</span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $_p['next_note'] ) ) : ?>
						<div class="g6-project__next">
							<p class="g6-project__next-label">What&rsquo;s next</p>
							<p class="g6-project__next-text"><?php echo esc_html( $_p['next_note'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $_updated ) : ?>
						<?php
						// Not decoration: the status is kept by hand, so a
						// card that cannot show its age states a stale
						// figure with the same confidence as a fresh one.
						?>
						<p class="g6-project__updated">
							Updated <?php echo esc_html( human_time_diff( $_updated, current_time( 'timestamp' ) ) ); ?> ago
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
