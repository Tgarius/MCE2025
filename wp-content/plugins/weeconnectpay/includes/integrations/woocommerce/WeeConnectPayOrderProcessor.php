<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

use Exception;
use InvalidArgumentException;
use WC_Order;
use WeeConnectPay\Api\Requests\CreateCloverCustomerRequest;
use WeeConnectPay\Api\Requests\CreateCloverOrderChargeRequest;
use WeeConnectPay\Api\Requests\CreateCloverOrderCustomTenderChargeRequest;
use WeeConnectPay\CloverReceiptsHelper;
use WeeConnectPay\Dependencies\Psr\Http\Message\ResponseInterface;
use WeeConnectPay\Integrations\GoogleRecaptcha;
use WeeConnectPay\Integrations\IntegrationSettings;
use WeeConnectPay\Integrations\RecaptchaVerifier;
use WeeConnectPay\Integrations\LogService;
use WeeConnectPay\Exceptions\WeeConnectPayException;
use WeeConnectPay\Exceptions\Codes\ExceptionCode;

class WeeConnectPayOrderProcessor
{
    protected IntegrationSettings $integrationSettings;

    public function __construct($integrationSettings)
    {
        $this->integrationSettings = $integrationSettings;
    }

    /**
     * Main method to process the order payment.
     * This method is called in two different contexts:
     * 1. By WC_Gateway_Weeconnectpay::process_payment() - For normal transactions where order total > 0
     * 2. By WC_Gateway_Weeconnectpay::weeconnectpay_maybe_handle_zero_total_order() - For orders where:
     *    - Total is 0 (possibly due to gift cards being applied)
     *    - There are pending custom tenders that need to be processed through Clover
     *
     * @param WC_Order $order
     * @param array $postData Typically $_POST data from checkout form. May be empty when handling zero-total orders.
     *
     * @return array
     */
    public function processOrderPayment(WC_Order $order, array $postData): array
    {
        try {
            LogService::debug(sprintf(
                'Starting payment processing for WooCommerce order (#%d)',
                $order->get_id()
            ));
            
            // Get pending custom tenders total before proceeding
            $pendingCustomTendersTotal = WeeConnectPayCustomTenderHelper::getCustomTendersPendingTotal($order);
            $isZeroTotalOrder = $order->get_total() <= 0;
            $hasCustomTenders = $pendingCustomTendersTotal > 0;

            LogService::debug(sprintf(
                'Initial state for WooCommerce order (#%d): isZeroTotal=%s, hasCustomTenders=%s, pendingCustomTendersTotal=%.2f %s',
                $order->get_id(),
                $isZeroTotalOrder ? 'true' : 'false',
                $hasCustomTenders ? 'true' : 'false',
                $pendingCustomTendersTotal / 100,
                $order->get_currency()
            ));

            // Early return for zero-total orders with no custom tenders
            // This can happen when the hook runs for zero-total orders where no processing is needed
            if ($isZeroTotalOrder && !$hasCustomTenders) {
                LogService::info(sprintf(
                    'Skipping payment processing for zero-total WooCommerce order (#%d) with no custom tenders',
                    $order->get_id()
                ));
                LogService::debug('Early return - zero total order with no custom tenders for WooCommerce order (#%d)', $order->get_id());
                return $this->successResponse($order);
            }

            // zero-total orders where processing is needed for a pending custom tender
            if ($isZeroTotalOrder) {
                LogService::info(sprintf(
                    'Processing zero-total WooCommerce order (#%d) with pending custom tenders (total: %.2f %s)',
                    $order->get_id(),
                    $pendingCustomTendersTotal / 100,
                    $order->get_currency()
                ));
            }
            // 1. Validate the honeypot field (if enabled)
            // We check honeypot even for zero-total orders as it's a basic bot protection
            if ($this->integrationSettings->getHoneypotFieldOrDefault()) {
                
                if ($this->isHoneypotFilled($postData)) {
                    LogService::warning(sprintf(
                        'Returning failure - honeypot triggered for WooCommerce order (#%d)',
                        $order->get_id()
                    ));
                    $this->handleHoneypotFailure($order, $postData);
                    return $this->failureResponse();
                }
                
                LogService::debug('Honeypot check passed for order #' . $order->get_id());
            }

            // 2. Verify Google reCAPTCHA if enabled
            // Only verify for orders with actual payment (total > 0)
            if (!$isZeroTotalOrder && GoogleRecaptcha::isEnabledAndReady()) {
                LogService::info('Verifying reCAPTCHA for non-zero total order #' . $order->get_id());
                $recaptchaResult = $this->handleRecaptcha($order, $postData);
                if (is_array($recaptchaResult)) {
                    LogService::warning('reCAPTCHA verification failed (low score) for order #' . $order->get_id());
                    LogService::debug('Returning recaptcha result array due to low score');
                    return $recaptchaResult;
                }
                if (!$recaptchaResult) {
                    LogService::error('reCAPTCHA verification failed for order #' . $order->get_id());
                    LogService::debug('Returning failure - recaptcha verification failed');
                    return $this->failureResponse();
                }
            }

            // 3. Extract payment token and card details
            // Only needed for orders with remaining balance after custom tenders
            $cardDetails = null;
            if (!$isZeroTotalOrder) {
                $cardDetails = $this->extractCardDetails($postData);
                if (empty($cardDetails['token'])) {
                    LogService::error(sprintf(
                        'Returning failure - missing card token for WooCommerce order (#%d)',
                        $order->get_id()
                    ));
                    return $this->failureResponse();
                }
                LogService::debug(sprintf(
                    'Successfully extracted card details for WooCommerce order (#%d)',
                    $order->get_id()
                ));
            }

            // 4. Create or retrieve a Clover customer
            // Always needed as custom tenders require a customer in Clover
            LogService::info('Creating/retrieving Clover customer for order #' . $order->get_id());
            $customerPayload = $this->buildCustomerPayload($order);
            $customerId = $this->createCloverCustomer($customerPayload, $order);
            if (!$customerId) {
                LogService::error(sprintf(
                    'Returning failure - customer creation failed for WooCommerce order (#%d)',
                    $order->get_id()
                ));
                return $this->failureResponse();
            }

            // 5. Prepare the Clover order (or retrieve the UUID if it exists)
            // Always needed as custom tenders require an order in Clover
            LogService::info('Creating/retrieving Clover order for order #' . $order->get_id());
            $cloverOrderUuid = $this->prepareCloverOrder($order, $customerId);
            if (!$cloverOrderUuid) {
                LogService::error(sprintf(
                    'Returning failure - Clover order preparation failed for WooCommerce order (#%d)',
                    $order->get_id()
                ));
                return $this->failureResponse();
            }

            // 6. Process custom tenders (if any) and adjust amount due accordingly
            if ($hasCustomTenders) {
                LogService::info(sprintf(
                    'Processing custom tenders for order #%d (pending total: %.2f %s)',
                    $order->get_id(),
                    $pendingCustomTendersTotal / 100,
                    $order->get_currency()
                ));
                $customTenderResult = $this->processCustomTenders($order, $cloverOrderUuid);
                if ($customTenderResult['result'] === 'fail') {
                    LogService::error(sprintf(
                        'Returning failure - custom tender processing failed for WooCommerce order (#%d)',
                        $order->get_id()
                    ));
                    return $customTenderResult;
                }
                // If custom tenders fully covered the order and were processed successfully, we're done
                if (isset($customTenderResult['skip_payment']) && $customTenderResult['skip_payment']) {
                    LogService::info('Custom tenders fully processed for order #' . $order->get_id());
                    LogService::debug('Returning success - custom tenders fully covered order');
                    return $customTenderResult;
                }
                $amountDue = $this->calculateAmountDue($order, $customTenderResult);
                LogService::info(sprintf(
                    'Remaining amount due after custom tenders for order #%d: %.2f %s',
                    $order->get_id(),
                    $amountDue / 100,
                    $order->get_currency()
                ));
            } else {
                $amountDue = WeeConnectPayHelper::safe_amount_to_cents_int($order->get_total());
                LogService::debug(sprintf(
                    'No custom tenders, full amount due for order #%d: %.2f %s',
                    $order->get_id(),
                    $amountDue / 100,
                    $order->get_currency()
                ));
            }

            // 7. Only charge the remaining amount if there is any
            if ($amountDue > 0) {
                if (!$cardDetails) {
                    LogService::error(sprintf(
                        'Missing card details for order #%d with remaining balance of %.2f %s',
                        $order->get_id(),
                        $amountDue / 100,
                        $order->get_currency()
                    ));
                    return $this->failureResponse();
                }
                LogService::info(sprintf(
                    'Processing card payment for order #%d (amount: %.2f %s)',
                    $order->get_id(),
                    $amountDue / 100,
                    $order->get_currency()
                ));
                $chargeResponse = $this->createCharge($order, $cloverOrderUuid, $cardDetails, $amountDue);
                LogService::debug(sprintf(
                    'Processing charge response for WooCommerce order (#%d)',
                    $order->get_id()
                ));
                return $this->handleChargeResponse($order, $chargeResponse, $cardDetails);
            }

            // If we get here, all custom tenders were processed successfully and no additional charge was needed
            LogService::info('Successfully completed processing for order #' . $order->get_id());
            LogService::debug('Returning success - all processing completed successfully');
            return $this->successResponse($order);
        } catch (WeeConnectPayException $e) {
            // Handle specific exception codes with user-friendly messages
            if ($e->getCode() === ExceptionCode::MISSING_SHIPPING_STATE
                || $e->getCode() === ExceptionCode::CUSTOMER_CREATION_EXCEPTION
                || $e->getCode() === ExceptionCode::STANDARDIZED_RESPONSE_EXCEPTION
                || $e->getCode() === ExceptionCode::INVALID_JSON_EXCEPTION
                || $e->getCode() === ExceptionCode::ORDER_LINE_ITEM_TOTAL_MISMATCH
                || $e->getCode() === ExceptionCode::UNSUPPORTED_ORDER_ITEM_TYPE
            ) {
                LogService::error(sprintf(
                    'Payment processing error shown to customer: %s',
                    $e->getMessage()
                ));
                wc_add_notice(esc_html($e->getMessage()), 'error');
            } else {
                LogService::error('An unhandled exception happened with the payment processor. Message: ' . $e->getMessage());
                wc_add_notice(__('Payment processing failed. Please try again.', 'weeconnectpay'), 'error');
            }
            return $this->failureResponse();
        } catch (Exception $e) {
            // Handle any other unexpected exceptions
            LogService::error('Unexpected exception during payment processing: ' . $e->getMessage());
            wc_add_notice(__('An unexpected error occurred during payment processing. Please try again.', 'weeconnectpay'), 'error');
            return $this->failureResponse();
        }
    }

    /**
     * Checks if the honeypot field is filled out.
     */
    protected function isHoneypotFilled(array $postData): bool
    {
        return !empty($postData['hp-feedback-required']);
    }

    /**
     * Handles a honeypot failure by adding an order note and cancelling the order.
     */
    protected function handleHoneypotFailure(WC_Order $order, array $postData): void
    {
        $sanitizedField = _sanitize_text_fields($postData['hp-feedback-required']);
        $orderNote = __('The hidden honeypot field was filled out. Likely a bot. Cancelling order. Field Value: ', 'weeconnectpay') . esc_html($sanitizedField);
        $order->add_order_note($orderNote);
        LogService::warning('Honeypot triggered. Order cancelled.');
        $order->update_status('cancelled');
    }

    /**
     * Handles Google reCAPTCHA by verifying the token, logging details, and cancelling
     * the order if the score is below the configured minimum.
     *
     * @param WC_Order $order
     * @param array    $postData
     * @return bool|array Returns true if reCAPTCHA is verified and processing should continue,
     *                    an array (with redirect response) if the order is cancelled due to a low score,
     *                    or false on fatal error.
     */
    protected function handleRecaptcha(WC_Order $order, array $postData)
    {
        if (empty($postData['recaptcha-token'])) {
            LogService::error('Missing reCAPTCHA token.');
            return false;
        }
        $recaptchaToken = _sanitize_text_fields($postData['recaptcha-token']);

        if (GoogleRecaptcha::tokenContainsErrorJson($recaptchaToken)) {
            $errorMessage = GoogleRecaptcha::extractErrorMessageFromToken($recaptchaToken);
            $order->add_order_note(
                __('<b>Google reCAPTCHA API.js (front-end/customer-facing) has encountered an error.</b> Google reCAPTCHA checks will be disabled for this transaction. Here is the error message: ', 'weeconnectpay') .
                esc_html($errorMessage)
            );
            // Continue processing despite the front-end error.
            return true;
        }

        $remoteIp = $order->get_customer_ip_address();
        $recaptchaVerifier = new RecaptchaVerifier();
        $recaptchaResponse = $recaptchaVerifier->verifyToken($recaptchaToken, $remoteIp);

        if (isset($recaptchaResponse['success']) && $recaptchaResponse['success'] === true) {
            if (isset($recaptchaResponse['score'])) {
                $score = $recaptchaResponse['score'];
                $minimumScore = $this->integrationSettings->getGoogleRecaptchaMinimumHumanScoreThresholdOrDefault();
                $googleRecaptchaText = __('Google reCAPTCHA: ', 'weeconnectpay');
                $googleRecaptchaScoreText = __('Google reCAPTCHA score: ', 'weeconnectpay');
                $minimumScoreText = __('Minimum human score setting: ', 'weeconnectpay');
                $isABot = false;
                if ($score >= $minimumScore) {
                    $recaptchaScoreOrderNote = '<b>' . $googleRecaptchaText . '</b>' .
                        __('According to your plugin settings for Google reCAPTCHA, the customer who paid for the order is likely a human being.', 'weeconnectpay') . '<br>';
                } else {
                    $recaptchaScoreOrderNote = '<b>' . $googleRecaptchaText . '</b>' .
                        __('According to your plugin settings for Google reCAPTCHA, the customer who paid for the order is <b>NOT</b> likely a human being. The order will be cancelled. If you are sure that this order was legitimate, please decrease the minimum human score threshold in the gateway settings.', 'weeconnectpay') . '<br>';
                    $isABot = true;
                }
                $recaptchaScoreOrderNote .= '<b>' . $googleRecaptchaScoreText . '</b>' . esc_html($score) . '<br>';
                $recaptchaScoreOrderNote .= '<b>' . $minimumScoreText . '</b>' . esc_html($minimumScore);
                $order->add_order_note($recaptchaScoreOrderNote);

                if ($isABot) {
                    LogService::warning('WeeConnectPay detected a potential bot!');
                    $order->update_status('cancelled');
                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_view_order_url(),
                    ];
                }
            } else {
                $unknownErrorOrderNote = __('The request to Google was successful but is missing the score. Full response: ', 'weeconnectpay') . json_encode($recaptchaResponse);
                $order->add_order_note($unknownErrorOrderNote);
            }
            return true;
        } else {
            LogService::error('The response from Google reCAPTCHA contains errors: ' . json_encode($recaptchaResponse));
            if (isset($recaptchaResponse['exception'])) {
                $order->add_order_note(
                    __('The request to Google reCAPTCHA triggered an exception. See exception message: ', 'weeconnectpay') .
                    esc_html($recaptchaResponse['exception'])
                );
            } elseif (isset($recaptchaResponse['error-codes'])) {
                $order->add_order_note(
                    __('The response from Google reCAPTCHA contains errors. See error codes: ', 'weeconnectpay') .
                    json_encode($recaptchaResponse['error-codes'])
                );
            } else {
                $order->add_order_note(
                    __('The response from Google reCAPTCHA contains unexpected errors. Full response: ', 'weeconnectpay') .
                    json_encode($recaptchaResponse)
                );
            }
            return false;
        }
    }

    /**
     * Extracts card token and details from the POST data.
     */
    protected function extractCardDetails(array $postData): array
    {
        return [
            'token' => !empty($postData['token']) ? _sanitize_text_fields($postData['token']) : '',
            'card_brand' => !empty($postData['card-brand']) ? _sanitize_text_fields($postData['card-brand']) : '',
            'card_last4' => !empty($postData['card-last4']) ? _sanitize_text_fields($postData['card-last4']) : '',
            'card_exp_month' => !empty($postData['card-exp-month']) ? _sanitize_text_fields($postData['card-exp-month']) : '',
            'card_exp_year' => !empty($postData['card-exp-year']) ? _sanitize_text_fields($postData['card-exp-year']) : '',
            'tokenized_zip' => !empty($postData['tokenized-zip']) ? WeeConnectPayUtilities::formatPostalCode(sanitize_text_field($postData['tokenized-zip'])) : '',
        ];
    }

    /**
     * Creates the customer creation payload for the WeeConnectPay API using only the available resources given to us
     *
     * @param WC_Order $order
     *
     * @return array
     * @since 2.4.1
     * @updated 3.12.6
     */
    protected function buildCustomerPayload(WC_Order $order): array
    {
        $customer = [];

        if ( $order->get_billing_first_name() ) {
            $customer['firstName'] = $order->get_billing_first_name();
        }

        if ( $order->get_billing_last_name() ) {
            $customer['lastName'] = $order->get_billing_last_name();
        }

        // Let customers follow your business on their own using the email sent to them by Clover -- Do not force this
        $customer['marketingAllowed'] = false;

        // Required: Address1, State, Country, City, Zip
        if ( $order->get_billing_address_1()
            && $order->get_billing_state()
            && $order->get_billing_country()
            && $order->get_billing_city()
            && $order->get_billing_postcode() ) {

            $customer['addresses'] = [
                [
                    "address1"    => $order->get_billing_address_1(),
                    "address2"    => $order->get_billing_address_2(),
                    "city"        => $order->get_billing_city(),
                    "country"     => $order->get_billing_country(),
                    "phoneNumber" => $order->get_billing_phone(),
                    "state"       => $order->get_billing_state(),
                    "zip"         => $order->get_billing_postcode()
                ]
            ];
        }

        // Required by Clover regardless and used for email DNS validation on our end
        $customer["emailAddresses"] = [
            [
                "emailAddress" => $order->get_billing_email(), // Required for the gateway regardless
                "primaryEmail" => true
            ]
        ];

        // Is there a phone number available?
        if ( $order->get_billing_phone() ) {
            $customer["phoneNumbers"] = [
                [
                    "phoneNumber" => $order->get_billing_phone()
                ]
            ];
        }

        $customer['metadata'] = [
            "note" => "Customer created by WeeConnectPay WooCommerce integration using the information provided by the customer during checkout.",
        ];

        if ( $order->get_billing_company() ) {
            $customer['metadata']['businessName'] = $order->get_billing_company();
        }

        return $customer;
    }


    /**
     * Creates a Clover customer using the provided payload.
     */
    protected function createCloverCustomer(array $customerPayload, WC_Order $order)
    {
        try {
            $request = new CreateCloverCustomerRequest();
            $response = $request->POST($customerPayload);
            $decoded = json_decode($response->getBody()->getContents(), true);
            if (isset($decoded['result'], $decoded['data']['id']) && $decoded['result'] === 'success') {
                LogService::info(sprintf(
                    'Successfully created/retrieved Clover customer (ID: %s) for WooCommerce order (#%d)',
                    $decoded['data']['id'],
                    $order->get_id()
                ));
                return $decoded['data']['id'];
            }
            throw new Exception('Customer creation failed.');
        } catch (Exception $e) {
            LogService::error("Failed to create customer for order #{$order->get_id()}: " . $e->getMessage());
            // Optionally add an order note.
            $order->add_order_note(__('Customer creation failed.', 'weeconnectpay'));
            return false;
        }
    }

    /**
     * Prepares a Clover order. If a Clover order UUID already exists in the order metadata, it returns that.
     * 
     * @throws WeeConnectPayException If there's a WeeConnectPay-specific error that should be handled by the top-level handler
     */
    protected function prepareCloverOrder(WC_Order $order, $customerId)
    {
        if (!$order->meta_exists('weeconnectpay_clover_order_uuid')) {
            try {
                $api = new WeeConnectPayAPI;
                $orderResponse = $api->prepare_order_for_payment($order, $customerId);
                if (isset($orderResponse['uuid'])) {
                    LogService::info(sprintf(
                        'Successfully created Clover order (ID: %s) for WooCommerce order (#%d)',
                        $orderResponse['uuid'],
                        $order->get_id()
                    ));
                    $order->add_meta_data('weeconnectpay_clover_order_uuid', $orderResponse['uuid']);
                    $order->save_meta_data();
                    /** @noinspection HtmlUnknownTarget */
                    $order->add_order_note(sprintf(
                        '<b>%s</b><br><b>%s</b> <a href="%s">%s</a>',
                        __('Clover order created.', 'weeconnectpay'),
                        __('Order ID: ', 'weeconnectpay'),
                        esc_url(CloverReceiptsHelper::getEnvReceiptUrl($orderResponse['uuid'], CloverReceiptsHelper::RECEIPT_TYPES['ORDER'])),
                        esc_html($orderResponse['uuid'])
                    ));
                    return $orderResponse['uuid'];
                }
            } catch (WeeConnectPayException $e) {
                // Let WeeConnectPayException bubble up to be handled by the top-level handler
                throw $e;
            } catch (Exception $e) {
                // Handle other exceptions with a generic error
                LogService::error(sprintf(
                    'Error preparing Clover order for WooCommerce order (#%d): %s',
                    $order->get_id(),
                    $e->getMessage()
                ));
                $order->add_order_note(__('Error preparing payment order with Clover.', 'weeconnectpay'));
                return false;
            }
        }
        $existingUuid = $order->get_meta('weeconnectpay_clover_order_uuid');
        LogService::info(sprintf(
            'Retrieved existing Clover order (ID: %s) for WooCommerce order (#%d)',
            $existingUuid,
            $order->get_id()
        ));
        return $existingUuid;
    }

    /**
     * Processes any custom tenders (e.g., gift cards) attached to the order.
     *
     * @return array Should contain at least a 'result' key and optionally 'clover_order_amount_due'.
     */
    protected function processCustomTenders(WC_Order $order, $cloverOrderUuid): array
    {
        try {
            $customTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order, 'pending');
            if (!empty($customTenders)) {
                return $this->process_custom_tenders($order, $cloverOrderUuid);
            }
        } catch (Exception $e) {
            LogService::error(sprintf(
                'Error processing custom tenders for WooCommerce order (#%d): %s',
                $order->get_id(),
                $e->getMessage()
            ));
            wc_add_notice(__('Payment processing failed. Please try again.', 'weeconnectpay'), 'error');
            return ['result' => 'fail', 'redirect' => ''];
        }
        return ['result' => 'success']; // No custom tenders found.
    }




    /**
     * Calculates the remaining amount due after processing custom tenders.
     */
    protected function calculateAmountDue(WC_Order $order, array $customTenderResult): int
    {
        if (isset($customTenderResult['clover_order_amount_due'])) {
            return $customTenderResult['clover_order_amount_due'];
        }
        return WeeConnectPayHelper::safe_amount_to_cents_int($order->get_total());
    }

    /**
     * Creates the charge for the remaining amount due.
     */
    protected function createCharge(WC_Order $order, $cloverOrderUuid, array $cardDetails, int $amountDue)
    {
        $ipAddress = $order->get_customer_ip_address();
        try {
            LogService::info(sprintf(
                'Initiating card charge of %.2f %s on Clover order (ID: %s) for WooCommerce order (#%d)',
                $amountDue / 100,
                $order->get_currency(),
                $cloverOrderUuid,
                $order->get_id()
            ));
            $chargeResponse = (new CreateCloverOrderChargeRequest())->POST($cloverOrderUuid, $cardDetails['token'], $ipAddress, $amountDue);
            return $chargeResponse;
        } catch (Exception $e) {
            LogService::error(sprintf(
                'Card charge creation failed for WooCommerce order (#%d): %s',
                $order->get_id(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Interprets and handles the charge response.
     */
    protected function handleChargeResponse(WC_Order $order, $chargeResponse, array $cardDetails): array
    {
        if (!$chargeResponse) {
            LogService::error(sprintf(
                'Charge response was empty for WooCommerce order (#%d)',
                $order->get_id()
            ));
            return $this->failureResponse();
        }

        LogService::debug("Card details: ".json_encode($cardDetails));

        $responseContent = $chargeResponse->getBody()->getContents();
        try {
            $decodedChargeResponse = WeeConnectPayUtilities::jsonValidate($responseContent);
        } catch (\WeeConnectPay\Exceptions\WeeConnectPayException $e) {
            LogService::error(sprintf(
                'Malformed charge response for WooCommerce order (#%d): %s',
                $order->get_id(),
                $e->getMessage()
            ));
            if ($e->getCode() === ExceptionCode::INVALID_JSON_EXCEPTION) {
                throw $e;
            }
            return $this->failureResponse();
        }

        if (isset($decodedChargeResponse->data->clover_payment_status)) {
            LogService::debug("decodedChargeResponse->data: ".json_encode($decodedChargeResponse->data));
            // Update metadata regardless of the result.
            $order->update_meta_data('weeconnectpay_card_brand', $cardDetails['card_brand']);

            if ('paid' === $decodedChargeResponse->data->clover_payment_status) {
                // Payment successful – record and complete.
                $amountDue = $this->calculateAmountDue($order, []);
                LogService::info(sprintf(
                    'Successfully created card charge (ID: %s) of %.2f %s on Clover order (ID: %s) for WooCommerce order (#%d)',
                    $decodedChargeResponse->data->clover_payment_id,
                    $amountDue / 100,
                    $order->get_currency(),
                    $decodedChargeResponse->data->clover_order_uuid ?? 'N/A',
                    $order->get_id()
                ));
                $paymentReceiptUrl = CloverReceiptsHelper::getEnvReceiptUrl(
                    $decodedChargeResponse->data->clover_payment_id,
                    CloverReceiptsHelper::RECEIPT_TYPES['CHARGE']
                );
                $order->add_meta_data('weeconnectpay_clover_payment_uuid', $decodedChargeResponse->data->clover_payment_id);
                /** @noinspection HtmlUnknownTarget */
                $order->add_order_note(
                    sprintf(
                        '<b>%s</b><br><b>%s</b> <a href="%s">%s</a>',
                        __('Clover payment successful!', 'weeconnectpay'),
                        __('Payment ID: ', 'weeconnectpay'),
                        esc_url($paymentReceiptUrl),
                        esc_html($decodedChargeResponse->data->clover_payment_id)
                    )
                );

                // Save credit card charge details
                try {
                    WeeConnectPayHelper::saveCreditCardCharge(
                        $order,
                        $this->calculateAmountDue($order, []),
                        $decodedChargeResponse->data->clover_charge_currency,
                        $cardDetails['card_brand'],
                        $cardDetails['card_last4'],
                        $cardDetails['card_exp_month'],
                        $cardDetails['card_exp_year'],
                        $cardDetails['tokenized_zip'],
                        $decodedChargeResponse->data->clover_payment_id
                    );
                } catch (Exception $e) {
                    LogService::error(sprintf(
                        'Error saving charge metadata for WooCommerce order (#%d): %s',
                        $order->get_id(),
                        $e->getMessage()
                    ));
                }

                $order->payment_complete();
                $this->addPostTokenizationNotes($order);
                return $this->successResponse($order);
            } elseif ('failed' === $decodedChargeResponse->data->clover_payment_status) {
                // Based on the payload example, extract error information properly
                $errorCode = isset($decodedChargeResponse->data->error->code) ? $decodedChargeResponse->data->error->code : '';
                $errorMessage = isset($decodedChargeResponse->data->error->message) ? $decodedChargeResponse->data->error->message : '';
                $chargeId = isset($decodedChargeResponse->data->error->charge) ? $decodedChargeResponse->data->error->charge : '';
                $declineCode = isset($decodedChargeResponse->data->error->declineCode) ? $decodedChargeResponse->data->error->declineCode : '';

                LogService::error(sprintf(
                    'Payment declined for WooCommerce order (#%d) - Code: %s, Message: %s, Decline Code: %s',
                    $order->get_id(),
                    $errorCode ?? 'Unknown',
                    $errorMessage ?? 'None',
                    $declineCode ?? 'None'
                ));

                // Add detailed order note for the failed payment
                $errorNote = '<b>' . __('Clover payment failed.', 'weeconnectpay') . '</b><br>';
                
                if (isset($decodedChargeResponse->data->clover_payment_id)) {
                    $errorNote .= '<b>' . __('Payment ID: ', 'weeconnectpay') . '</b>' . 
                        esc_html($decodedChargeResponse->data->clover_payment_id) . '<br>';
                }
                
                // Include charge ID if available
                if (!empty($chargeId)) {
                    $paymentReceiptUrl = CloverReceiptsHelper::getEnvReceiptUrl(
                        $chargeId,
                        CloverReceiptsHelper::RECEIPT_TYPES['CHARGE']
                    );
                    $errorNote .= '<b>' . __('Charge ID: ', 'weeconnectpay') . '</b>' . 
                        '<a href="' . esc_url($paymentReceiptUrl) . '">' . esc_html($chargeId) . '</a><br>';
                }
                
                // Include error code if available
                if (!empty($errorCode)) {
                    $errorNote .= '<b>' . __('Error code: ', 'weeconnectpay') . '</b>' . 
                        esc_html($errorCode) . '<br>';
                }
                
                // Include decline code if available
                if (!empty($declineCode)) {
                    $errorNote .= '<b>' . __('Decline code: ', 'weeconnectpay') . '</b>' . 
                        esc_html($declineCode) . '<br>';
                }
                
                // Include error message if available
                if (!empty($errorMessage)) {
                    $errorNote .= '<b>' . __('Clover error message: ', 'weeconnectpay') . '</b>' .
                        esc_html($errorMessage) . '<br>';
                }
                
                // Add card details if available
                if (isset($cardDetails) && is_array($cardDetails)) {
                    if (!empty($cardDetails['card_brand'])) {
                        $errorNote .= '<b>' . __('Card Type', 'weeconnectpay') . ': </b>' . 
                            esc_html($cardDetails['card_brand']) . '<br>';
                    }
                    
                    if (!empty($cardDetails['card_last4'])) {
                        $errorNote .= '<b>' . __('Last 4', 'weeconnectpay') . ': </b>' . 
                            esc_html($cardDetails['card_last4']) . '<br>';
                    }
                }
                
                $order->add_order_note($errorNote);
                
                $order->update_status('failed');
                return $this->redirectResponse($order);
            }
        }

        LogService::error(sprintf(
            'Malformed charge response for WooCommerce order (#%d): %s',
            $order->get_id(),
            $responseContent
        ));
        return $this->failureResponse();
    }

    /**
     * A generic success response.
     */
    protected function successResponse(WC_Order $order): array
    {
        $orderStatus = $order->get_status();
        LogService::debug(sprintf(
            'Generating redirect response for WooCommerce order (#%d) - Redirecting to order received page (Order Status: %s)',
            $order->get_id(),
            $orderStatus
        ));
        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_order_received_url(),
        ];
    }

    /**
     * A generic failure response.
     */
    protected function failureResponse(): array
    {
        LogService::debug('Payment processing halted - No payment was processed as a pre-processing condition was not met. This is a generic failure response used before payment processing begins.');
        return [
            'result' => 'fail',
            'redirect' => '',
        ];
    }

    /**
     * Helper: Returns a redirect response array based on the order.
     */
    protected function redirectResponse(WC_Order $order): array
    {
        $orderStatus = $order->get_status();
        LogService::debug(sprintf(
            'Generating redirect response for WooCommerce order (#%d) - Redirecting to order details page (Order Status: %s)',
            $order->get_id(),
            $orderStatus
        ));
        return [
            'result' => 'success',
            'redirect' => $order->get_view_order_url(),
        ];
    }

    // If you already have methods like process_custom_tenders() or customerPayload(),
    // you can either call them here or integrate their logic.

    /**
     * Processes a single custom tender by making an API request.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string $tenderId           The unique identifier of the custom tender.
     * @param string $cloverOrderUuid    The Clover Order UUID.
     * @param string $customTenderLabel  The label of the custom tender.
     * @param int $amount             The amount in cents.
     * @param string $ipAddress          The customer's IP address.
     *
     * @return ResponseInterface
     * @throws WeeConnectPayException
     */
    private function processCustomTender(WC_Order $order, string $tenderId, string $cloverOrderUuid, string $customTenderLabel, int $amount, string $ipAddress): ResponseInterface
    {
        try {
            LogService::info(sprintf(
                'Processing custom tender charge (Type: %s) of %.2f %s on Clover order (ID: %s) for WooCommerce order (#%d)',
                $customTenderLabel,
                $amount / 100,
                $order->get_currency(),
                $cloverOrderUuid,
                $order->get_id()
            ));
            return (new CreateCloverOrderCustomTenderChargeRequest())->POST($cloverOrderUuid, $customTenderLabel, $amount, $ipAddress);
        } catch (Exception $e) {
            throw new WeeConnectPayException('Failed to process custom tender: ' . $e->getMessage());
        }
    }

    /**
     * Handles the logic when a custom tender payment is successful.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string $tenderLabel
     * @param string $tenderId The unique identifier of the custom tender.
     * @param string $cloverChargeId The Clover charge ID.
     * @param string $cloverOrderUuid The Clover Order UUID.
     * @param int $amount The amount in cents.
     */
    private function handleCustomTenderPaymentSuccess(WC_Order $order, string $tenderLabel, string $tenderId, string $cloverChargeId, string $cloverOrderUuid, int $amount) {
        LogService::info(sprintf(
            'Successfully created custom tender charge (ID: %s, Type: %s) of %.2f %s on Clover order (ID: %s) for WooCommerce order (#%d)',
            $cloverChargeId,
            $tenderLabel,
            $amount / 100,
            $order->get_currency(),
            $cloverOrderUuid,
            $order->get_id()
        ));
        $paymentReceiptUrl = CloverReceiptsHelper::getEnvReceiptUrl(
            $cloverChargeId,
            CloverReceiptsHelper::RECEIPT_TYPES['CHARGE']
        );

        $successOrderNote  = '<b>' . __( 'Clover custom tender payment successful!', 'weeconnectpay' ) . '</b><br>';
        $successOrderNote .= '<b>' . __( 'Payment ID: ', 'weeconnectpay' ) . '</b>' . '<a href="' . esc_url( $paymentReceiptUrl ) . '">' . esc_html( $cloverChargeId ) . '</a><br>';

        if ( ! empty( $tenderLabel ) ) {
            $successOrderNote .= '<b>' . __( 'Custom Tender', 'weeconnectpay' ) . ': </b>' . esc_html( $tenderLabel ) . ' (ID: ' . esc_html( $tenderId ) . ')';
        }

        $order->add_order_note( $successOrderNote );

        WeeConnectPayCustomTenderHelper::executeCustomTenderCallback($order,$tenderId, 'chargeCreationCallback');
    }

    /**
     * Handles the logic when a custom tender payment fails.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param object $decodedChargeResponse The decoded API response.
     * @param string $tenderLabel
     * @param string $tenderId The unique identifier of the custom tender.
     */
    private function handleCustomTenderPaymentFailure(WC_Order $order, object $decodedChargeResponse, string $tenderLabel, string $tenderId ) {
        // Handle specific error: order already paid
        if ( isset( $decodedChargeResponse->error->code ) && 'order_already_paid' === $decodedChargeResponse->error->code ) {
            $message = isset( $decodedChargeResponse->error->message ) ? esc_html( $decodedChargeResponse->error->message ) : '';
            $alreadyPaidOrderNote  = '<b>' . __( 'Clover error message: ', 'weeconnectpay' ) . '</b><br>';
            $alreadyPaidOrderNote .= $message . '<br>';
            $alreadyPaidOrderNote .= __( 'Please check the order in the Clover dashboard for the full payment information.', 'weeconnectpay' );
            $order->add_order_note( $alreadyPaidOrderNote );

            // Mark payment as complete since it's already paid
            $order->payment_complete();
            return;
        }

        LogService::error( "decodedChargeResponse: " . json_encode( $decodedChargeResponse ) );

        if ( isset( $decodedChargeResponse->error->charge ) ) {
            $chargeErrorNote  = '<b>' . __( 'Clover custom tender payment failed.', 'weeconnectpay' ) . '</b><br>';
            $chargeErrorNote .= '<b>' . __( 'Payment ID: ', 'weeconnectpay' ) . '</b>' . esc_html( $decodedChargeResponse->error->charge ) . '<br>';

            if ( ! empty( $tenderLabel ) ) {
                $chargeErrorNote .= '<b>' . __( 'Custom Tender', 'weeconnectpay' ) . ': </b>' . esc_html( $tenderLabel );
            }

            if ( isset( $decodedChargeResponse->error->message ) ) {
                $chargeErrorNote .= '<b>' . __( 'Clover error message: ', 'weeconnectpay' ) . '</b>' . esc_html( $decodedChargeResponse->error->message ) . '<br>';
            }

            $order->add_order_note( $chargeErrorNote );

        } elseif ( isset( $decodedChargeResponse->message ) || isset( $decodedChargeResponse->error->message ) ) {
            $errorNote  = '<b>' . __( 'Clover custom tender payment failed.', 'weeconnectpay' ) . '</b><br>';

            if ( isset( $decodedChargeResponse->message ) ) {
                $errorNote .= '<b>' . __( 'Clover response message: ', 'weeconnectpay' ) . '</b>' . esc_html( $decodedChargeResponse->message ) . '<br>';
            }

            if ( isset( $decodedChargeResponse->error->code ) ) {
                $errorNote .= '<b>' . __( 'Clover error code: ', 'weeconnectpay' ) . '</b>' . esc_html( $decodedChargeResponse->error->code ) . '<br>';
            }

            if ( isset( $decodedChargeResponse->error->message ) ) {
                $errorNote .= '<b>' . __( 'Clover error message: ', 'weeconnectpay' ) . '</b>' . esc_html( $decodedChargeResponse->error->message ) . '<br>';
            }

            $order->add_order_note( $errorNote );

        } else {
            $otherNote  = '<b>' . __( 'Clover custom tender payment failed - Unhandled context, see response payload: ', 'weeconnectpay' ) . '</b>';
            $otherNote .= json_encode( $decodedChargeResponse );
            $order->add_order_note( $otherNote );
        }

        $order->update_status( 'failed' );

        // Notify the user about the failure.
        wc_add_notice( __( 'Payment failed. Please try again.', 'weeconnectpay' ), 'error' );
        WeeConnectPayCustomTenderHelper::executeCustomTenderCallback($order,$tenderId, 'chargeCreationCallback');
    }

    /**
     * Processes all pending custom tenders associated with the order.
     *
     * @param WC_Order $order           The WooCommerce order object.
     * @param string $cloverOrderUuid The Clover Order UUID.
     * @return array                    The result of processing custom tenders.
     *                                   'result' => 'success' or 'fail',
     *                                   'redirect' => URL or '',
     *                                   'skip_payment' => bool (true if no further payment needed)
     *                                   'clover_order_amount_due' => int|null
     */
    private function process_custom_tenders(WC_Order $order, string $cloverOrderUuid ): array
    {
        // Retrieve all custom tenders for the order using WeeConnectPayCustomTenderHelper
        try {
            $customTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order);
        } catch (InvalidArgumentException $e) {
            LogService::error('Error retrieving custom tenders: ' . $e->getMessage());
            wc_add_notice(__('Payment processing failed. Please try again.', 'weeconnectpay'), 'error');
            return [
                'result' => 'fail',
                'redirect' => '',
            ];
        }

        if (empty($customTenders)) {
            // No custom tenders to process; proceed normally.
            return [
                'result' => 'success',
                'redirect' => '',
                'skip_payment' => false,
            ];
        }

        $ipAddress = $order->get_customer_ip_address();

        // Flag to determine if any tenders were successfully processed
        $anyTendersProcessed = false;
        // Variable to determine how much we have left to pay. Is updated each custom tender processed successfully
        $cloverOrderAmountDue = null;

        foreach ($customTenders as $tender) {
            // Process only tenders with 'pending' status
            if (isset($tender['status']) && 'pending' !== $tender['status']) {
                continue;
            }

            LogService::info('Processing custom tender: ' . json_encode($tender));

            $customTenderLabel = $tender['provider'];
            $amount = $tender['amount'];
            $tenderId = $tender['id'];

            try {
                // Process each custom tender
                $chargeResponse = $this->processCustomTender($order, $tenderId, $cloverOrderUuid, $customTenderLabel, $amount, $ipAddress);

                // Decode and validate the response
                $chargeResponseContent = $chargeResponse->getBody()->getContents();
                $decodedChargeResponse = WeeConnectPayUtilities::jsonValidate($chargeResponseContent);

                LogService::info('Decoded custom tender charge response: ' . json_encode($decodedChargeResponse));

                // Extract necessary fields from the response
                $tenderStatus = isset($decodedChargeResponse->data->clover_payment_status) ? sanitize_text_field($decodedChargeResponse->data->clover_payment_status) : '';
                $cloverChargeId = isset($decodedChargeResponse->data->clover_payment_id) ? sanitize_text_field($decodedChargeResponse->data->clover_payment_id) : '';

                $cloverOrderAmountDue = $decodedChargeResponse->data->clover_order_amount_due ?? null;

                switch ($tenderStatus) {
                    case 'paid':
                    case 'created':
                        // Update the tender's status to 'success' and assign the Clover charge ID
                        WeeConnectPayCustomTenderHelper::updateCustomTenderToPaid($order, $tenderId, $cloverChargeId);
                        $this->handleCustomTenderPaymentSuccess($order, $customTenderLabel, $tenderId, $cloverChargeId, $cloverOrderUuid, $amount);
                        $anyTendersProcessed = true;
                        break;

                    case 'failed':
                        // Update the tender's status to 'failed'
                        WeeConnectPayCustomTenderHelper::updateCustomTenderToFailed($order, $tenderId);
                        $this->handleCustomTenderPaymentFailure($order, $decodedChargeResponse, $customTenderLabel, $tenderId);
                        break;

                    default:
                        LogService::error(sprintf(
                            'Invalid clover_payment_status (%s) for custom tender charge on WooCommerce order (#%d)',
                            $tenderStatus,
                            $order->get_id()
                        ));
                        wc_add_notice(__('Payment processing failed due to an unexpected error.', 'weeconnectpay'), 'error');
                        WeeConnectPayCustomTenderHelper::updateCustomTenderToFailed($order, $tenderId);
                        return [
                            'result' => 'fail',
                            'redirect' => '',
                            'clover_order_amount_due' => $cloverOrderAmountDue,
                        ];
                }

            } catch (Exception $e) {
                LogService::error(sprintf(
                    'Exception during custom tender processing for WooCommerce order (#%d): %s',
                    $order->get_id(),
                    $e->getMessage()
                ));
                WeeConnectPayCustomTenderHelper::updateCustomTenderToFailed($order, $tenderId);
                wc_add_notice(__('Payment processing failed. Please try again.', 'weeconnectpay'), 'error');
                return [
                    'result' => 'fail',
                    'redirect' => '',
                ];
            }
        }

        // If any tenders were processed successfully and they cover the entire order total
        // or if the order total is 0 and all pending tenders were processed
        if ($anyTendersProcessed && ($this->isCustomTendersCoverOrderTotal($order) || $order->get_total() <= 0)) {
            $this->addPostTokenizationNotes($order);
            $order->payment_complete();
            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url(),
                'skip_payment' => true,
            ];
        }

        // If some tenders were processed successfully but don't cover the total, proceed to actual payment processing
        return [
            'result' => 'success',
            'redirect' => '',
            'skip_payment' => false,
            'clover_order_amount_due' => $cloverOrderAmountDue,
        ];
    }

    /**
     * Determines if custom tenders cover the entire order total.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return bool        True if custom tenders cover the total, false otherwise.
     */
    private function isCustomTendersCoverOrderTotal(WC_Order $order): bool
    {
        LogService::debug('Checking if custom tenders cover order total for WooCommerce order (#' . $order->get_id() . ')');
        
        // Get current order total in cents
        $currentOrderTotalInCents = intval(round($order->get_total() * 100));
        LogService::debug('Current order total in cents: ' . $currentOrderTotalInCents);
        
        // Get all custom tenders
        $customTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order);
        LogService::debug('Custom tenders found: ' . json_encode($customTenders));

        // Calculate total of all tenders (both pending and successful)
        $totalTenderAmount = 0;
        foreach ($customTenders as $tender) {
            // Include both pending and successful tenders since we validated the total earlier
            if (isset($tender['amount'])) {
                $totalTenderAmount += $tender['amount'];
                LogService::debug(sprintf(
                    'Added tender amount %d, status: %s, new total: %d', 
                    $tender['amount'],
                    $tender['status'] ?? 'unknown',
                    $totalTenderAmount
                ));
            }
        }

        // Reconstruct original order total by adding current total and all tender amounts
        $reconstructedTotalInCents = $currentOrderTotalInCents + $totalTenderAmount;
        LogService::debug(sprintf(
            'Reconstructed total: %d cents (current total: %d + total tenders: %d)',
            $reconstructedTotalInCents,
            $currentOrderTotalInCents,
            $totalTenderAmount
        ));

        // Calculate successful tender total
        $successfulTenderTotal = 0;
        foreach ($customTenders as $tender) {
            if (isset($tender['status']) && $tender['status'] === 'success') {
                $successfulTenderTotal += $tender['amount'];
                LogService::debug(sprintf(
                    'Added successful tender amount %d, new successful total: %d',
                    $tender['amount'],
                    $successfulTenderTotal
                ));
            }
        }

        // Check if successful tenders cover the reconstructed total
        $tendersCoversTotal = $successfulTenderTotal >= $reconstructedTotalInCents;
        LogService::debug(sprintf(
            'Successful tenders (%d) %s reconstructed total (%d)',
            $successfulTenderTotal,
            $tendersCoversTotal ? '>=' : '<',
            $reconstructedTotalInCents
        ));

        return $tendersCoversTotal;
    }


    /**
     * Adds post-tokenization verification notes to the order.
     *
     * @param WC_Order $order The WooCommerce order object.
     */
    private function addPostTokenizationNotes(WC_Order $order ) {
        $isPostTokenizationVerificationActive = (new IntegrationSettings())->getPostTokenizationVerificationOrDefault();

        if ( $isPostTokenizationVerificationActive ) {

            // Retrieve all saved credit card charge metadata for a given order.
            $charges = WeeConnectPayHelper::getAllCreditCardCharges($order);

            if (!empty($charges)) {
                // Currently assuming only one charge exists per order.
                $chargeData = $charges[0];
                $chargePostalCode = $chargeData['card_postal_code'] ?? null;
            }

            $shippingPostalCode      = WeeConnectPayUtilities::formatPostalCode( $order->get_shipping_postcode() );
            $billingPostalCode       = WeeConnectPayUtilities::formatPostalCode( $order->get_billing_postcode() );

            if (empty($chargePostalCode)) {
                $warning_note = __('⚠️ Warning: An error has occurred: We could not detect the postal code used for the transaction.', 'weeconnectpay');
                $order->add_order_note($warning_note);
                return;
            }


            $tokenizationPostalCode  = WeeConnectPayUtilities::formatPostalCode( $chargePostalCode );

            if ( $shippingPostalCode && $shippingPostalCode !== $billingPostalCode ) {
                $info_note = sprintf(
                    __( 'ℹ️ Info: Please note that the shipping ZIP/Postal code "%s" and the billing ZIP/Postal code "%s" are different.', 'weeconnectpay' ),
                    $shippingPostalCode,
                    $billingPostalCode
                );
                $order->add_order_note( $info_note );
            }

            if ( $billingPostalCode !== $tokenizationPostalCode ) {
                $warning_note = sprintf(
                    __( '⚠️ Warning: Please note that the billing ZIP/Postal code "%s" and the payment card ZIP/Postal code "%s" are different. These should be the same.', 'weeconnectpay' ),
                    $billingPostalCode,
                    $tokenizationPostalCode
                );
                $order->add_order_note( $warning_note );
            }
        }
    }

}