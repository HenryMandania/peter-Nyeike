<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use Safaricom\Mpesa\Mpesa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MpesaService
{
    protected $config;

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
    }

    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (strlen($phone) == 9) return '254' . $phone;
        return $phone;
    }

    public function processPayment($model, $customerPhone)
    {
        if (!$this->config) {
            throw new Exception("M-Pesa configuration missing.");
        }

        return DB::transaction(function () use ($model, $customerPhone) {
            // Calculate amount safely
            $amount = $model->total_amount - ($model->transaction_fee ?? 0);

            if ($amount <= 0) {
                throw new Exception("Invalid transaction amount: " . $amount);
            }

            $formattedPhone = $this->formatPhoneNumber($customerPhone);

            return $this->initiateStkPush($model, $formattedPhone, $amount);
        });
    }

    public function initiateStkPush($model, $phoneNumber, $amount)
    {
        try {
            putenv("MPESA_ENV=" . strtolower($this->config->env));
            putenv("MPESA_CONSUMER_KEY=" . $this->config->consumer_key);
            putenv("MPESA_CONSUMER_SECRET=" . $this->config->consumer_secret);
            putenv("MPESA_PASSKEY=" . $this->config->passkey);
            putenv("MPESA_SHORTCODE=" . $this->config->shortcode);

            $mpesa = new Mpesa();
            $callbackUrl = $this->config->callback_url;
            $reference = strtoupper(substr(class_basename($model), 0, 3)) . '_' . $model->id;

            $response = $mpesa->STKPushSimulation(
                $this->config->shortcode, $this->config->passkey, 'CustomerPayBillOnline',
                $amount, $phoneNumber, $this->config->shortcode, $phoneNumber,
                $callbackUrl, $reference, "Payment: " . class_basename($model), 'Payment'
            );

            $resData = json_decode($response, true);

            if (!isset($resData['CheckoutRequestID'])) {
                throw new Exception($resData['errorMessage'] ?? 'STK Push failed');
            }

            // Save to DB
            MpesaTransaction::create([
                'transactionable_id' => $model->id,
                'transactionable_type' => get_class($model),
                'checkout_request_id' => $resData['CheckoutRequestID'],
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'requested',
            ]);

            $model->update([
                'payment_status' => 'processing',
                'mpesa_checkout_id' => $resData['CheckoutRequestID']
            ]);

            return ['CheckoutRequestID' => $resData['CheckoutRequestID']];
        } catch (Exception $e) {
            Log::error("STK Push error: " . $e->getMessage());
            throw $e;
        }
    }
}