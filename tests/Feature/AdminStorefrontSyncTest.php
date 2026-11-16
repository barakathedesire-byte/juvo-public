<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminStorefrontSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_editing_a_store_setting_reaches_the_storefront(): void
    {
        StoreSetting::updateOrCreate(['key' => 'phone_number'], ['value' => '+255700000001']);
        $this->get(route('support.index'))->assertOk()->assertSee('+255700000001');

        StoreSetting::updateOrCreate(['key' => 'phone_number'], ['value' => '+255700000002']);

        $this->get(route('support.index'))
            ->assertOk()
            ->assertSee('+255700000002')
            ->assertDontSee('+255700000001');
    }

    public function test_editing_an_exchange_rate_reaches_price_conversion(): void
    {
        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
        ]);
        $currency = Currency::updateOrCreate(['code' => 'TZS'], [
            'name' => 'Tanzanian Shilling',
            'symbol' => 'TSh',
            'exchange_rate' => 2500,
        ]);

        $this->assertSame(2500.0, convert_price(1, 'TZS'));

        $currency->exchange_rate = 2600;
        $currency->save();

        $this->assertSame(2600.0, convert_price(1, 'TZS'));
    }

    public function test_deleting_a_setting_clears_its_cached_value(): void
    {
        $setting = StoreSetting::updateOrCreate(['key' => 'store_address'], ['value' => 'Old address']);
        $this->assertSame('Old address', getWebConfig('store_address'));

        $setting->delete();

        $this->assertNull(getWebConfig('store_address'));
    }
}
