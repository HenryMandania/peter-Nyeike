<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Safaricom\Mpesa\Mpesa;
use Illuminate\Support\Facades\DB;
use Exception;

class MpesaService
{
    protected $config;
    protected $maxRetries = 3;

    public function __construct()
    {
        $activeConfig = MpesaConfig::where('is_active', true)->first();
        
        if (!$activeConfig) {
            \Log::error("MpesaService: No active configuration found in database.");
        } elseif (empty($activeConfig->paying_number)) {
            \Log::error("MpesaService: Active configuration found, but paying_number is empty. Config ID: " . $activeConfig->id);
        }
        
        $this->config = $activeConfig;
    }

    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) return '254' . $phone;
        return $phone;
    }

    public function processPayment($model)
{
    // Hardcoded config for testing purposes
    $this->config = (object) [
        'consumer_key'    => 'NBdWttxdv29OzT55G0eiQrnYpxdj0VAxbTtnGzurPTn4vKpS',
        'consumer_secret' => 'mvw7M7fSQIExV4COwCFx8eZQCaCiNe8YLeNi6m95LmDZAHgps9b6X2sIYSZTU40C',
        'shortcode'       => '174379',
        'passkey'         => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'env'             => 'sandbox',
        'callback_url'    => 'https://test1.goonersystem.com/api/mpesa/callback',
        'paying_number'   => '254728633090'
    ];

    // Proceed to initiate the push
    $amount = $model->total_amount ?? 1; // Fallback to 1 for testing
    $phone = $this->formatPhoneNumber($this->config->paying_number);

    return $this->initiateStkPush($model, $phone, $amount);
}
    public function initiateStkPush($model, $phoneNumber, $amount, $retryCount = 0)
    {
        try {
            putenv("MPESA_ENV=" . strtolower($this->config->env));
            putenv("MPESA_CONSUMER_KEY=" . $this->config->consumer_key);
            putenv("MPESA_CONSUMER_SECRET=" . $this->config->consumer_secret);
            putenv("MPESA_PASSKEY=" . $this->config->passkey);
            putenv("MPESA_SHORTCODE=" . $this->config->shortcode);

            $mpesa = new Mpesa();
            $callbackUrl = $this->config->callback_url ?: config('app.url') . '/api/mpesa/callback';
            $reference = strtoupper(substr(class_basename($model), 0, 3)) . '_' . $model->id;

            $response = $mpesa->STKPushSimulation(
                $this->config->shortcode, $this->config->passkey, 'CustomerPayBillOnline',
                $amount, $phoneNumber, $this->config->shortcode, $phoneNumber,
                $callbackUrl, $reference, "Payment: " . class_basename($model), 'Payment'
            );

            $resData = json_decode($response, true);
            if (!isset($resData['CheckoutRequestID'])) throw new Exception($resData['errorMessage'] ?? 'STK push failed');

            MpesaTransaction::create([
                'transactionable_id' => $model->id,
                'transactionable_type' => get_class($model),
                'type' => strtolower(class_basename($model)),
                'checkout_request_id' => $resData['CheckoutRequestID'],
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'requested',
            ]);

            // Unified update logic for all models
            $model->update([
                'mpesa_checkout_id' => $resData['CheckoutRequestID'],
                'mpesa_phone' => $phoneNumber,
                'payment_status' => 'processing',
                'mpesa_error_message' => null
            ]);

            return ['status' => true, 'CheckoutRequestID' => $resData['CheckoutRequestID']];

        } catch (Exception $e) {
            if ($retryCount < $this->maxRetries) {
                sleep(pow(2, $retryCount) * 5);
                return $this->initiateStkPush($model, $phoneNumber, $amount, $retryCount + 1);
            }
            
            $model->update(['mpesa_error_message' => $e->getMessage(), 'payment_status' => 'failed']);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}