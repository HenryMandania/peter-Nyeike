<?php

namespace App\Services;

use Safaricom\Mpesa\Mpesa;
use App\Models\Purchase;
use App\Models\MpesaConfig;
use Exception;

class MpesaService
{
    protected $mpesa;
    protected $config;

    public function __construct()
    {
        $this->mpesa = new Mpesa();
        // Fetch active configuration from DB
        $this->config = MpesaConfig::where('is_active', true)->first();
        
        if (!$this->config) {
            throw new Exception("Active M-Pesa configuration not found in database.");
        }
    }

    /**
     * Dynamically generates the security credential and retrieves config.
     */
    private function getCredentials()
    {
        if (empty($this->config->initiator_password)) {
            throw new Exception("Initiator password is not configured.");
        }

        return [
            'initiator' => $this->config->initiator_name,
            'security_credential' => $this->generateSecurityCredential($this->config->initiator_password),
            'shortcode' => $this->config->shortcode
        ];
    }

    /**
     * Encrypts the initiator password using the cert.cer file.
     */
    public function generateSecurityCredential($initiatorPassword)
    {
        $certPath = storage_path('app/cert.cer');
        
        if (!file_exists($certPath)) {
            throw new Exception("Certificate file (cert.cer) not found at: {$certPath}");
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($certPath));
        
        if (!$publicKey) {
            throw new Exception("Invalid certificate file provided.");
        }
        
        // Encrypt the password using PKCS1 padding
        openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        
        // Return base64 encoded string as required by M-Pesa API
        return base64_encode($encrypted);
    }

    public function processVendorPayment(Purchase $purchase)
    {
        $creds = $this->getCredentials();

        if ($purchase->vendor_type === 'phone') {
            return $this->mpesa->b2c(
                $creds['initiator'],
                $creds['security_credential'],
                "BusinessPayment",
                $purchase->total_amount,
                $creds['shortcode'],
                $purchase->vendor_phone, 
                "Payment for Purchase #" . $purchase->id,
                $this->config->timeout_url,
                $this->config->result_url,
                "" 
            );
        } else {
            return $this->mpesa->b2b(
                $creds['initiator'],
                $creds['security_credential'],
                $purchase->total_amount,
                $creds['shortcode'], 
                $purchase->vendor_paybill, 
                "Payment for Purchase #" . $purchase->id,
                $this->config->timeout_url,
                $this->config->result_url,
                $purchase->id, 
                "BusinessPayBill",
                "Shortcode",
                "Shortcode"
            );
        }
    }
}