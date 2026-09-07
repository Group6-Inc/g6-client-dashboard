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
<!-- Keyword Rankings -->
		<?php if ( $cfg['widgets']['keywords'] ?? true ) : ?>
		<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'search', 20 ); ?>
						Keyword Rankings
					</h2>
					<span class="g6-dashboard__updated">
						Updated <?php echo esc_html( date( 'M j, Y', strtotime( $cfg['last_updated'] ) ) ); ?>
					</span>
				</div>
				<table class="g6-keywords-table">
					<thead>
						<tr>
							<th>Keyword</th>
							<th>Position</th>
							<th>Change</th>
							<th>Volume</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cfg['keywords'] as $kw ) :
							if ( $kw['position'] <= 3 )       $pos_class = 'g6-keywords-table__position--top3';
							elseif ( $kw['position'] <= 10 )  $pos_class = 'g6-keywords-table__position--top10';
							elseif ( $kw['position'] <= 20 )  $pos_class = 'g6-keywords-table__position--top20';
							else                              $pos_class = 'g6-keywords-table__position--below';

							if ( $kw['change'] > 0 )      { $change_class = 'g6-keywords-table__change--up';   $change_text = '↑ ' . $kw['change']; }
							elseif ( $kw['change'] < 0 )  { $change_class = 'g6-keywords-table__change--down'; $change_text = '↓ ' . abs( $kw['change'] ); }
							else                          { $change_class = 'g6-keywords-table__change--flat'; $change_text = '—'; }
						?>
						<tr>
							<td class="g6-keywords-table__term"><?php echo esc_html( $kw['term'] ); ?></td>
							<td><span class="g6-keywords-table__position <?php echo esc_attr( $pos_class ); ?>"><?php echo (int) $kw['position']; ?></span></td>
							<td><span class="g6-keywords-table__change <?php echo esc_attr( $change_class ); ?>"><?php echo esc_html( $change_text ); ?></span></td>
							<td class="g6-keywords-table__volume"><?php echo number_format( $kw['volume'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="g6-card__cta-footer">
					<p class="g6-card__cta-text">Want to improve these rankings?</p>
					<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>?subject=SEO%20Inquiry%20-%20<?php echo rawurlencode( $cfg['client_name'] ); ?>" class="g6-card__cta-link">
						Talk to us about Local SEO &rarr;
					</a>
				</div>
		</div>
		<?php endif; ?>
