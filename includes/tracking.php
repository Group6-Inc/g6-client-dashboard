<?php
/**
 * Tracking & analytics script injection.
 * Reads IDs from g6_client_config['tracking'] and outputs standard embed code.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head',      'g6_tracking_head_scripts', 1 );
add_action( 'wp_body_open','g6_tracking_body_open_scripts', 1 );

function g6_tracking_head_scripts(): void {
	$cfg      = g6_get_client_config();
	$tracking = $cfg['tracking'] ?? [];

	$gtm_id            = trim( $tracking['gtm_id']            ?? '' );
	$google_ads_id     = trim( $tracking['google_ads_id']     ?? '' );
	$facebook_pixel_id = trim( $tracking['facebook_pixel_id'] ?? '' );
	$x_pixel_id        = trim( $tracking['x_pixel_id']        ?? '' );
	$clarity_id        = trim( $tracking['clarity_project_id'] ?? '' );

	if ( $gtm_id ) : ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
<!-- End Google Tag Manager -->
	<?php endif;

	if ( $google_ads_id ) : ?>
<!-- Google Ads -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $google_ads_id ); ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js( $google_ads_id ); ?>');</script>
<!-- End Google Ads -->
	<?php endif;

	if ( $facebook_pixel_id ) : ?>
<!-- Meta Pixel -->
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?php echo esc_js( $facebook_pixel_id ); ?>');fbq('track','PageView');</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $facebook_pixel_id ); ?>&ev=PageView&noscript=1"/></noscript>
<!-- End Meta Pixel -->
	<?php endif;

	if ( $x_pixel_id ) : ?>
<!-- X (Twitter) Pixel -->
<script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config','<?php echo esc_js( $x_pixel_id ); ?>');</script>
<!-- End X (Twitter) Pixel -->
	<?php endif;

	if ( $clarity_id ) : ?>
<!-- Microsoft Clarity -->
<script type="text/javascript">(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","<?php echo esc_js( $clarity_id ); ?>");</script>
<!-- End Microsoft Clarity -->
	<?php endif;
}

function g6_tracking_body_open_scripts(): void {
	$cfg    = g6_get_client_config();
	$gtm_id = trim( $cfg['tracking']['gtm_id'] ?? '' );

	if ( $gtm_id ) : ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
	<?php endif;
}
