<?php

namespace WeeConnectPay\WordPress\Plugin\includes;


use Exception;
use WC_Order;
use WeeConnectPay\Api\Requests\RefundCloverChargeRequest;
use WeeConnectPay\Dependencies\GuzzleHttp\Exception\InvalidArgumentException;
use WeeConnectPay\Integrations\LogService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 1.0.0
 */
class WeeConnectPayHelper {

	/**
	 * Formats the description on the clover receipt. Also used in refunds.
	 * @param $name
	 * @param $qty
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function name_and_qty_as_clover_line_desc( $name, $qty ): string
    {
		return $name . ' x ' . $qty;
	}

	/**
	 * Safely formats a string amount value to an int representation of its value in cents.
	 * @param string $string_amount
	 * @since 1.3.3
	 *
	 * @return int
	 */
	public static function safe_amount_to_cents_int( string $string_amount ): int
    {
		return (int) number_format( $string_amount, 2, '', '' );
	}

    /**
     * Save a successful credit card transaction to the WooCommerce order meta.
     *
     * Instead of using a generated UUID, this method uses the provided Clover charge ID
     * as the key, ensuring that the record always exists for a successful transaction.
     *
     * @param WC_Order $order The WooCommerce order instance.
     * @param int $amountInCents The transaction amount in cents.
     * @param string $currency The currency of the charge (e.g., CAD, USD).
     * @param string $cardType The type of card (e.g., Visa, MasterCard).
     * @param string $last4Digits The last 4 digits of the card.
     * @param string|null $month The expiry month of the card. -- Google Pay does not provide us with this information
     * @param string|null $year The expiry year of the card. -- Google Pay does not provide us with this information
     * @param string $postalCode The postal code provided during the card tokenization that passed the AVS for this transaction
     * @param string $chargeId The unique Clover charge ID.
     *
     * @return array The saved charge data.
     *
     */
    public static function saveCreditCardCharge(WC_Order $order, int $amountInCents, string $currency, string $cardType, string $last4Digits, ?string $month, ?string $year, string $postalCode, string $chargeId): array
    {
        // Validate inputs.
        if (trim($cardType) === '') {
            throw new InvalidArgumentException('Card type must be a non-empty string.');
        }
        if (trim($currency) === '') {
            throw new InvalidArgumentException('Currency must be a non-empty string.');
        }
        if (trim($last4Digits) === '') {
            throw new InvalidArgumentException('Last 4 digits must be provided.');
        }
// Maybe in the future we will look at whether this is Google Pay, and provide specific ways of handling Google Pay or Apple Pay
//        if (trim($month) === '') {
//            throw new InvalidArgumentException('Month must be a non-empty string.');
//        }
//        if (trim($year) === '') {
//            throw new InvalidArgumentException('Year must be a non-empty string.');
//        }
        if (trim($postalCode) === '') {
            throw new InvalidArgumentException('Postal code must be a non-empty string.');
        }
        if (trim($chargeId) === '') {
            throw new InvalidArgumentException('Clover Charge ID must be provided.');
        }
        if ($amountInCents <= 0) {
            throw new InvalidArgumentException('Amount must be a positive integer in cents.');
        }

        // Retrieve existing charges from order meta.
        $charges = $order->get_meta('weeconnectpay_charges', true);
        if (!is_array($charges)) {
            $charges = [];
        }

        // Check if a record for this charge ID already exists.
        if (isset($charges[$chargeId])) {
            throw new InvalidArgumentException("A charge with ID {$chargeId} already exists.");
        }

        // Create the new charge record.
        $chargeData = [
            'charge_id'    => sanitize_text_field($chargeId),
            'amount'       => absint($amountInCents),
            'currency'     => sanitize_text_field($currency),
            'card_type'    => sanitize_text_field($cardType),
            'card_last4' => sanitize_text_field($last4Digits),
            'card_exp_month' => sanitize_text_field($month),
            'card_exp_year' => sanitize_text_field($year),
            'card_postal_code' => sanitize_text_field($postalCode),
            'status'       => 'success', // Immediately mark as success on transaction completion.
        ];

        // Save the new charge record keyed by its charge ID.
        $charges[$chargeId] = $chargeData;

        // Save back to order meta.
        $order->update_meta_data('weeconnectpay_charges', $charges);
        $order->save();

        return $chargeData;
    }

    /**
     * Retrieve a credit card charge record from the order meta using the Clover charge ID.
     *
     * @param WC_Order $order    The WooCommerce order instance.
     * @param string   $chargeId The unique Clover charge ID to search for.
     *
     * @return array|null The charge record if found, or null if not found.
     *
     * @throws InvalidArgumentException If the provided charge ID is empty.
     */
    public static function getCreditCardCharge(WC_Order $order, string $chargeId): ?array
    {
        if (trim($chargeId) === '') {
            throw new InvalidArgumentException('Clover Charge ID must be a non-empty string.');
        }

        $charges = $order->get_meta('weeconnectpay_charges', true);
        if (!is_array($charges)) {
            return null;
        }

        return $charges[$chargeId] ?? null;
    }

    /**
     * Process a Clover charge refund via Guzzle.
     *
     * @param string $cloverChargeUuid The Clover charge ID (UUID).
     * @param int    $refundAmount     The refund amount in cents.
     * @param string $reason           The reason for refund. Allowed values: 'requested_by_customer', 'duplicate', 'fraudulent'.
     *                                 Defaults to 'requested_by_customer'.
     * @param string $externalReferenceId Optional external reference ID (max 12 characters).
     *
     * @return array The refund data from Clover.
     *
     * @throws Exception if the refund fails.
     */
    public static function processCloverChargeRefund(
        string $cloverChargeUuid,
        int $refundAmount,
        string $reason = 'requested_by_customer',
        string $externalReferenceId = ''
    ): array {
        // Validate the external reference ID (if provided)
        if (!empty($externalReferenceId) && strlen($externalReferenceId) > 12) {
            throw new InvalidArgumentException('External Reference ID must not exceed 12 characters.');
        }

        // Validate the reason.
        $allowedReasons = ['requested_by_customer', 'duplicate', 'fraudulent'];
        if (!in_array($reason, $allowedReasons, true)) {
            throw new InvalidArgumentException('Invalid reason provided. Allowed values: ' . implode(', ', $allowedReasons));
        }

        try {
            $refundRequest = new RefundCloverChargeRequest();
            $response = $refundRequest->POST(
                $cloverChargeUuid,
                $reason,
                $externalReferenceId,
                $refundAmount // refund amount is provided in cents
            );
            $responseBody = json_decode((string)$response->getBody(), true);

            // Check if the refund was successful.
            if (isset($responseBody['result']) && $responseBody['result'] === 'success') {
                // Return the refund data.
                return $responseBody['data'];
            } else {
                throw new Exception('Refund request failed: ' . print_r($responseBody, true));
            }
        } catch (Exception $e) {
            throw new Exception('Guzzle refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Update a credit card charge record to 'refunded' status.
     *
     * @param \WC_Order $order
     * @param string    $chargeId
     *
     * @return void
     *
     * @throws Exception If the charge record is not found.
     */
    public static function updateCreditCardChargeToRefunded(\WC_Order $order, string $chargeId): void {
        $charges = $order->get_meta('weeconnectpay_charges', true);
        if (!is_array($charges)) {
            throw new Exception('No credit card charges found for this order.');
        }

        if (!isset($charges[$chargeId])) {
            throw new Exception('Credit card charge with the provided Clover Charge ID not found.');
        }

        $charges[$chargeId]['status'] = 'refunded';
        $order->update_meta_data('weeconnectpay_charges', $charges);
        $order->save();
    }

    /**
     * Retrieve all credit card charge metadata from the WooCommerce order.
     *
     * This function retrieves all credit card charge records stored in the order meta under
     * 'weeconnectpay_charges' and returns an array of metadata arrays. Each metadata array contains:
     * - 'charge_id'
     * - 'amount'
     * - 'currency'
     * - 'card_type'
     * - 'card_postal_code'
     * - 'status'
     *
     *
     * @param WC_Order $order The WooCommerce order instance.
     * @return array An array of credit card charge metadata.
     */
    public static function getAllCreditCardCharges(WC_Order $order): array
    {
        $charges = $order->get_meta('weeconnectpay_charges', true);
        if (!is_array($charges)) {
            return [];
        }
        $metadata = [];
        foreach ($charges as $charge) {
            $metadata[] = [
                'charge_id'         => $charge['charge_id'] ?? '',
                'amount'            => $charge['amount'] ?? 0,
                'currency'          => $charge['currency'] ?? '',
                'card_type'         => $charge['card_type'] ?? '',
                'card_postal_code'  => $charge['card_postal_code'] ?? '',
                'status'            => $charge['status'] ?? '',
            ];
        }
        return $metadata;
    }

    /**
     * Add refund notes to an order based on the refund response.
     * This method provides consistent refund note handling across different refund types (credit card, custom tender, etc.)
     *
     * @param WC_Order $order The WooCommerce order
     * @param array|object $refundResponse The decoded response from the refund request
     * @param string|null $reason Optional reason for the refund
     * @return void
     */
    public static function addRefundNotesToOrder(WC_Order $order, $refundResponse, ?string $reason = null): void
    {
        // Convert object to array if needed
        $response = is_object($refundResponse) ? json_decode(json_encode($refundResponse), true) : $refundResponse;

        // Handle standard charge refund response
        if (isset($response['result']) && $response['result'] === 'success' && isset($response['data'])) {
            $data = $response['data'];
            
            if (isset($data['id'], $data['amount'], $data['charge'], $data['status']) && $data['status'] === 'succeeded') {
                $formatted_refund_amount = number_format((float)$data['amount'] / 100, 2, '.', '');

                LogService::info(sprintf(
                    'Refund successful for order #%d - Amount: %.2f, Refund ID: %s, Charge ID: %s',
                    $order->get_id(),
                    $formatted_refund_amount,
                    $data['id'],
                    $data['charge']
                ));

                $chargeRefundNote = '<b>' . __('Refunded: ', 'weeconnectpay') . '</b>';
                $chargeRefundNote .= sprintf(
                    __('%1$s %2$s', 'weeconnectpay'),
                    $formatted_refund_amount,
                    $order->get_currency()
                ) . '<br>';
                $chargeRefundNote .= '<b>' . __('Refund ID: ', 'weeconnectpay') . '</b>' . $data['id'] . '<br>';
                
                // Look for custom tender by charge ID using helper
                $customTenders = WeeConnectPayCustomTenderHelper::getCustomTenders($order);
                if (!empty($customTenders)) {
                    foreach ($customTenders as $tender) {
                        if (isset($tender['charge_id']) && $tender['charge_id'] === $data['charge']) {
                            $chargeRefundNote .= '<b>' . __('Custom Tender: ', 'weeconnectpay') . '</b>' . 
                                esc_html($tender['provider']) . ' (ID: ' . esc_html($tender['id']) . ')<br>';
                            break;
                        }
                    }
                }
                
                $chargeRefundNote .= '<b>' . __('Refunded charge ID: ', 'weeconnectpay') . '</b>' . $data['charge'] . '<br>';

                if ($reason !== null && $reason !== '') {
                    $chargeRefundNote .= '<b>' . __('Reason: ', 'weeconnectpay') . '</b>' . esc_html($reason);
                }

                $order->add_order_note($chargeRefundNote);
                return;
            }

            // Handle return response (for items)
            if (isset($data['id'], $data['amount_returned'], $data['items'], $data['status']) && $data['status'] === 'returned') {
                $formatted_returned_amount = number_format((float)$data['amount_returned'] / 100, 2, '.', '');

                LogService::info(sprintf(
                    'Return successful for order #%d - Amount: %.2f, Return ID: %s',
                    $order->get_id(),
                    $formatted_returned_amount,
                    $data['id']
                ));

                $returnString = '<b>' . __('Refunded: ', 'weeconnectpay') . '</b>';
                $returnString .= sprintf(
                    __('%1$s %2$s', 'weeconnectpay'),
                    $formatted_returned_amount,
                    $order->get_currency()
                ) . '<br>';
                $returnString .= '<b>' . __('Refund ID: ', 'weeconnectpay') . '</b>' . $data['id'] . '<br>';

                if ($reason !== null && $reason !== '') {
                    $returnString .= '<b>' . __('Reason: ', 'weeconnectpay') . '</b>' . esc_html($reason) . '<br>';
                }

                foreach ($data['items'] as $item_returned) {
                    if (isset($item_returned['parent'], $item_returned['description'], $item_returned['amount'])) {
                        $clover_item_id = $item_returned['parent'];
                        $clover_item_returned_description = $item_returned['description'] ?? null;
                        $formatted_return_amount = number_format((float)$item_returned['amount'] / 100, 2, '.', '');

                        LogService::debug(sprintf(
                            'Returned item details - Order #%d, Item ID: %s, Description: %s, Amount: %.2f',
                            $order->get_id(),
                            $clover_item_id,
                            $clover_item_returned_description,
                            $formatted_return_amount
                        ));

                        $returnString .= '<b>' . __('Returned clover item ID: ', 'weeconnectpay') . '</b>';
                        $returnString .= sprintf(
                            __('%1$s(%2$s %3$s) - %4$s', 'weeconnectpay'),
                            $clover_item_id,
                            $formatted_return_amount,
                            $order->get_currency(),
                            $clover_item_returned_description
                        ) . '<br>';
                    }
                }
                $order->add_order_note($returnString);
                return;
            }
        }

        // Fallback for other response formats or errors
        $errorNote = '<b>' . __('⚠️ Refund Note: ', 'weeconnectpay') . '</b><br>';
        if (isset($response['error']['message'])) {
            $errorNote .= '<b>' . __('Error: ', 'weeconnectpay') . '</b>' . 
                esc_html($response['error']['message']) . '<br>';
        }
        if (isset($response['error']['code'])) {
            $errorNote .= '<b>' . __('Error Code: ', 'weeconnectpay') . '</b>' . 
                esc_html($response['error']['code']);
        }
        
        $order->add_order_note($errorNote);
        LogService::error(sprintf(
            'Unable to format standard refund note for order #%d - Response: %s',
            $order->get_id(),
            json_encode($response)
        ));
    }
}
