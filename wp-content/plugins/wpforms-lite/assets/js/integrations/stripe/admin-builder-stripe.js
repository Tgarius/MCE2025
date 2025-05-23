/* global wpforms_builder, wpforms_builder_stripe */

// noinspection ES6ConvertVarToLetConst
/**
 * Stripe builder function.
 *
 * @since 1.8.2
 */
// eslint-disable-next-line no-var
var WPFormsStripe = window.WPFormsStripe || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @since 1.8.2
	 *
	 * @type {Object}
	 */
	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.8.2
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Initialized once the DOM is fully loaded.
		 *
		 * @since 1.8.2
		 */
		ready() {
			if ( ! app.isLegacySettings() ) {
				return;
			}

			app.settingsDisplay();
			app.settingsConditions();

			app.bindUIActions();
		},

		/**
		 * Process various events as a response to UI interactions.
		 *
		 * @since 1.8.2
		 */
		bindUIActions() {
			$( document )
				.on( 'wpformsFieldDelete', app.disableNotifications )
				.on( 'wpformsSaved', app.requiredFieldsCheck )
				.on( 'wpformsFieldUpdate', app.settingsDisplay )
				.on( 'wpformsFieldUpdate', app.settingsConditions );

			$( '#wpforms-panel-field-stripe-recurring-email' ).on( 'change', app.resetEmailAlertErrorClass );
		},

		/**
		 * Toggles visibility of the Stripe settings.
		 *
		 * If a credit card field has been added, then reveal the settings.
		 * Otherwise, hide them.
		 *
		 * @since 1.8.2
		 */
		settingsDisplay() {
			const $alert = $( '#wpforms-stripe-credit-card-alert' );
			const $content = $( '#stripe-provider' );

			// Check if any Credit Card fields were added to the form.
			const ccFieldsAdded = wpforms_builder_stripe.field_slugs.filter( function( fieldSlug ) {
				const $el = $( '.wpforms-field-option-' + fieldSlug );

				return $el.length ? $el : null;
			} );

			if ( ccFieldsAdded.length ) {
				$alert.hide();
				$content.find( '#wpforms-stripe-new-interface-alert, .wpforms-stripe-notice-info, .wpforms-panel-field, .wpforms-conditional-block-panel, h2' ).show();
			} else {
				$alert.show();
				$content.find( '#wpforms-stripe-new-interface-alert, .wpforms-stripe-notice-info, .wpforms-panel-field, .wpforms-conditional-block-panel, h2' ).hide();
				$content.find( '#wpforms-panel-field-stripe-enable' ).prop( 'checked', false ).trigger( 'change' );
			}
		},

		/**
		 * Toggles the visibility of the related settings.
		 *
		 * @since 1.8.2
		 */
		settingsConditions() {
			$( '#wpforms-panel-field-stripe-enable' ).conditions( {
				conditions: {
					element: '#wpforms-panel-field-stripe-enable',
					type: 'checked',
					operator: 'is',
				},
				actions: {
					if: {
						element: '.wpforms-panel-content-section-stripe-body',
						action: 'show',
					},
					else: {
						element: '.wpforms-panel-content-section-stripe-body',
						action:  'hide',
					},
				},
				effect: 'appear',
			} );

			$( '#wpforms-panel-field-stripe-recurring-enable' ).conditions( {
				conditions: {
					element: '#wpforms-panel-field-stripe-recurring-enable',
					type: 'checked',
					operator: 'is',
				},
				actions: {
					if: {
						element: '#wpforms-panel-field-stripe-recurring-period-wrap,#wpforms-panel-field-stripe-recurring-conditional_logic-wrap,#wpforms-conditional-groups-payments-stripe-recurring,#wpforms-panel-field-stripe-recurring-email-wrap,#wpforms-panel-field-stripe-recurring-name-wrap',
						action: 'show',
					},
					else: {
						element: '#wpforms-panel-field-stripe-recurring-period-wrap,#wpforms-panel-field-stripe-recurring-conditional_logic-wrap,#wpforms-conditional-groups-payments-stripe-recurring,#wpforms-panel-field-stripe-recurring-email-wrap,#wpforms-panel-field-stripe-recurring-name-wrap',
						action:  'hide',
					},
				},
				effect: 'appear',
			} );
		},

		/**
		 * On form save notify users about required fields.
		 *
		 * @since 1.8.2
		 */
		requiredFieldsCheck() {
			if (
				! $( '#wpforms-panel-field-stripe-enable' ).is( ':checked' ) ||
				! $( '#wpforms-panel-field-stripe-recurring-enable' ).is( ':checked' )
			) {
				return;
			}

			const $emailField = $( '#wpforms-panel-field-stripe-recurring-email' );

			if ( $emailField.val() ) {
				return;
			}

			$emailField.addClass( 'wpforms-required-field-error' );

			let alertMessage = wpforms_builder.stripe_recurring_email;

			if ( ! $( '.wpforms-panel-content-section-stripe' ).is( ':visible' ) ) {
				alertMessage += ' ' + wpforms_builder.stripe_recurring_settings;
			}

			$.alert( {
				title: wpforms_builder.stripe_recurring_heading,
				content: alertMessage,
				icon: 'fa fa-exclamation-circle',
				type: 'red',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
					},
				},
				onOpen() {
					$( '.wpforms-stripe-settings-redirect' ).on( 'click', app.settingsRedirect );
				},
			} );
		},

		/**
		 * Redirect to the settings tab.
		 *
		 * @since 1.9.5
		 */
		settingsRedirect() {
			// Open the Stripe settings tab.
			$( '.wpforms-panel-payments-button' ).trigger( 'click' );
			$( '.wpforms-panel-sidebar-section-stripe' ).trigger( 'click' );

			// Scroll to the Stripe settings.
			window.location.href = window.location.pathname + window.location.search + '#wpforms-panel-field-stripe-enable_recurring-wrap';

			// Close the alert.
			$( this ).closest( '.jconfirm-box' ).find( '.btn-confirm' ).trigger( 'click' );
		},

		/**
		 * Maybe reset required email field error class.
		 *
		 * @since 1.9.5
		 */
		resetEmailAlertErrorClass() {
			$( this ).toggleClass( 'wpforms-required-field-error', ! $( this ).val() );
		},

		/**
		 * Disable notifications.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} e    Event object.
		 * @param {number} id   Field ID.
		 * @param {string} type Field type.
		 */
		disableNotifications( e, id, type ) {
			if ( ! wpforms_builder_stripe.field_slugs.includes( type ) ) {
				return;
			}

			const $notificationWrap = $( '.wpforms-panel-content-section-notifications [id*="-stripe-wrap"]' );

			$notificationWrap.find( 'input[id*="-stripe"]' ).prop( 'checked', false );
			$notificationWrap.addClass( 'wpforms-hidden' );
		},

		/**
		 * Determine is legacy settings is loaded.
		 *
		 * @since 1.8.4
		 *
		 * @return {boolean} True is legacy settings loaded.
		 */
		isLegacySettings() {
			return $( '#wpforms-panel-field-stripe-enable' ).length;
		},
	};

	// Provide access to public functions/properties.
	return app;
}( document, window, jQuery ) );

// Initialize.
WPFormsStripe.init();
