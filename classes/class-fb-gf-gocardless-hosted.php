<?php
/**
 * Fat Beehive Gravity Forms GoCardless payment method.
 *
 * @package fatbeehive
 */

/**
* Recommended WP Plugin security in case server is misconfigured.
*
* @see https://codex.wordpress.org/Writing_a_Plugin
*/
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
	GFForms::include_payment_addon_framework();

	/**
	 * Fb_Gf_Gocardless_Hosted.
	 */
	class Fb_Gf_Gocardless_Hosted extends GFPaymentAddOn {

		/**
		 * Version.
		 *
		 * @var string
		 */
		protected $_version = '2.3.7';

		/**
		 * Min version.
		 *
		 * @var string
		 */
		protected $_min_gravityforms_version = '1.8.12';

		/**
		 * Slug.
		 *
		 * @var string
		 */
		protected $_slug = 'gravityformsfbgfgocardlesshosted';

		/**
		 * Plugin class path.
		 *
		 * @var string
		 */
		protected $_path = 'classes/class-fg-gf-gocardless-hosted.php';

		/**
		 * Plugin class full path.
		 *
		 * @var string
		 */
		protected $_full_path = __FILE__;

		/**
		 * Long title.
		 *
		 * @var string
		 */
		protected $_title = 'FB Gravity Forms Go Cardless Hosted Add-On';

		/**
		 * Short title.
		 *
		 * @var string
		 */
		protected $_short_title = 'GoCardless Hosted';

		/**
		 * Support callbacks.
		 *
		 * @var string
		 */
		protected $_supports_callbacks = true;

		/**
		 * Requires credit card.
		 *
		 * @var string
		 */
		protected $_requires_credit_card = false;

		/**
		 * If available, contains an instance of this class.
		 *
		 * @var object|null $_instance
		 */
		private static $_instance = null;

		/**
		 * Storage for the redirect flow response.
		 *
		 * @var bool|mixed $redirect_flow_response
		 */
		private $redirect_flow_response = false;

		/**
		 * Has this feed storage.
		 *
		 * @var bool|null $has_this_feed
		 */
		private $has_this_feed = null;

		/**
		 * Constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'init', array( $this, 'set_session_cookie_value' ) );
			add_action( 'pre_get_posts', array( $this, 'success_redirect_to_gocardless_confirmation_url' ) );
			add_filter( 'query_vars', array( $this, 'add_to_query_vars' ) );
			add_filter( 'allowed_redirect_hosts' , array( $this, 'allowed_redirect_hosts' ) );
			add_action( 'gform_field_standard_settings', array( $this, 'add_field_options_gform_field_standard_settings' ), 10, 2 );
			add_action( 'gform_editor_js', array( $this, 'add_field_options_editor_script' ) );
			add_filter( 'gform_tooltips', array( $this, 'add_field_options_encryption_tooltips' ) );
		}

		/**
		 * Implements admin_notices action.
		 */
		public function admin_notices() {
			if ( ! defined( 'FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT' ) || ! defined( 'FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN' ) ) {
				?>
				<div class="notice notice-error">
					<strong>Please define the gocardless hosted requirements in your wp-config.php</strong>
					<p>define( 'FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT', 'sandbox OR live' );<br />
					define( 'FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN', 'See your GoCardless dashboard per environment' );</p>
				</div>
				<?php
			} elseif ( 0 !== strpos( FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN, FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT ) ) {
				?>
				<div class="notice notice-error">
					<strong>Please check the gocardless hosted requirements in your wp-config.php</strong>
					<p>It appears your token and environment do not match. Ensure you use a live token for the live environment and sandbox token for the sandbox environment.</p>
				</div>
				<?php
			} elseif ( 'live' === FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT && ! is_ssl() ) {
				?>
				<div class="notice notice-error">
					<strong>Gocardless hosted requires SSL for the live environment</strong>
					<p>Please ensure your site is served over SSL or your users will be unable to checkout with gocardless.</p>
				</div>
				<?php
			}
		}

		/**
		 * Add supported notification events.
		 *
		 * @since  Unknown
		 * @access public
		 *
		 * @used-by GFFeedAddOn::notification_events()
		 * @uses    GFFeedAddOn::has_feed()
		 *
		 * @param array $form The form currently being processed.
		 *
		 * @return array|false The supported notification events. False if feed cannot be found within $form.
		 */
		public function supported_notification_events( $form ) {

			// If this form does not have a Stripe feed, return false.
			if ( ! $this->has_feed( $form['id'] ) ) {
				return false;
			}

			// Return notification events.
			return array(
				'create_subscription' => esc_html__( 'Direct Debit Created', $this->_slug ),
			);
		}

		/**
		 * Check if this feed is active.
		 *
		 * @param int $form_id The form id.
		 *
		 * @return boolean Whether this feed is active.
		 */
		private function has_this_feed( $form_id ) {
			$this->has_this_feed = false;
			$feeds = $this->get_feeds( $form_id );
			if ( $feeds ) {
				foreach ( $feeds as $feed ) {
					if ( $feed['addon_slug'] === $this->_slug ) {
						$this->has_this_feed = true;
					}
				}
			}
			return $this->has_this_feed;
		}

		/**
		 * Implements gform_field_standard_settings.
		 *
		 * @param int $position The position on the form.
		 * @param int $form_id The form id.
		 */
		public function add_field_options_gform_field_standard_settings( $position, $form_id ) {
			if ( 25 === $position && $this->has_this_feed( $form_id ) ) {
				?>
				<li class="gocardless_email_setting field_setting">
					<label for="field_admin_label">
						<?php esc_html_e( 'Pre-populate the GoCardless email address', 'gravityforms' ); ?>
						<?php gform_tooltip( 'form_gocardless_email_setting_value' ) ?>
					</label>
					<input type="checkbox" id="field_gocardless_email_setting" onclick="SetFieldProperty('gocardlessEmailSetting', this.checked);" /> Pre-populate the Go Cardless email address with this.
				</li>
				<li class="gocardless_name_setting field_setting">
					<label for="field_admin_label">
						<?php esc_html_e( 'Pre-populate the GoCardless name', 'gravityforms' ); ?>
						<?php gform_tooltip( 'form_gocardless_name_setting_value' ) ?>
					</label>
					<input type="checkbox" id="field_gocardless_name_setting" onclick="SetFieldProperty('gocardlessNameSetting', this.checked);" /> Pre-populate the Go Cardless first name and last name with this.
				</li>
				<li class="gocardless_address_setting field_setting">
					<label for="field_admin_label">
						<?php esc_html_e( 'Pre-populate the GoCardless address', 'gravityforms' ); ?>
						<?php gform_tooltip( 'form_gocardless_address_setting_value' ) ?>
					</label>
					<input type="checkbox" id="field_gocardless_address_setting" onclick="SetFieldProperty('gocardlessAddressSetting', this.checked);" /> Pre-populate the Go Cardless address with this.
				</li>
				<?php
			}
		}

		/**
		 * Implements editor_script.
		 */
		public function add_field_options_editor_script() {
			?>
			<script type='text/javascript'>
				// Adding settings to particular field types.
				fieldSettings.email += ', .gocardless_email_setting';
				fieldSettings.name += ', .gocardless_name_setting';
				fieldSettings.address += ', .gocardless_address_setting';

				//binding to the load field settings event to initialize the checkbox
				jQuery(document).bind('gform_load_field_settings', function(event, field, form){
					jQuery('#field_gocardless_email_setting').attr('checked', field.gocardlessEmailSetting == true);
					jQuery('#field_gocardless_name_setting').attr('checked', field.gocardlessNameSetting == true);
					jQuery('#field_gocardless_address_setting').attr('checked', field.gocardlessAddressSetting == true);
				});
			</script>
			<?php
		}

		/**
		 * Implements encryption_tooltips.
		 *
		 * @param array $tooltips The tooltips.
		 *
		 * @return array The tooltips.
		 */
		public function add_field_options_encryption_tooltips( $tooltips ) {
			$tooltips['form_gocardless_email_setting_value'] = '<h6>Go Cardless</h6>Check this box to prepopulate the Go Cardless email address with this field.';
			$tooltips['form_gocardless_name_setting_value'] = '<h6>Go Cardless</h6>Check this box to prepopulate the Go Cardless first and last name with this field.';
			$tooltips['form_gocardless_address_setting_value'] = '<h6>Go Cardless</h6>Check this box to prepopulate the Go Cardless address with this field.';
			return $tooltips;
		}

		/**
		 * Implements filter query_vars.
		 *
		 * @param return array $vars The available query vars.
		 *
		 * @return array The updated vars.
		 */
		function add_to_query_vars( $vars ) {
			$vars[] = 'gf_gocardless';
			return $vars;
		}

		/**
		 * Implements filter allowed_redirect_hosts.
		 *
		 * @param array $hosts The allowed hosts.
		 *
		 * @return array The updated allowed hosts.
		 */
		function allowed_redirect_hosts( $hosts ) {
			if ( defined( 'FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT' ) ) {
				$hosts[] = 'gocardless.com';
				if ( 'live' === FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT ) {
					$hosts[] = 'pay.gocardless.com';
				} else {
					$hosts[] = 'pay-sandbox.gocardless.com';
				}
			}
			return $hosts;
		}

		/**
		 * Set a session cookie for additional GoCardless validation.
		 */
		public function set_session_cookie_value() {
			if ( ! isset( $_COOKIE[ $this->_slug ] ) || ! $_COOKIE[ $this->_slug ] ) {
				$value = sanitize_title_with_dashes( wp_generate_password( rand( 25, 35 ) ) );
				setcookie( $this->_slug, $value, ( time() + ( 30 * DAY_IN_SECONDS ) ), '/', $_SERVER['HTTP_HOST'] );
			}
		}

		/**
		 * Retrieve the session cookie.
		 *
		 * @return string The session cookie value.
		 */
		public function get_session_cookie_value() {
			if ( ! isset( $_COOKIE[ $this->_slug ] ) || $_COOKIE[ $this->_slug ] ) {
				$this->set_session_cookie_value();
			}
			return sanitize_text_field( wp_unslash( $_COOKIE[ $this->_slug ] ) );
		}

		/**
		 * Returns an instance of this class, and stores it in the $_instance property.
		 *
		 * @return object $_instance An instance of this class.
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Feed settings.
		 *
		 * @return array Default settings.
		 */
		public function feed_settings_fields() {
			$default_settings = parent::feed_settings_fields();

			// Set transaction type to donation.
			$transaction_type = $this->get_field( 'transactionType', $default_settings );
			$transaction_type['choices'] = array( reset( $transaction_type['choices'] ) );
			$transaction_type['choices'][] = array(
				'label' => __( 'Direct Debit', 'gravityformsfbgfgocardlesshosted' ),
				'value' => 'product',
			);
			$default_settings = $this->replace_field( 'transactionType', $transaction_type, $default_settings );

			// Add html description.
			$default_settings[0]['description'] = '<strong>Important:</strong><p>Please ensure you add a "Product" field, for instance, with the field type "User defined amount" and check the box "Allow field to be populated dynamically" under advanced options. It is recommended to use a redirect or page confirmation so the visitor is not redirected back to the form once they complete their direct debit sign-up. Note that redirect and page conditional confirmations are not supported at this time. Your redirect URL must use https:// for the live environment or GoCardless will reject it. You can use the "name", "email", and "address" Gravity Form field types to pre-propulate the hosted GoCardless page.<p>';

			// Remove billing information.
			$billing_info   = $this->get_field( 'billingInformation', $default_settings );
			$billing_info['field_map'] = array();
			$default_settings = $this->replace_field( 'billingInformation', $billing_info, $default_settings );

			// Remove fields that aren't needed.
			$default_settings = $this->remove_field( 'options', $default_settings );
			$default_settings = $this->remove_field( 'conditionalLogic', $default_settings );

			return $default_settings;
		}

		/**
		 * Redirect form to go cardless.
		 *
		 * @param object $feed The feed.
		 * @param array  $submission_data The submission data.
		 * @param array  $form The form.
		 * @param object $entry The submission data.
		 *
		 * @return array The expected array by GFPaymentAddOn.
		 *
		 * @see https://docs.gravityforms.com/gfpaymentaddon/#authorize-
		 */
		public function redirect_url( $feed, $submission_data, $form, $entry ) {

			// Store payment amount.
			gform_update_meta( $entry['id'], 'gocardless_direct_debit_amount', $submission_data['payment_amount'] );

			$gocardless_client = $this->get_gocardless_client();
			if ( $gocardless_client ) {

				// Get token.
				$session_token = $this->get_session_cookie_value();

				// Query string var for success page.
				$success_key = $entry['id'] . '___' . sanitize_title_for_query( wp_generate_password( rand( 25, 35 ), false ) );
				gform_update_meta( $entry['id'], 'gocardless_success_key', $success_key );

				$prefilled_customer = array(
					'given_name' => '',
					'family_name' => '',
					'email' => '',
					'address_line1' => '',
					'city' => '',
					'postal_code' => '',
				);
				foreach ( $form['fields'] as $field ) {
					switch ( $field->type ) {
						case 'name':
							$setting = 'gocardlessNameSetting';
							if ( isset( $field->{$setting} ) && $field->{$setting} ) {
								foreach ( $field->inputs as $input ) {
									if ( 'First' === $input['label'] ) {
										$prefilled_customer['given_name'] = $entry[ $input['id'] ];
									} elseif ( 'Last' === $input['label'] ) {
										$prefilled_customer['family_name'] = $entry[ $input['id'] ];
									}
								}
							}
							break;
						case 'address':
							$setting = 'gocardlessAddressSetting';
							if ( isset( $field->{$setting} ) && $field->{$setting} ) {
								foreach ( $field->inputs as $input ) {
									if ( 'Street Address' === $input['label'] ) {
										$prefilled_customer['address_line1'] = $entry[ $input['id'] ];
									} elseif ( 'City' === $input['label'] ) {
										$prefilled_customer['city'] = $entry[ $input['id'] ];
									} elseif ( 'ZIP / Postal Code' === $input['label'] ) {
										$prefilled_customer['postal_code'] = $entry[ $input['id'] ];
									}
								}
							}
							break;
						case 'email':
							$setting = 'gocardlessEmailSetting';
							if ( isset( $field->{$setting} ) && $field->{$setting} ) {
								$prefilled_customer['email'] = $entry[ $field->id ];
							}
							break;
					} // End switch().
				} // End foreach().

				// Create the redirect flow.
				$response = $gocardless_client->redirectFlows()->create( array(
					'params' => array(
						'description' => htmlspecialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
						'session_token' => $this->get_session_cookie_value(),
						'success_redirect_url' => add_query_arg( array(
							'gf_gocardless' => $success_key,
						), site_url() ),
						'prefilled_customer' => $prefilled_customer,
					),
				) );

				if ( $response && is_object( $response ) && isset( $response->api_response->status_code ) && 201 === (int) $response->api_response->status_code ) {

					// Successfully generated a checkout flow, store the flow
					// ID and send the user off to GoCardless.
					$redirect_flow = $response->api_response->body->redirect_flows;
					gform_update_meta( $entry['id'], 'gocardless_redirect_flow_id', $redirect_flow->id );
					wp_safe_redirect( $redirect_flow->redirect_url );
					exit();

				} elseif ( $response && is_object( $response ) && isset( $response->api_response->error ) ) {
					$message = 'GoCardless error "' . sanitize_text_field( $response->api_response->error->type ) . '": ' . sanitize_text_field( $response->api_response->error->message );
					die( esc_html( $message ) );
				} else {
					die( 'An unknown GoCardless error occured' );
				}
			} // End if().
			return $return;
		}

		/**
		 * Success page send the user to gocardless confirmation page.
		 */
		public function success_redirect_to_gocardless_confirmation_url() {

			// Only attempt process if we have the query var.
			if ( ! get_query_var( 'gf_gocardless', '' ) ) {
				return;
			}

			// Don't process more than once.
			if ( $this->redirect_flow_response ) {
				return;
			}

			// Attempt to process.
			$entry = $this->get_gform_entry_from_success_url();
			$form = GFAPI::get_form( $entry['form_id'] );
			if ( $entry && $form ) {
				$response = $this->get_gocardless_complete_checkout_response_for_entry( $entry );
				if ( $response && is_object( $response ) && isset( $response->api_response->status_code ) && 200 === (int) $response->api_response->status_code ) {
					$redirect_flow = $response->api_response->body->redirect_flows;
					$mandate_id = $redirect_flow->links->mandate;

					// Mark the payment as completed.
					$this->mark_payment_as_completed( $entry, $mandate_id );

					// Set up the payment.
					$gocardless_client = $this->get_gocardless_client();

					$action = apply_filters('fb_gf_gocardless_action', 'payment', $entry, $form);

					if ( 'payment' == $action ) {
						$gocardless_client->payments()->create( array(
							'params' => array(
								'amount' => (int) ( ( gform_get_meta( $entry['id'], 'gocardless_direct_debit_amount' ) ) * 100 ),
								'currency' => 'GBP',
								'name' => apply_filters('fb_gf_gocardless_payment_name', '', $entry, $form),
								'links' => array(
									'mandate' => $mandate_id,
								),
								'metadata' => array(
									'site_url' => site_url(),
									'gravity_forms_entry_id' => $entry['id'],
								),
							),
						) );
					} elseif ( 'subscription' == $action ) {
						$gocardless_client->subscriptions()->create( array(
							'params' => array(
								'amount' => (int) ( ( gform_get_meta( $entry['id'], 'gocardless_direct_debit_amount' ) ) * 100 ),
								'currency' => 'GBP',
								'interval' => apply_filters('fb_gf_gocardless_subscription_interval', 1, $entry, $form),
								'interval_unit' => apply_filters('fb_gf_gocardless_subscription_interval_unit', 'yearly', $entry, $form),
								'name' => apply_filters('fb_gf_gocardless_subscription_name', '', $entry, $form),
								'links' => array(
									'mandate' => $mandate_id,
								),
								'metadata' => array(
									'site_url' => site_url(),
									'gravity_forms_entry_id' => $entry['id'],
								),
							),
						) );
					}

					// Determine redirect url from gravity forms settings.
					$success_redirect_url = false;
					if ( isset( $form['confirmations'] ) && $form['confirmations'] ) {
						foreach ( $form['confirmations'] as $confirmation ) {
							if ( ! $success_redirect_url && 'page' === $confirmation['type'] ) {
								$success_redirect_url = get_permalink( $confirmation['pageId'] );
							} elseif ( ! $success_redirect_url && 'redirect' === $confirmation['type'] ) {
								$success_redirect_url = $confirmation['url'];
							}
						}
					}

					// Fallback to the gocardless confirmation url.
					if ( ! $success_redirect_url ) {
						$success_redirect_url = $redirect_flow->confirmation_url;
					}

					// Redirect the user to the gocardless hosted confirmation.
					wp_safe_redirect( $success_redirect_url );
					exit();
				} // End if().
			} else {
				die( 'We were unable to retrieve information from the payment gateway about your direct debit. Please contact us for help.' );
			} // End if().

			// Failed to process.
			die( 'We were unable to confirm your direct debit. Please contact us for help.' );
		}

		/**
		 * Get the gravity forms from the query string and session var combo.
		 *
		 * @return object|bool The gravity forms entry.
		 */
		protected function get_gform_entry_from_success_url() {
			$visitor_success_key = get_query_var( 'gf_gocardless', '' );
			if ( $visitor_success_key ) {
				$parts = explode( '___', $visitor_success_key );
				$entry_id = reset( $parts );
				$meta_success_key = gform_get_meta( $entry_id, 'gocardless_success_key' );
				if ( $meta_success_key && (string) $meta_success_key === (string) $visitor_success_key ) {
					return GFAPI::get_entry( $entry_id );
				} else {
					die( 'We were unable to retrieve your form submission to confirm your direct debit. Please contact us for help.' );
				}
			}
			return false;
		}

		/**
		 * Get gocardless confirmation url for entry.
		 *
		 * @param object $entry Gforms entry.
		 *
		 * @return bool|string The gocardless confirmation url or false.
		 */
		protected function get_gocardless_complete_checkout_response_for_entry( $entry ) {
			if ( $this->redirect_flow_response ) {
				return $this->redirect_flow_response;
			}

			$gocardless_redirect_flow_id = gform_get_meta( $entry['id'], 'gocardless_redirect_flow_id' );
			$gocardless_client = $this->get_gocardless_client();
			if ( $gocardless_redirect_flow_id ) {
				try {
					$gocardless_client = $this->get_gocardless_client();
					$this->redirect_flow_response = $gocardless_client->redirectFlows()->complete( $gocardless_redirect_flow_id, array(
						'params' => array(
							'session_token' => $this->get_session_cookie_value(),
						),
					) );
				} catch ( Exception $e ) {
					print esc_html( 'Caught exception-: ' . $e->getMessage() );
				}
				return $this->redirect_flow_response;
			}
			return false;
		}

		/**
		 * Mark the entry as paid.
		 *
		 * @param object $entry Gforms entry.
		 * @param string $mandate_id The direct debit mandate id.
		 */
		protected function mark_payment_as_completed( $entry, $mandate_id ) {

			// Payment action configuration.
			$action = array();
			$action['is_success']	    = true;
			$action['transaction_id']   = $mandate_id;
			$action['payment_status']   = 'Direct debit mandate setup completed';
			$action['payment_date']     = gmdate( 'Y-m-d H:i:s' );
			$action['payment_method']   = $this->_slug;
			$action['payment_amount']   = '';
			$action['transaction_type'] = 'product';
			$action['type']             = 'create_subscription';

			gform_update_meta( $entry['id'], 'gocardless_direct_debit_mandate_id', $mandate );

			// Add the completed payment setup record.
			$this->complete_payment( $entry, $action );
		}

		/**
		 * Get the GoCardless client.
		 *
		 * @return bool|\GoCardlessPro\Client The GoCardless client.
		 */
		protected function get_gocardless_client() {
			if ( ! defined( 'FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT' ) ) {
				return false;
			}
			if ( 'live' === FB_GF_GOCARDLESS_HOSTED_ENVIRONMENT ) {
				$environment = \GoCardlessPro\Environment::LIVE;
			} else {
				$environment = \GoCardlessPro\Environment::SANDBOX;
			}
			$client = new \GoCardlessPro\Client( array(
				'access_token' => ( defined( 'FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN' ) ? FB_GF_GOCARDLESS_HOSTED_READWRITE_TOKEN : '' ),
				'environment'  => $environment,
			) );
			return $client;
		}
	}

	$fb_gf_gocardless_hosted = new Fb_Gf_Gocardless_Hosted();
} // End if().
