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
<!-- Get in Touch -->
		<?php if ( $cfg['widgets']['contact'] ?? true ) : ?>
		<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'message-circle', 20 ); ?>
						Get in Touch
					</h2>
				</div>
				<p style="font-size:14px; color:var(--g6-neutral-500); margin:0 0 18px; line-height:1.5;">
					Have a question or need help? Submit a request and your account manager will follow up.
				</p>
				<?php
				// Two shapes for one form.
				//
				// Zendesk wants its issue-type field, whose options are
				// prose and double as the ticket subject — those strings
				// are mapped 1:1 in includes/ajax.php and must not drift.
				//
				// The portal has real categories, editable by staff, so
				// the list is fetched rather than baked in; and it has a
				// subject of its own, which is what makes a queue of
				// tickets readable. Sending the category name as the
				// subject would give staff twenty rows all called
				// "Website Update".
				$_c_portal     = function_exists( 'g6_tickets_destination' ) && 'portal' === g6_tickets_destination( $cfg );
				$_c_categories = $_c_portal ? g6_api_get_ticket_categories( g6_portal_token( $cfg ) ) : [];

				// The SHAPE follows the setting, not the fetch.
				//
				// A site set to the portal has been migrated, and must not
				// start showing Zendesk's topics again because a token was
				// revoked or the portal was briefly down — those requests
				// fall through to email, where a typed subject is the only
				// thing making them readable. Only the topic list depends
				// on the fetch succeeding; without it the portal applies
				// its own default category.
				$_c_topics = $_c_portal && ! empty( $_c_categories );
				?>
				<div class="g6-contact-form" id="g6-contact-form" data-mode="<?php echo $_c_portal ? 'portal' : 'legacy'; ?>">
					<?php if ( $_c_portal ) : ?>
					<?php if ( $_c_topics ) : ?>
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-category">Topic</label>
						<select class="g6-contact-form__select" id="g6-category" name="category">
							<option value="">Choose a topic&hellip;</option>
							<?php foreach ( $_c_categories as $_slug => $_name ) : ?>
								<option value="<?php echo esc_attr( $_slug ); ?>"><?php echo esc_html( $_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-subject">Subject</label>
						<input class="g6-contact-form__select" type="text" id="g6-subject" name="subject"
							maxlength="255" placeholder="A few words on what this is about">
					</div>
					<?php else : ?>
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-subject">Subject</label>
						<select class="g6-contact-form__select" id="g6-subject" name="subject">
							<option value="">Choose a topic&hellip;</option>
							<option value="I would like to update my website">I would like to update my website</option>
							<option value="Hosting-related issues">Hosting-related issues</option>
							<option value="Design/branding requests or issues">Design/branding requests or issues</option>
							<option value="New feature request">New feature request</option>
							<option value="Billing/account">Billing/account</option>
							<option value="I need training on a specific topic">I need training on a specific topic</option>
							<option value="Other - My issue is not listed">Other - My issue is not listed</option>
						</select>
					</div>
					<?php endif; ?>
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-message">Message</label>
						<textarea class="g6-contact-form__textarea" id="g6-message" name="message" placeholder="Tell us what you need&hellip;"></textarea>
					</div>
					<button type="button" class="g6-contact-form__submit" id="g6-submit-btn" onclick="g6SubmitContact()">
						<?php echo g6_icon( 'send', 16 ); ?>
						Send Message
					</button>
					<div class="g6-contact-form__success" id="g6-contact-success">
						<?php echo g6_icon( 'check-circle', 16 ); ?>
						&nbsp; Request submitted! Your account manager will follow up shortly.
					</div>
					<div class="g6-contact-form__error" id="g6-contact-error"></div>
				</div>
		</div>
		<?php endif; ?>
