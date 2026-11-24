<?php
/*
Template Name: Plans
*/

// The form carries a nonce, so this page must not be served from WP Fastest Cache.
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

$options = fd_plans_options();
$state   = fd_plans_state();
$status  = isset( $state['status'] ) ? $state['status'] : '';
$errors  = ( 'error' === $status && ! empty( $state['errors'] ) ) ? $state['errors'] : array();
$values  = ( 'error' === $status && ! empty( $state['values'] ) ) ? $state['values'] : array();

$val = function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};

// aria-describedby / aria-invalid for a field, plus its inline error.
$field_attrs = function ( $key, $help_id = '' ) use ( $errors ) {
	$ids = array_filter( array( $help_id, isset( $errors[ $key ] ) ? 'f-' . $key . '-err' : '' ) );
	$out = $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '';
	return isset( $errors[ $key ] ) ? $out . ' aria-invalid="true"' : $out;
};
$field_error = function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? '<p class="field-err" id="f-' . esc_attr( $key ) . '-err">' . esc_html( $errors[ $key ] ) . '</p>' : '';
};

// Where each error in the summary links to.
$error_targets = array(
	'plan'    => 'f-plan-oncall',
	'sites'   => 'f-sites',
	'name'    => 'f-name',
	'email'   => 'f-email',
	'start'   => 'f-start',
	'payment' => 'f-pay',
);

$plan      = $val( 'plan' );
$care      = ! empty( $values['care'] );
$payment   = $val( 'payment' ) ? $val( 'payment' ) : 'card';
$care_rate = in_array( $plan, $options['paid'], true ) ? '($75 per site with your retainer)' : '($85 per site, or $75 with a retainer plan)';
$tomorrow  = ( new DateTimeImmutable( 'tomorrow', wp_timezone() ) )->format( 'Y-m-d' );

$choices = array(
	'oncall'  => '<span class="c-name">On Call</span><span class="c-hours">10 hrs / mo</span><span class="c-price">$900/month</span>',
	'ongoing' => '<span class="c-name">Ongoing</span><span class="c-hours">20 hrs / mo</span><span class="c-price">$1,700/month</span>',
	'partner' => '<span class="c-name">Partner</span><span class="c-hours">40 hrs / mo</span><span class="c-price">$3,200/month</span>',
	'custom'  => '<span class="c-name">Custom</span><span class="c-hours">60+ hrs / mo</span><span class="c-price">Priced together</span>',
	'unsure'  => '<span class="c-name">Not sure yet</span><span class="c-price">I\'ll recommend one</span>',
	'none'    => '<span class="c-name">No retainer</span><span class="c-price">Care plan only</span>',
);

get_header();
?>

<?php // #main-content is what Divi's fixed-header waypoint watches to shrink the header on scroll. ?>
<div id="main-content">
<main class="fd-plans" id="fd-plans">
	<section class="hero">
		<div class="wrap">
			<p class="eyebrow">Retainer plans and care plans</p>
			<h1>A senior developer on your team, <em>every month</em></h1>
			<p class="lede">Reserve hours each month for the changes, pages, and fixes your site always needs. No proposal every time, no waiting in a queue, and you'll always be talking to the person doing the work.</p>
			<div class="hero-actions">
				<a class="btn" href="#plans">See the plans</a>
				<a class="text" href="#care">Or just need your site looked after? See care plans</a>
			</div>
			<dl class="hero-facts">
				<div><dt>Plans from</dt><dd>10 hours a month</dd></div>
				<div><dt>Rates from</dt><dd>$80 an hour</dd></div>
				<div><dt>Care plans</dt><dd>$85 per site</dd></div>
			</dl>
		</div>
	</section>

	<section>
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">How it works</p>
				<h2>Simple from the first month</h2>
			</div>
			<div class="steps">
				<div class="step"><h3>Pick a plan</h3><p>Choose the number of hours that fits how much you usually need. You can move up or down as things change.</p></div>
				<div class="step"><h3>Send requests whenever</h3><p>Email me what you need: a new landing page, a fix, tracking setup, a question. I prioritize by your plan and get it done.</p></div>
				<div class="step"><h3>Get a monthly summary</h3><p>At the end of each billing month you get a short report: hours used, what shipped, and what's coming next.</p></div>
			</div>
		</div>
	</section>

	<section class="plans-sec" id="plans">
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">Retainer plans</p>
				<h2>Reserve your hours</h2>
				<p>Every retainer is billed monthly in advance. The more hours you reserve, the lower your rate.</p>
			</div>
			<div class="tiers">
				<article class="tier">
					<p class="tier-name">On Call</p>
					<p class="hours">10<small>hrs / month</small></p>
					<p class="price"><strong>$900</strong><span> / month at $90/hr</span></p>
					<p class="response">Response within 2 business days</p>
					<p class="fit">Good for ongoing updates, small improvements, and having someone on call.</p>
					<a class="btn btn-ghost" href="#start" data-plan="oncall">Choose On Call</a>
				</article>
				<article class="tier">
					<p class="tier-name">Ongoing</p>
					<p class="hours">20<small>hrs / month</small></p>
					<p class="price"><strong>$1,700</strong><span> / month at $85/hr</span></p>
					<p class="response">Response within 1 business day</p>
					<p class="fit">Good for steady work like new pages, campaigns, and regular improvements.</p>
					<a class="btn btn-ghost" href="#start" data-plan="ongoing">Choose Ongoing</a>
				</article>
				<article class="tier">
					<p class="tier-name">Partner</p>
					<p class="hours">40<small>hrs / month</small></p>
					<p class="price"><strong>$3,200</strong><span> / month at $80/hr</span></p>
					<p class="response">Same-business-day response</p>
					<p class="fit">Good for agencies and teams that need a reliable developer every week.</p>
					<a class="btn btn-ghost" href="#start" data-plan="partner">Choose Partner</a>
				</article>
				<article class="tier">
					<p class="tier-name">Custom</p>
					<p class="hours">60+<small>hrs / month</small></p>
					<p class="price"><strong>Priced together</strong><span> based on scope</span></p>
					<p class="response">Part of your team</p>
					<p class="fit">Good for agencies and companies that need a developer working as part of their team. Let's talk.</p>
					<a class="btn btn-ghost" href="#start" data-plan="custom">Get in touch</a>
				</article>
			</div>
			<p class="plans-note">No retainer? I also work hourly at <strong>$100/hour</strong>, billed as the work is done.</p>

			<div class="covers">
				<div>
					<h3>What clients use their hours for</h3>
					<ul class="tags">
						<li>Content and design updates</li>
						<li>New landing pages and sections</li>
						<li>Bug fixes</li>
						<li>Small features and integrations</li>
						<li>Analytics and tracking (GA4, Tag Manager)</li>
						<li>Email templates</li>
						<li>Performance and accessibility</li>
						<li>Consulting calls</li>
						<li>New sites and redesigns</li>
					</ul>
				</div>
			</div>
		</div>
	</section>

	<section id="care">
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">Care plans</p>
				<h2>Keep your site healthy</h2>
				<p>For sites that just need to be looked after. A care plan is a flat monthly price per site, and you choose which sites to add.</p>
			</div>
			<div class="care">
				<div class="care-price">
					<p class="big">$85<small>per site / month</small></p>
					<ul class="rates">
						<li><span>Clients with an active retainer</span><b>$75 / site</b></li>
						<li><span>Agencies with 5 or more sites</span><b>$65 / site</b></li>
						<li><span>Pay annually</span><b>1 month free</b></li>
					</ul>
					<p class="audit-fee"><b>$250 site audit</b> required to start, credited toward your first months of care or any cleanup work.</p>
				</div>
				<div class="care-list">
					<h3>Included</h3>
					<ul class="checks">
						<li>WordPress core, theme, and plugin updates, tested before they go live</li>
						<li>Daily offsite backups, kept for 30 days</li>
						<li>Restores when an update or maintenance causes a problem</li>
						<li>Uptime and security monitoring, with alerts when something is wrong</li>
						<li>A short monthly report</li>
					</ul>
					<p class="excl"><strong>Not included:</strong> content edits, new pages or features, design changes, restores caused by other changes, and full malware cleanup. These can be covered by a retainer or billed hourly.</p>
					<div class="audit">
						<h3>Every site starts with an audit</h3>
						<p>Before a site joins a care plan, I review it: code, plugins, hosting, and security. Most sites are accepted as they are. If something needs to be fixed first, I'll quote it. If a site isn't a good fit, I'll tell you why.</p>
						<a class="btn" href="#start" data-plan="care">Add a site</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="billing-sec">
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">How billing works</p>
				<h2>No surprises</h2>
			</div>
			<dl class="billing">
				<div><dt>Billing date</dt><dd>Plans are billed monthly, starting on the day you sign up. Your hours reset on the same date each month.</dd></div>
				<div><dt>Extra hours</dt><dd>Billed at the end of your billing month, at your plan's rate.</dd></div>
				<div><dt>Tracking</dt><dd>Time is tracked in 15-minute increments.</dd></div>
				<div><dt>Unused hours</dt><dd>Unused hours don't carry over to the next month.</dd></div>
				<div><dt>Changes</dt><dd>Plans can be changed or cancelled anytime. Changes take effect at the end of your current billing month.</dd></div>
				<div><dt>Payment</dt><dd>Card or bank transfer with automatic monthly billing, PayPal, or an invoice if your company pays through its own system.</dd></div>
			</dl>
		</div>
	</section>

	<section>
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">Questions</p>
				<h2>Frequently asked</h2>
			</div>
			<div class="faq">
				<details><summary>What if I use more hours than my plan includes?</summary><p>The extra hours are billed at the end of the month at your plan's rate, not the hourly rate. If it happens regularly, moving up a plan will usually save you money.</p></details>
				<details><summary>Can I change or cancel my plan?</summary><p>Yes, anytime. Changes take effect at the end of your current billing month, so you keep the hours you've already paid for.</p></details>
				<details><summary>Do unused hours roll over?</summary><p>No. A plan reserves my time for you each month, so hours don't carry over.</p></details>
				<details><summary>Do you host websites?</summary><p>Not at the moment. Your site stays with your current host, or I can recommend one. Care plans cover everything I manage on your site, and your host is responsible for the server.</p></details>
				<details><summary>Can you take over a site someone else built?</summary><p>Usually, yes. That's what the audit is for. I'll review the site and let you know if it needs any work before I take it on.</p></details>
				<details><summary>Who will I be working with?</summary><p>Me. I answer my own email and do the work myself.</p></details>
				<details><summary>Do you work with agencies?</summary><p>Yes, including white-label work. See For Agencies for details.</p></details>
			</div>
		</div>
	</section>

	<section class="start" id="start">
		<div class="wrap">
			<div class="section-head">
				<p class="eyebrow">Get started</p>
				<h2>Start a plan</h2>
				<p>Tell me about your site and what you usually need. Not sure which plan fits? Choose "Not sure yet" and I'll recommend one. If a care plan or a one-time project makes more sense, I'll tell you that too.</p>
			</div>
			<div class="form-card">
				<?php if ( 'sent' === $status ) : ?>
					<p class="sent notice" id="fd-plans-notice" role="status" aria-live="polite" tabindex="-1">Thanks, your inquiry was sent. I'll reply by email so we can talk through the details.</p>
					<p class="another" hidden><button type="button" class="link-btn" id="fd-plans-another">Send another inquiry</button></p>
				<?php elseif ( $errors ) : ?>
					<div class="err notice" id="fd-plans-notice" role="alert" aria-live="assertive" tabindex="-1">
						<p><strong>Please check the following and send again:</strong></p>
						<ul>
							<?php foreach ( $errors as $key => $message ) : ?>
								<li>
									<?php if ( isset( $error_targets[ $key ] ) ) : ?>
										<a href="#<?php echo esc_attr( $error_targets[ $key ] ); ?>"><?php echo esc_html( $message ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $message ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $status ) : ?>
					<?php // Bring the form section into view right away, before the rest of the page renders, so there is no visible jump. ?>
					<script>(function(){var s=document.getElementById('start'),n=document.getElementById('fd-plans-notice');if(s){s.scrollIntoView({block:'start',behavior:'instant'});}if(n){n.focus({preventScroll:true});}})();</script>
				<?php endif; ?>

				<form id="plan-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate<?php echo 'sent' === $status ? ' hidden' : ''; ?>>
					<input type="hidden" name="action" value="fd_plans_inquiry">
					<input type="hidden" name="fd_page" value="<?php echo esc_attr( get_queried_object_id() ); ?>">
					<?php wp_nonce_field( 'fd_plans_submit', 'fd_plans_nonce', false ); ?>
					<div class="hp" aria-hidden="true">
						<label for="f-hp">Leave this field empty</label>
						<input type="text" id="f-hp" name="fd_hp_field" value="" tabindex="-1" autocomplete="off">
					</div>

					<fieldset class="group full"<?php echo isset( $errors['plan'] ) ? ' aria-describedby="f-err"' : ''; ?>>
						<legend><span class="gnum">1</span> Retainer plan</legend>
						<div class="choices">
							<?php foreach ( $choices as $key => $inner ) : ?>
								<label class="choice<?php echo in_array( $key, array( 'unsure', 'none' ), true ) ? ' alt' : ''; ?>"><input type="radio" id="f-plan-<?php echo esc_attr( $key ); ?>" name="plan" value="<?php echo esc_attr( $key ); ?>"<?php checked( $plan, $key ); ?>><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<fieldset class="group full">
						<legend><span class="gnum">2</span> Care plan</legend>
						<label class="toggle" for="f-care"><input type="checkbox" id="f-care" name="care" value="1" role="switch"<?php checked( $care ); ?>><span class="switch" aria-hidden="true"></span><span>Add care plans for my sites <span class="c-rate" id="care-rate"><?php echo esc_html( $care_rate ); ?></span></span></label>
						<div class="field" id="f-sites-wrap"<?php echo $care ? '' : ' hidden'; ?>>
							<label class="lbl" for="f-sites">Website URLs <span class="opt">one per line</span></label>
							<textarea id="f-sites" name="sites" rows="3" placeholder="https://example.com"<?php echo $care ? ' required' : ''; ?><?php echo $field_attrs( 'sites', 'f-sites-help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $val( 'sites' ) ); ?></textarea>
							<?php echo $field_error( 'sites' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<p class="help" id="f-sites-help">Each site starts with a $250 audit, credited toward your first months of care or any cleanup work.</p>
						</div>
					</fieldset>

					<fieldset class="group full">
						<legend><span class="gnum">3</span> About you</legend>
						<div class="grid2">
							<div class="field"><label class="lbl" for="f-name">Name</label><input id="f-name" name="name" autocomplete="name" required value="<?php echo esc_attr( $val( 'name' ) ); ?>"<?php echo $field_attrs( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo $field_error( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div class="field"><label class="lbl" for="f-company">Company</label><input id="f-company" name="company" autocomplete="organization" value="<?php echo esc_attr( $val( 'company' ) ); ?>"></div>
							<div class="field full"><label class="lbl" for="f-email">Email</label><input id="f-email" name="email" type="email" autocomplete="email" required value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php echo $field_attrs( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo $field_error( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div class="field full"><label class="lbl" for="f-needs">What do you usually need help with?</label><textarea id="f-needs" name="needs"><?php echo esc_textarea( $val( 'needs' ) ); ?></textarea></div>
							<div class="field"><label class="lbl" for="f-start">Preferred start date</label><input id="f-start" name="start" type="date" required min="<?php echo esc_attr( $tomorrow ); ?>" value="<?php echo esc_attr( $val( 'start' ) ); ?>"<?php echo $field_attrs( 'start', 'f-start-help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo $field_error( 'start' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<p class="help" id="f-start-help">Your plan and billing start once we've talked and agreed on the details. For care plans, the site audit happens first.</p></div>
							<div class="field"><label class="lbl" for="f-pay">Preferred payment method</label>
								<select id="f-pay" name="payment"<?php echo $field_attrs( 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<?php foreach ( $options['payments'] as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $payment, $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php echo $field_error( 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div class="field" id="f-invoice-wrap"<?php echo 'invoice' === $payment ? '' : ' hidden'; ?>><label class="lbl" for="f-invoice">How does your company pay?</label><input id="f-invoice" name="invoice_details" placeholder="e.g. Bill.com, net 15" value="<?php echo esc_attr( $val( 'invoice_details' ) ); ?>"></div>
							</div>
							<div class="field full"><label class="lbl" for="f-notes">Anything else I should know? <span class="opt">optional</span></label><textarea id="f-notes" name="notes"><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea></div>
						</div>
					</fieldset>

					<p class="err" id="f-err" role="alert" aria-live="assertive"<?php echo isset( $errors['plan'] ) ? '' : ' hidden'; ?>>Pick a retainer plan, "Not sure yet", or add a care plan so I know what you're interested in.</p>
					<div class="form-foot">
						<button class="btn" type="submit">Send</button>
						<p>Nothing is charged until we've talked and agreed on the details.</p>
					</div>
				</form>
			</div>
		</div>
	</section>
</main>
</div> <!-- #main-content -->

<?php
get_footer();
