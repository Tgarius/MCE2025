<?php

namespace WeeConnectPay\Integrations;

class DismissibleNewFeatureNotice {
	private $displayedNewFeatureNoticeName;
	/**
	 * @var string
	 */
	private $messageHtml;

	public function __construct( $displayedNewFeatureNoticeName, $messageHtml ) {
		$this->displayedNewFeatureNoticeName = $displayedNewFeatureNoticeName;
		$this->messageHtml                   = $messageHtml;

		add_action( 'admin_notices', array( $this, 'display_notice' ) );
		add_action( 'admin_init', array( $this, 'dismiss_notice' ) );
	}

	public function display_notice() {

		// Check if the notice should be displayed
		if ( ! get_option( $this->displayedNewFeatureNoticeName, false ) ) {
			$dismiss_url = esc_url( add_query_arg( $this->displayedNewFeatureNoticeName, 'dismiss' ) );
			$notice      = '<div class="notice notice-info weeconnectpay-notice"><p>'
			               . $this->messageHtml . '</p>
							<a href="' . $dismiss_url . '" class="dismiss-button" aria-label="Dismiss the notice">
						        <span class="dashicons dashicons-no-alt"></span>
						    </a>
					  </div>';

			echo $notice;

			?>
            <style>
                .weeconnectpay-notice {
                    position: relative;
                    padding: 20px;
                    background-color: #f9f9f9;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    margin: 20px 0;
                }

                .dismiss-button {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    display: block;
                    width: 20px;
                    height: 20px;
                    font-size: 24px;
                    line-height: 20px;
                    text-align: center;
                    cursor: pointer;
                    color: #555;
                    text-decoration: none;
                    transition: color 0.3s;
                }

                .dismiss-button:hover {
                    color: #333;
                }
            </style>
			<?php
		}
	}

	public function dismiss_notice() {
		if ( isset( $_GET[ $this->displayedNewFeatureNoticeName ] ) && $_GET[ $this->displayedNewFeatureNoticeName ] === 'dismiss' ) {
			update_option( $this->displayedNewFeatureNoticeName, true );
		}
	}
}
