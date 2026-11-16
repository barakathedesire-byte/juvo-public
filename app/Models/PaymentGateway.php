<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    public function configs()
    {
        return $this->hasMany(PaymentGatewayConfig::class, 'gateway_id');
    }

    public function getConfigValue($key, $default = null, $environment = null)
    {
        $config = $this->configs
            ->where('key_name', $key)
            ->when($environment, fn ($c) => $c->where('environment', $environment))
            ->first();

        return $config ? $config->key_value : $default;
    }

    /**
     * The admin UI allows either the canonical key or a common alias. Read all
     * of them so a gateway configured by hand still connects.
     *
     * @param  array<int,string>  $keys
     */
    public function getConfigValueAny(array $keys, $default = null, $environment = null)
    {
        foreach ($keys as $key) {
            $value = $this->getConfigValue($key, null, $environment);

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return $default;
    }

    public function isSupported(): bool
    {
        return in_array($this->code, ['paypal', 'stripe', 'mobilemoney'], true);
    }

    public function preferredEnvironment(): string
    {
        foreach (['production', 'sandbox'] as $environment) {
            if ($this->isConfigured($environment)) {
                return $environment;
            }
        }

        return 'sandbox';
    }

    public function isConfigured(?string $environment = null): bool
    {
        if ($environment === null) {
            return $this->isConfigured('production') || $this->isConfigured('sandbox');
        }

        if ($this->code === 'paypal') {
            return $this->isReal($this->getConfigValue('client_id', null, $environment))
                && $this->isReal($this->getConfigValueAny(['secret', 'client_secret', 'secret_key'], null, $environment));
        }

        if ($this->code === 'stripe') {
            return $this->isReal($this->getConfigValueAny(['secret', 'secret_key', 'private_key'], null, $environment));
        }

        return false;
    }

    protected function isReal($value): bool
    {
        $value = trim((string) $value);

        return $value !== ''
            && ! preg_match('/^(your[-_ ]|changeme|replace|xxx|sk_test_your|pk_test_your)/i', $value);
    }
}
