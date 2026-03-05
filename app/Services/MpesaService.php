<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Safaricom\Mpesa\Mpesa;  
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $config;

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
        
        if ($this->config) {
            $env = strtolower($this->config->env); 
    
            // Inject EVERYTHING from the database into the environment memory
            putenv("MPESA_ENV={$env}");
            putenv("MPESA_CONSUMER_KEY={$this->config->consumer_key}");
            putenv("MPESA_CONSUMER_SECRET={$this->config->consumer_secret}");
            putenv("MPESA_PASSKEY={$this->config->passkey}");
            putenv("MPESA_SHORTCODE={$this->config->shortcode}");
            
            // Also keep the Laravel config for your own use
            config([
                'mpesa.env' => $env,
                'mpesa.shortcode' => $this->config->shortcode,
            ]);
        }
    }

    public function processPayment($model)
    {
        if (!$this->config) {
            return ['status' => false, 'message' => 'M-Pesa configuration inactive.'];
        }

        // 1. Determine Phone
        $phone = match (get_class($model)) {
            Purchase::class     => $model->vendor?->phone ?? $model->vendor_phone,
            FloatRequest::class => $model->user?->phone ?? $model->phone_number,
            Expense::class      => $model->phone_number,
            default             => null,
        };

        if (!$phone) return ['status' => false, 'message' => 'No valid phone found.'];

        // 2. Identify Amount (Net amount for Purchases)
        $type = strtolower(class_basename($model));
        $amount = ($model instanceof Purchase) 
            ? ($model->total_amount - ($model->transaction_fee ?? 0))
            : ($model->total_amount ?? $model->amount);

        $reference = strtoupper(substr($type, 0, 3)) . '_' . $model->id;
        $description = "Payment for $type #$model->id";
        
        return $this->initiateStkPush($model, $this->formatPhoneNumber($phone), $reference, $description, $amount, $type);
    }

    public function initiateStkPush($model, $phoneNumber, $accountRef, $description, $amount, $type)
    {
        try {
            $mpesa = new \Safaricom\Mpesa\Mpesa();
            
            $callbackUrl = $this->config->callback_url ?: config('app.url') . '/api/mpesa/callback';
            
            // This version of the package uses STKPushSimulation
            // Parameters: $BusinessShortCode, $LipaNaMpesaPasskey, $TransactionType, $Amount, $PartyA, $PartyB, $PhoneNumber, $CallBackURL, $AccountReference, $TransactionDesc, $Remark
            $response = $mpesa->STKPushSimulation(
                $this->config->shortcode,     // BusinessShortCode
                $this->config->passkey,       // LipaNaMpesaPasskey
                'CustomerPayBillOnline',      // TransactionType
                $amount,                      // Amount
                $phoneNumber,                 // PartyA
                $this->config->shortcode,     // PartyB
                $phoneNumber,                 // PhoneNumber
                $callbackUrl,                 // CallBackURL
                $accountRef,                  // AccountReference
                $description,                 // TransactionDesc
                'Payment'                     // Remark (Required by this package)
            );

            // This package returns a JSON string, so we decode it
            $resData = json_decode($response, true);
            $checkoutId = $resData['CheckoutRequestID'] ?? null;

            if ($checkoutId) {
                MpesaTransaction::create([
                    'transactionable_id'   => $model->id,
                    'transactionable_type' => get_class($model),
                    'type'                 => $type,
                    'checkout_request_id'  => $checkoutId,
                    'amount'               => $amount,
                    'phone_number'         => $phoneNumber,
                    'status'               => 'requested',
                ]);

                $model->update([
                    'mpesa_checkout_id' => $checkoutId,
                    'mpesa_phone'       => $phoneNumber,
                ]);

                return ['status' => true, 'message' => 'STK Push sent to ' . $phoneNumber];
            }

            Log::error("M-Pesa API Error: " . $response);
            return ['status' => false, 'message' => $resData['errorMessage'] ?? 'Safaricom Error. Check Logs.'];

        } catch (\Exception $e) {
            Log::error("M-Pesa Exception: " . $e->getMessage());
            return ['status' => false, 'message' => "M-Pesa Service Error."];
        }
    }

    protected function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone); 
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) return '254' . $phone;
        return $phone; 
    }
}