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
<!-- Reputation Snapshot -->
		<?php if ( $cfg['widgets']['reviews'] ?? true ) :
			$_gmb_locations    = $cfg['reviews_locations']   ?? [];
			$_gmb_competitors  = $cfg['reviews_competitors'] ?? [];
			$_gmb_api_key      = $cfg['reviews_api_key']     ?? '';
			$_gmb_display_mode = $cfg['reviews_display_mode'] ?? 'combined';
			$_gmb_cta_text     = $cfg['reviews_cta_text']    ?? '';

			// Migrate legacy single place_id.
			if ( empty( $_gmb_locations ) && ! empty( $cfg['reviews_place_id'] ) ) {
				$_gmb_locations = [ [ 'place_id' => $cfg['reviews_place_id'], 'label' => '' ] ];
			}

			$_gmb_all      = ! empty( $_gmb_locations )   ? g6_gmb_get_all_data( $_gmb_locations,   $_gmb_api_key ) : [];
			$_gmb_comp_all = ! empty( $_gmb_competitors ) ? g6_gmb_get_all_data( $_gmb_competitors, $_gmb_api_key ) : [];
			$_gmb_combined = g6_gmb_combine( $_gmb_all );

			// Fallback to static config when no live data.
			$_reviews_data  = $_gmb_combined ?: $cfg['reviews'];
			$_client_count  = (int)   ( $_reviews_data['google_count']  ?? 0 );
			$_client_rating = (float) ( $_reviews_data['google_rating'] ?? 0 );

			// 3 most-recent reviews — always combined/sorted from all locations.
			$_reviews_recent  = array_slice( $_reviews_data['recent'] ?? [], 0, 3 );
			$_show_comparison = ! empty( $_gmb_comp_all );

			// CTA: manual override wins; otherwise dynamic; otherwise generic.
			if ( $_gmb_cta_text ) {
				$_cta_body = esc_html( $_gmb_cta_text );
			} elseif ( $_show_comparison ) {
				$_cta_body = esc_html( g6_gmb_dynamic_cta( $_client_count, $_gmb_comp_all ) );
			} else {
				$_cta_body = 'Want more reviews and better reputation management?';
			}
		?>
		<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'star', 20 ); ?>
						Reputation Snapshot
					</h2>
				</div>

				<?php if ( $_gmb_display_mode === 'separate' && count( $_gmb_all ) > 1 ) :
					foreach ( $_gmb_all as $_loc_data ) : ?>
					<div class="g6-reviews__location-label"><?php echo esc_html( $_loc_data['label'] ); ?></div>
					<div class="g6-reviews__summary">
						<div class="g6-reviews__stat">
							<div class="g6-reviews__stat-value"><?php echo esc_html( $_loc_data['google_rating'] ); ?></div>
							<div class="g6-reviews__stars"><?php echo str_repeat( '★', (int) round( $_loc_data['google_rating'] ) ); ?></div>
							<div class="g6-reviews__stat-label">Google Rating</div>
						</div>
						<div class="g6-reviews__stat">
							<div class="g6-reviews__stat-value"><?php echo (int) $_loc_data['google_count']; ?></div>
							<div class="g6-reviews__stat-label">Google Reviews</div>
						</div>
					</div>
					<?php endforeach; ?>
				<?php else : ?>
				<div class="g6-reviews__summary">
					<div class="g6-reviews__stat">
						<div class="g6-reviews__stat-value"><?php echo esc_html( $_reviews_data['google_rating'] ); ?></div>
						<div class="g6-reviews__stars"><?php echo str_repeat( '★', (int) round( $_reviews_data['google_rating'] ) ); ?></div>
						<div class="g6-reviews__stat-label">Google Rating</div>
					</div>
					<div class="g6-reviews__stat">
						<div class="g6-reviews__stat-value"><?php echo (int) $_reviews_data['google_count']; ?></div>
						<div class="g6-reviews__stat-label">Google Reviews</div>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( $_show_comparison ) : ?>
				<div class="g6-reviews__compare-box">
					<div class="g6-reviews__comparison-title">How You Compare</div>
					<div class="g6-reviews__comparison-table">
						<div class="g6-reviews__comp-row g6-reviews__comp-row--client">
							<span class="g6-reviews__comp-name"><?php echo esc_html( $cfg['client_name'] ); ?></span>
							<span class="g6-reviews__comp-rating"><?php echo esc_html( number_format( $_client_rating, 1 ) ); ?> ★</span>
							<span class="g6-reviews__comp-count"><?php echo number_format( $_client_count ); ?> reviews</span>
							<span class="g6-reviews__comp-gap"></span>
						</div>
						<?php foreach ( $_gmb_comp_all as $_comp ) :
							$_gap      = (int) ( $_comp['google_count'] ?? 0 ) - $_client_count;
							$_maps_url = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode( $_comp['place_id'] ?? '' );
						?>
						<div class="g6-reviews__comp-row">
							<a class="g6-reviews__comp-name" href="<?php echo esc_url( $_maps_url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $_comp['label'] ); ?>
							</a>
							<span class="g6-reviews__comp-rating"><?php echo esc_html( number_format( $_comp['google_rating'] ?? 0, 1 ) ); ?> ★</span>
							<span class="g6-reviews__comp-count"><?php echo number_format( (int) ( $_comp['google_count'] ?? 0 ) ); ?> reviews</span>
							<span class="g6-reviews__comp-gap <?php echo $_gap > 0 ? 'g6-reviews__comp-gap--behind' : 'g6-reviews__comp-gap--ahead'; ?>">
								<?php if ( $_gap > 0 ) : ?>
									+<?php echo number_format( $_gap ); ?> ahead
								<?php elseif ( $_gap < 0 ) : ?>
									<?php echo number_format( abs( $_gap ) ); ?> behind
								<?php else : ?>
									tied
								<?php endif; ?>
							</span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $_reviews_recent ) ) : ?>
				<div class="g6-reviews__recent-title">Latest Reviews</div>
				<?php foreach ( $_reviews_recent as $_review ) : ?>
				<div class="g6-review">
					<div class="g6-review__header">
						<span>
							<span class="g6-review__stars"><?php echo str_repeat( '★', (int) $_review['rating'] ); ?></span>
							<span class="g6-review__author"><?php echo esc_html( $_review['author'] ); ?></span>
						</span>
						<span class="g6-review__meta"><?php echo esc_html( $_review['source'] ); ?> &middot; <?php echo esc_html( $_review['date'] ); ?></span>
					</div>
					<p class="g6-review__text"><?php echo esc_html( $_review['text'] ); ?></p>
				</div>
				<?php endforeach; ?>
				<?php endif; ?>

				<div class="g6-card__cta-footer">
					<p class="g6-card__cta-text"><?php echo $_cta_body; ?></p>
					<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>?subject=Reputation%20Management%20-%20<?php echo rawurlencode( $cfg['client_name'] ); ?>" class="g6-card__cta-link">
						Ask about Reputation Management &rarr;
					</a>
				</div>
		</div>
		<?php endif; ?>
