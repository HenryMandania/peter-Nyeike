<?php

namespace App\Services;

use Exception;
use Akika\LaravelMpesa\Mpesa;
use App\Models\Purchase;
use App\Models\Vendor; // Assuming your Vendor model is here
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $mpesa;

    public function __construct()
    {
        $this->mpesa = new Mpesa();

        Log::debug('M-Pesa Service Initialized', [
            'environment' => config('mpesa.env', 'unknown'),
            'shortcode'   => config('mpesa.shortcode'),
            'initiator'   => config('mpesa.initiator_name'),
        ]);
    }

    public function processVendorPayment(Purchase $purchase)
    {
        Log::info('Starting vendor payment process', [
            'purchase_id'    => $purchase->id,
            'vendor_id'      => $purchase->vendor_id,
            'vendor_type'    => $purchase->vendor_type,
            'amount'         => $purchase->total_amount,
        ]);

        try {
            // Fetch vendor details using vendor_id relationship
            $vendor = $purchase->vendor; // Assumes you have a belongsTo relationship: vendor() in Purchase model
            // If not, use: $vendor = Vendor::find($purchase->vendor_id);

            if (!$vendor) {
                throw new Exception("Vendor not found for purchase #{$purchase->id} (vendor_id: {$purchase->vendor_id})");
            }

            Log::debug('Vendor fetched', [
                'vendor_id'   => $vendor->id,
                'phone'       => $vendor->phone ?? 'N/A',
                'paybill'     => $vendor->paybill ?? 'N/A',
            ]);

            // Determine payment type (fallback to phone if vendor_type is null)
            $vendorType = $purchase->vendor_type ?? 'phone'; // You can make this stricter if needed

            $phone = $this->formatPhone($vendor->phone ?? '');
            $paybill = $vendor->paybill ?? null;

            $resultUrl   = 'https://rosalee-curious-earnest.ngrok-free.dev/api/mpesa/callback';
            $timeoutUrl  = 'https://rosalee-curious-earnest.ngrok-free.dev/api/mpesa/callback';

            Log::debug('Prepared payment parameters', [
                'vendor_type'   => $vendorType,
                'phone'         => $phone,
                'paybill'       => $paybill,
                'result_url'    => $resultUrl,
                'timeout_url'   => $timeoutUrl,
                'amount'        => $purchase->total_amount,
            ]);

            if ($vendorType === 'phone' && $phone) {
                Log::info('Initiating B2C payment', [
                    'phone'  => $phone,
                    'amount' => $purchase->total_amount,
                ]);

                $response = $this->mpesa->b2cTransaction(
                    null,                               // originatorConversationId
                    'BusinessPayment',                  // commandID
                    $phone,                             // recipient phone
                    $purchase->total_amount,            // amount
                    "Payment for Purchase #{$purchase->id}", // remarks
                    ''                                  // occasion
                );
            } else {
                if (!$paybill) {
                    throw new Exception("Vendor PayBill number is required for B2B payments (vendor_id: {$vendor->id})");
                }

                Log::info('Initiating B2B payment', [
                    'paybill' => $paybill,
                    'amount'  => $purchase->total_amount,
                ]);

                $response = $this->mpesa->b2bPaybill(
                    $paybill,                           // destShortcode (receiver)
                    $purchase->total_amount,            // amount
                    "Payment for Purchase #{$purchase->id}", // remarks
                    (string) $purchase->id,             // accountNumber
                    $resultUrl,                         // resultUrl (hardcoded for test)
                    $timeoutUrl,                        // timeoutUrl (hardcoded for test)
                    null                                // requester (optional)
                );
            }

            // Log raw response
            Log::debug('Raw response from M-Pesa package', [
                'status'     => $response->status(),
                'body_raw'   => $response->body(),
            ]);

            $result = $response->json() ?? [];

            Log::info('M-Pesa Vendor Payment Response', [
                'purchase_id' => $purchase->id,
                'type'        => $vendorType,
                'response'    => $result,
            ]);

            if (isset($result['ConversationID'])) {
                Log::info('ConversationID saved', ['conversation_id' => $result['ConversationID']]);
                $purchase->update(['conversation_id' => $result['ConversationID']]);
            } else {
                Log::warning('No ConversationID in response', ['response' => $result]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('M-Pesa Vendor Payment Failed', [
                'purchase_id' => $purchase->id,
                'vendor_id'   => $purchase->vendor_id ?? 'N/A',
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function formatPhone($phone)
    {
        if (!$phone) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }

        Log::debug('Formatted phone number', ['original' => $phone, 'formatted' => $phone]);

        return $phone;
    }
}