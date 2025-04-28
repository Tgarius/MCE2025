<?php

namespace WeeConnectPay\WordPress\Plugin\includes;

use Exception;
use InvalidArgumentException;
use WC_Order;
use WeeConnectPay\Api\Requests\RefundCloverChargeRequest;
use WeeConnectPay\Integrations\LogService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WeeConnectPayCustomTenderHelper
{
    /**
     * Add a custom tender to a WooCommerce order.
     *
     * @param WC_Order $order
     * @param string $customTenderLabel The Label of the custom tender in the Clover Merchant account
     * @param int $amountInCents The amount applied from the gift card (in cents).
     * @param string $callbackClass Fully qualified name of the callback class that implements CustomTenderInterface
     * @param string|null $uniqueId Optional Unique ID provided by the
     * @return array
     */
    public static function setCustomTender(WC_Order $order, string $customTenderLabel , int $amountInCents, string $callbackClass, ?string $uniqueId = null): array
    {
        // Validate input types
        if (trim($customTenderLabel) === '') {
            throw new InvalidArgumentException('Custom tender label must be a non-empty string.');
        }
        if ($amountInCents <= 0) {
            throw new InvalidArgumentException('Amount must be a positive integer in cents.');
        }

        // Validate the callback class
        // First check if the class exists
        if (!class_exists($callbackClass)) {
            throw new InvalidArgumentException("The callback class {$callbackClass} does not exist.");
        }

        // Get all interfaces implemented by the callback class
        $implementedInterfaces = class_implements($callbackClass, true);
        if (!$implementedInterfaces) {
            throw new InvalidArgumentException("The callback class {$callbackClass} does not implement any interfaces.");
        }

        // Define the possible interface names (both with and without namespace)
        $validInterfaceNames = [
            CustomTenderInterface::class,                                  // For when using namespaces
            'WeeConnectPay\WordPress\Plugin\includes\CustomTenderInterface', // Fully qualified name
            '\WeeConnectPay\WordPress\Plugin\includes\CustomTenderInterface' // Fully qualified name with leading slash
        ];

        // Check if any of the valid interface names are implemented
        $implementsInterface = false;
        foreach ($validInterfaceNames as $interfaceName) {
            if (in_array($interfaceName, $implementedInterfaces, true)) {
                $implementsInterface = true;
                break;
            }
        }

        if (!$implementsInterface) {
            LogService::error(sprintf(
                'Invalid callback class configuration - Class: %s does not implement CustomTenderInterface. Implemented interfaces: %s',
                $callbackClass,
                json_encode(array_values($implementedInterfaces))
            ));
            throw new InvalidArgumentException("The callback class {$callbackClass} must implement CustomTenderInterface.");
        }

        // Retrieve existing custom tenders
        $customTenders = $order->get_meta('weeconnectpay_custom_tenders', true);
        if (!is_array($customTenders)) {
            $customTenders = [];
        }

        // Ensure the unique ID is either generated, or that the uniqueId provided is truly unique (in this order)
        if (!$uniqueId) {
            $newId = wp_generate_uuid4();
        } else {
            $newId = $uniqueId;

            // Check if an entry with this ID already exists
            foreach ($customTenders as $tender) {
                if (isset($tender['id']) && $tender['id'] === $newId) {
                    LogService::warning('Custom tender with this ID already exists: ' . $newId);
                    throw new InvalidArgumentException('Custom tender with this ID ('.$newId.') already exists.');
                }
            }
        }

        $customTender = [
            'id'       => $newId,
            'amount'   => absint($amountInCents),
            'provider' => sanitize_text_field($customTenderLabel),
            'status'   => 'pending',                                // Initialize with 'pending' status
            'charge_id'=> '',                                       // To store Clover charge ID upon success
            'callback' => $callbackClass,
        ];

        // Add the new custom tender with a unique ID and status 'pending'
        $customTenders[] = $customTender;

        // Save back to order meta
        $order->update_meta_data('weeconnectpay_custom_tenders', $customTenders);
        $order->save();

        LogService::debug(sprintf(
            'Added new pending custom tender for order #%d - ID: %s, Provider: %s, Amount: %.2f, Callback: %s',
            $order->get_id(),
            $newId,
            $customTenderLabel,
            $amountInCents / 100,
            $callbackClass
        ));

        return $customTender;
    }

    /**
     * Get a single custom tender associated with a WooCommerce order by its ID.
     *
     * @param WC_Order $order
     * @param string $tenderId The unique identifier of the custom tender.
     * @return array|null The custom tender if found, or null if not found.
     */
    public static function getCustomTender(WC_Order $order, string $tenderId): ?array
    {
        // Validate input type
        if (empty($tenderId)) {
            throw new InvalidArgumentException('Tender ID must be a non-empty string.');
        }

        // Retrieve custom tenders from order meta
        $customTenders = $order->get_meta('weeconnectpay_custom_tenders', true);
        if (!is_array($customTenders)) {
            $customTenders = [];
        }

        // Find the tender with the matching id
        foreach ($customTenders as $customTender) {
            if (isset($customTender['id']) && $customTender['id'] === $tenderId) {
                return $customTender;
            }
        }

        // Return null if no matching tender found
        return null;
    }

    /**
     * Get all custom tenders associated with a WooCommerce order, optionally filtered by provider.
     *
     * @param WC_Order $order
     * @param string|null $status The status of the custom tender (optional). Pass null or omit to retrieve all custom tender regardless of status.
     * @param string|null $customTenderLabel The label of the custom tender (optional). Pass null or omit to retrieve all custom tenders regardless of tender label.
     * @return array An array of custom tenders, each containing 'name', 'amount', and 'provider'.
     */
    public static function getCustomTenders(WC_Order $order, ?string $status = null, ?string $customTenderLabel = null): array
    {
        // Retrieve custom tenders from order meta
        $customTenders = $order->get_meta('weeconnectpay_custom_tenders', true);
        if (!is_array($customTenders)) {
            $customTenders = [];
        }

        // Filter custom tenders by provider if specified
        if ($customTenderLabel !== null) {
            $customTenderLabel = sanitize_text_field($customTenderLabel);
            $customTenders = array_filter($customTenders, function ($customTender) use ($customTenderLabel) {
                return isset($customTender['provider']) && $customTender['provider'] === $customTenderLabel;
            });
        }

        // Filter custom tenders by status -- IE: pending
        if ($status !== null) {
            $customTenders = array_filter($customTenders, function ($customTender) use ($status) {
                return isset($customTender['status']) && $customTender['status'] === $status;
            });
        }

        return array_map(function ($customTender) {
            return [
                'id'        => $customTender['id'],
                'amount'    => $customTender['amount'],
                'provider'  => $customTender['provider'],
                'status'    => $customTender['status'],       // Default to 'pending' if not set
                'charge_id' => $customTender['charge_id'],           // Default to empty string if not set
            ];
        }, $customTenders);
    }

    public static function getCustomTendersPendingTotal(WC_Order $order, ?string $customTenderLabel = null): int
    {
        $customTenders = self::getCustomTenders($order, 'pending', $customTenderLabel);
        $total = 0;
        foreach ($customTenders as $customTender) {
            $total += $customTender['amount'];
        }
        return $total;
    }

    /**
     * Update a single custom tender for a WooCommerce order by its tender_id.
     *
     * @param WC_Order $order
     * @param string $tenderId The unique identifier of the custom tender.
     * @param array $data An associative array of data to update (e.g., 'status', 'charge_id').
     * @return void
     */
    public static function updateCustomTender(WC_Order $order, string $tenderId, array $data)
    {
        // Validate input types
        if (empty($tenderId)) {
            throw new InvalidArgumentException('Tender ID must be a non-empty string.');
        }
        if (empty($data)) {
            throw new InvalidArgumentException('Data for updating tender cannot be empty.');
        }

        // Retrieve existing custom tenders
        $customTenders = $order->get_meta('weeconnectpay_custom_tenders', true);
        if (!is_array($customTenders)) {
            $customTenders = [];
        }

        // Find the tender with the matching id
        $found = false;
        $oldStatus = null;
        foreach ($customTenders as &$tender) { // Using reference to modify
            if (isset($tender['id']) && $tender['id'] === $tenderId) {
                // Store old status for logging if status is being updated
                if (isset($data['status'])) {
                    $oldStatus = $tender['status'] ?? 'unknown';
                }
                
                // Update the tender with provided data
                foreach ($data as $key => $value) {
                    $tender[$key] = $value;
                }
                $found = true;
                break;
            }
        }
        unset($tender); // Break reference

        if (!$found) {
            throw new InvalidArgumentException('Custom tender with the provided ID not found.');
        }

        // Save back to order meta
        $order->update_meta_data('weeconnectpay_custom_tenders', $customTenders);
        $order->save();

        // Log status changes
        if (isset($data['status']) && $oldStatus !== $data['status']) {
            LogService::debug(sprintf(
                'Updated custom tender status for order #%d - ID: %s, Old Status: %s, New Status: %s',
                $order->get_id(),
                $tenderId,
                $oldStatus,
                $data['status']
            ));
        }

        // Log other changes
        $otherChanges = array_diff_key($data, ['status' => true]);
        if (!empty($otherChanges)) {
            LogService::debug(sprintf(
                'Updated custom tender data for order #%d - ID: %s, Updated Fields: %s',
                $order->get_id(),
                $tenderId,
                json_encode($otherChanges)
            ));
        }
    }

    /**
     * Update a single custom tender to 'success' status and assign a Clover charge ID.
     *
     * @param WC_Order $order
     * @param string $tenderId The unique identifier of the custom tender.
     * @param string $cloverChargeId The Clover charge ID to associate with the tender.
     * @return void
     */
    public static function updateCustomTenderToPaid(WC_Order $order, string $tenderId, string $cloverChargeId)
    {
        // Validate input types
        if (empty($cloverChargeId)) {
            throw new InvalidArgumentException('Clover Charge ID must be a non-empty string.');
        }

        // Get current tender data for logging
        $currentTender = self::getCustomTender($order, $tenderId);
        
        // Prepare data for updating
        $data = [
            'status'    => 'success',
            'charge_id' => sanitize_text_field($cloverChargeId),
        ];

        // Update the tender
        self::updateCustomTender($order, $tenderId, $data);

        LogService::info(sprintf(
            'Custom tender payment successful for order #%d - ID: %s, Provider: %s, Amount: %.2f, Clover Charge ID: %s',
            $order->get_id(),
            $tenderId,
            $currentTender['provider'] ?? 'unknown',
            ($currentTender['amount'] ?? 0) / 100,
            $cloverChargeId
        ));
    }

    /**
     * Update a single custom tender to 'failed' status.
     *
     * @param WC_Order $order
     * @param string $tenderId The unique identifier of the custom tender.
     * @return void
     */
    public static function updateCustomTenderToFailed(WC_Order $order, string $tenderId)
    {
        // Get current tender data for logging
        $currentTender = self::getCustomTender($order, $tenderId);
        
        // Prepare data for updating
        $data = [
            'status' => 'failed',
        ];

        // Update the tender
        self::updateCustomTender($order, $tenderId, $data);

        LogService::error(sprintf(
            'Custom tender payment failed for order #%d - ID: %s, Provider: %s, Amount: %.2f',
            $order->get_id(),
            $tenderId,
            $currentTender['provider'] ?? 'unknown',
            ($currentTender['amount'] ?? 0) / 100
        ));
    }

    public static function deletePendingCustomTender(WC_Order $order, string $tenderId)
    {
        // Retrieve existing custom tenders
        $customTenders = $order->get_meta('weeconnectpay_custom_tenders', true);
        if (!is_array($customTenders)) {
            $customTenders = [];
        }

        // Find the tender with the matching ID for validation and deletion
        $found = false;
        $deletedTender = null;
        foreach ($customTenders as $key => $tender) {
            if (isset($tender['id']) && $tender['id'] === $tenderId) {
                $found = true;
                $status = $tender['status'] ?? 'pending';
                $deletedTender = $tender;

                // Check tender status
                if ($status === 'refunded') {
                    throw new InvalidArgumentException("Can't delete a tender that has already been refunded.");
                } elseif ($status === 'success') {
                    throw new InvalidArgumentException("Can't delete a tender that has already processed a transaction, use the refund tender function.");
                }

                // Remove the tender
                unset($customTenders[$key]);
                break;
            }
        }

        if (!$found) {
            throw new InvalidArgumentException('Custom tender with the provided ID not found.');
        }

        // Save updated tenders back to order meta
        $order->update_meta_data('weeconnectpay_custom_tenders', array_values($customTenders));
        $order->save();

        LogService::debug(sprintf(
            'Deleted pending custom tender from order #%d - ID: %s, Provider: %s, Amount: %.2f',
            $order->get_id(),
            $tenderId,
            $deletedTender['provider'] ?? 'unknown',
            ($deletedTender['amount'] ?? 0) / 100
        ));
    }

    /**
     * Executes a custom tender callback based on the provided callback type.
     *
     * This function retrieves a custom tender associated with the order and invokes
     * the callback method of a class implementing the CustomTenderInterface.
     *
     * @param WC_Order $order The WooCommerce order instance.
     * @param string $tenderId The unique identifier of the custom tender.
     * @param string $callbackType The type of callback to execute ('chargeCreationCallback' or 'chargeRefundCallback').
     *
     * @return void
     */
    public static function executeCustomTenderCallback(WC_Order $order, string $tenderId, string $callbackType): void
    {
        // Execute callback logic
        try {
            $tender = WeeConnectPayCustomTenderHelper::getCustomTender($order, $tenderId);
            if (!$tender) {
                LogService::error(sprintf(
                    'Failed to execute %s - Custom tender not found for order #%d - ID: %s',
                    $callbackType,
                    $order->get_id(),
                    $tenderId
                ));
                return;
            }

            // Retrieve the callback class from the custom tender
            $callbackClass = $tender['callback'] ?? null;

            // Ensure a valid callback class is defined
            // Validate the callback class
            if (!class_exists($callbackClass) || !in_array(CustomTenderInterface::class, class_implements($callbackClass), true)) {
                LogService::error(sprintf(
                    'Invalid callback class for %s - Class: %s does not implement CustomTenderInterface (Order #%d, Tender ID: %s)',
                    $callbackType,
                    $callbackClass ?? 'null',
                    $order->get_id(),
                    $tenderId
                ));
                throw new InvalidArgumentException("The callback class {$callbackClass} must implement CustomTenderInterface.");
            }

            // Execute the appropriate callback
            if ($callbackType === 'chargeCreationCallback') {
                // Log charge creation attempt
                LogService::info(sprintf(
                    'Initiating charge creation callback for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Status: %s',
                    $order->get_id(),
                    $tenderId,
                    $tender['provider'] ?? 'unknown',
                    ($tender['amount'] ?? 0) / 100,
                    $tender['status'] ?? 'unknown'
                ));

                try {
                    $callbackResult = $callbackClass::chargeCreationCallback($tender);
                    
                    // Log successful charge creation
                    LogService::info(sprintf(
                        'Successfully executed charge creation callback for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f',
                        $order->get_id(),
                        $tenderId,
                        $tender['provider'] ?? 'unknown',
                        ($tender['amount'] ?? 0) / 100
                    ));
                } catch (Exception $e) {
                    // Log charge creation failure
                    LogService::error(sprintf(
                        'Charge creation callback failed for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Error: %s',
                        $order->get_id(),
                        $tenderId,
                        $tender['provider'] ?? 'unknown',
                        ($tender['amount'] ?? 0) / 100,
                        $e->getMessage()
                    ));
                    throw $e;
                }
            } elseif ($callbackType === 'chargeRefundCallback') {
                // Log refund callback attempt
                LogService::info(sprintf(
                    'Initiating refund callback for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Charge ID: %s',
                    $order->get_id(),
                    $tenderId,
                    $tender['provider'] ?? 'unknown',
                    ($tender['amount'] ?? 0) / 100,
                    $tender['charge_id'] ?? 'none'
                ));

                try {
                    $callbackResult = $callbackClass::chargeRefundCallback($tender);
                    
                    // Log successful refund callback
                    LogService::info(sprintf(
                        'Successfully executed refund callback for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f',
                        $order->get_id(),
                        $tenderId,
                        $tender['provider'] ?? 'unknown',
                        ($tender['amount'] ?? 0) / 100
                    ));
                } catch (Exception $e) {
                    // Log refund callback failure
                    LogService::error(sprintf(
                        'Refund callback failed for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Error: %s',
                        $order->get_id(),
                        $tenderId,
                        $tender['provider'] ?? 'unknown',
                        ($tender['amount'] ?? 0) / 100,
                        $e->getMessage()
                    ));
                    throw $e;
                }
            } else {
                LogService::error(sprintf(
                    'Invalid callback type requested for custom tender on order #%d - ID: %s, Type: %s',
                    $order->get_id(),
                    $tenderId,
                    $callbackType
                ));
                throw new InvalidArgumentException('Invalid callback type.');
            }

        } catch (Exception | InvalidArgumentException $exception) {
            LogService::error(sprintf(
                'Error executing %s for custom tender on order #%d - ID: %s, Provider: %s, Error: %s',
                $callbackType,
                $order->get_id(),
                $tenderId,
                $tender['provider'] ?? 'unknown',
                $exception->getMessage()
            ));
            // Silently fail if code in another plugin is not executed well
        }
    }

    /**
     * Refund a custom tender charge.
     *
     * This function retrieves the custom tender by its ID, validates that it
     * is in a refundable state (i.e. status is "success" and a charge_id exists),
     * then sends a refund request using RefundCloverChargeRequest. If the refund
     * is successful, the tender is updated to "refunded".
     *
     * @param WC_Order $order   The WooCommerce order instance.
     * @param string   $tenderId The unique identifier of the custom tender.
     *
     * @return array The refund response data
     *
     * @throws Exception If the tender is not found, not in a refundable state,
     *                   or if the refund request fails.
     */
    public static function refundCustomTender(WC_Order $order, string $tenderId): array
    {
        // Retrieve the custom tender.
        $tender = self::getCustomTender($order, $tenderId);
        if (!$tender) {
            throw new InvalidArgumentException("Custom tender with ID {$tenderId} not found.");
        }

        // Ensure the tender is in a state that can be refunded.
        if ($tender['status'] !== 'success') {
            throw new Exception("Custom tender is not in a refundable state (current status: {$tender['status']}).");
        }
        if (empty($tender['charge_id'])) {
            throw new Exception("Custom tender does not have an associated charge ID.");
        }

        LogService::info(sprintf(
            'Initiating refund for custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Charge ID: %s',
            $order->get_id(),
            $tenderId,
            $tender['provider'] ?? 'unknown',
            ($tender['amount'] ?? 0) / 100,
            $tender['charge_id']
        ));

        // Prepare the refund parameters.
        $refundAmount = $tender['amount'];
        $reason = 'requested_by_customer';
        $externalReferenceId = '';

        // Process the refund using the RefundCloverChargeRequest.
        try {
            $refundRequest = new RefundCloverChargeRequest();
            $response = $refundRequest->POST(
                $tender['charge_id'],
                $reason,
                $externalReferenceId,
                $refundAmount
            );

            /** @var array $responseBody */
            $responseBody = json_decode((string)$response->getBody(), true);

            // Check if the API response is valid and contains the necessary structure.
            if (isset($responseBody['result']) && $responseBody['result'] === 'success') {
                // Check if the 'data' object exists.
                if (isset($responseBody['data'])) {
                    $data = $responseBody['data'];

                    // Validate the fields from the 'data' object.
                    if (!isset($data['object']) || $data['object'] !== 'refund') {
                        throw new Exception("Refund validation failed: 'object' must be 'refund'. Response: " . json_encode($data));
                    }

                    if (!isset($data['amount']) || (int)$data['amount'] !== (int)$tender['amount']) {
                        throw new Exception("Refund validation failed: Refund amount does not match tender amount. Response: " . json_encode($data));
                    }

                    if (!isset($data['status']) || $data['status'] !== 'succeeded') {
                        throw new Exception("Refund validation failed: 'status' must be 'succeeded'. Response: " . json_encode($data));
                    }

                    if (!isset($data['id'])) {
                        throw new Exception("Refund validation failed: 'id' is missing in the response. Response: " . json_encode($data));
                    }

                    LogService::info(sprintf(
                        'Successfully refunded custom tender on order #%d - ID: %s, Provider: %s, Amount: %.2f, Refund ID: %s',
                        $order->get_id(),
                        $tenderId,
                        $tender['provider'] ?? 'unknown',
                        ($tender['amount'] ?? 0) / 100,
                        $data['id']
                    ));

                    // Add refund notes to the order
                    WeeConnectPayHelper::addRefundNotesToOrder($order, $responseBody);

                    // Update the custom tender's status to "refunded".
                    self::updateCustomTender($order, $tenderId, ['status' => 'refunded']);

                    // Execute the refund callback
                    self::executeCustomTenderCallback($order, $tenderId, 'chargeRefundCallback');

                    // Return the response body.
                    return $responseBody;
                } else {
                    throw new Exception("Refund request failed: 'data' object is missing in the response. Response: " . json_encode($responseBody));
                }
            } else {
                throw new Exception("Refund request failed: API call did not return success. Response: " . json_encode($responseBody));
            }
        } catch (Exception $e) {
            LogService::error(sprintf(
                'Failed to refund custom tender on order #%d - ID: %s, Provider: %s, Error: %s',
                $order->get_id(),
                $tenderId,
                $tender['provider'] ?? 'unknown',
                $e->getMessage()
            ));
            throw new Exception("Refunding custom tender failed: " . $e->getMessage());
        }
    }
}