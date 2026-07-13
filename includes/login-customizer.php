<?php
/**
 * Login Screen Customizer.
 * Outputs branded CSS on the WP login page and overrides the header link URL.
 * Uses a class so there are no global function names that could conflict with
 * existing WPCodeBox snippets.
 *
 * Loaded conditionally from g6-client-dashboard.php when login.enabled is true.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G6_Login_Customizer {

	private array $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;
		add_action( 'login_enqueue_scripts', [ $this, 'output_css' ] );
		add_filter( 'login_headerurl',       [ $this, 'header_url' ] );
		if ( ! empty( $cfg['login_error_message'] ) ) {
			add_filter( 'login_errors', [ $this, 'custom_errors' ] );
		}
	}

	public function custom_errors(): string {
		return '<strong>' . esc_html( $this->cfg['login_error_message'] ) . '</strong>';
	}

	public function header_url(): string {
		return home_url();
	}

	public function output_css(): void {
		$c           = $this->cfg;
		$logo_url    = esc_url( $c['logo_url']       ?? '' );
		$logo_height = absint( $c['logo_height']     ?? 65 );
		$bg_color    = sanitize_text_field( $c['bg_color']       ?? '#111111' ) ?: '#111111';
		$hero_url    = esc_url( $c['hero_image_url'] ?? '' );
		$accent      = sanitize_text_field( $c['accent_color']  ?? '#ff6e61' ) ?: '#ff6e61';
		$link_color  = sanitize_text_field( $c['link_color']    ?? '#ffffff' ) ?: '#ffffff';
		?>
<style id="g6-login-customizer">
body.login {
	background-color: <?php echo $bg_color; ?>;
}
<?php if ( $logo_url ) : ?>
body.login div#login h1 a {
	background-image: url('<?php echo $logo_url; ?>');
	padding-bottom: 0;
	background-size: contain;
	width: 100%;
	height: <?php echo $logo_height; ?>px;
}
<?php endif; ?>
<?php if ( $hero_url ) : ?>
body.login::before {
	content: "";
	position: absolute;
	top: 0; right: 0;
	width: 50vw;
	height: 100svh;
	background-image: url('<?php echo $hero_url; ?>');
	background-size: cover;
	background-position: center;
	background-repeat: no-repeat;
}
<?php endif; ?>
body.login #login {
	background: <?php echo $bg_color; ?>;
	height: 100svh;
	display: flex;
	flex-direction: column;
	justify-content: center;
	width: 50vw;
	min-width: 500px;
	padding: 0 10%;
	position: absolute;
	left: 0; top: 0;
	box-sizing: border-box;
	margin: 0;
}
.login form {
	border-radius: 12px;
}
body.login #backtoblog a,
body.login #backtoblog a:focus,
body.login #nav a:focus,
body.login h1 a:focus,
body.login #nav a {
	color: <?php echo $link_color; ?>;
}
body.login #backtoblog a:hover,
body.login #nav a:hover,
body.login h1 a:hover {
	color: <?php echo $link_color; ?>;
	opacity: 0.8;
}
body.login #login #wp-submit {
	background: <?php echo $accent; ?>;
	border: none;
	transition: opacity 0.2s ease;
}
body.login #login #wp-submit:hover {
	opacity: 0.85;
}
.login-action-confirm_admin_email #login {
	max-width: unset;
	width: 50vw;
	margin-top: unset;
}
@media (max-width: 500px) {
	body.login #login {
		width: 100%;
		min-width: 100%;
		padding: 0 6%;
	}
	.login-action-confirm_admin_email #login {
		max-width: 100%;
		width: 100%;
	}
}
</style>
		<?php
	}
}
