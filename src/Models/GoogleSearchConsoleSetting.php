<?php

namespace RayzenAI\UrlManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GoogleSearchConsoleSetting extends Model
{
    protected $table = 'google_search_console_settings';
    
    protected $fillable = [
        'enabled',
        'site_url',
        'frontend_url', 
        'credentials',
        'service_account_email',
    ];
    
    protected $casts = [
        'enabled' => 'boolean',
        'credentials' => 'encrypted:array', // Laravel will automatically encrypt/decrypt
    ];
    
    /**
     * Get or create the settings record
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        
        if (!$settings) {
            $settings = self::create([
                'enabled' => false,
                'site_url' => config('app.url', url('/')),
            ]);
        }
        
        return $settings;
    }
    
    /**
     * Update settings from array
     */
    public function updateSettings(array $data): self
    {
        $this->update($data);
        return $this;
    }
    
    /**
     * Get decrypted credentials as JSON string for Google API
     */
    public function getCredentialsJsonAttribute(): ?string
    {
        if (!$this->credentials) {
            return null;
        }
        
        // credentials are already decrypted by the encrypted cast
        return json_encode($this->credentials);
    }
    
    /**
     * Set credentials from JSON string or array
     */
    public function setCredentialsFromJson($json): void
    {
        if (is_string($json)) {
            $this->credentials = json_decode($json, true);
        } else {
            $this->credentials = $json;
        }
        
        // Extract service account email
        if (isset($this->credentials['client_email'])) {
            $this->service_account_email = $this->credentials['client_email'];
        }
    }
}