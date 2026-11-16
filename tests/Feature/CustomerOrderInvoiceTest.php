<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerOrderInvoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function makeCustomer(string $label = 'a'): Customer
    {
        return Customer::create([
            'name' => 'Buyer '.$label,
            'email' => 'buyer-'.$label.'-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    protected function makeOrder(Customer $customer, string $status = 'processing'): Order
    {
        $order = Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 20000,
            'total_amount' => 20000,
            'delivery_fee' => 0,
            'status' => $status,
            'currency' => 'TZS',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
        ]);

        $product = Product::visible()->first();
        if ($product) {
            OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'price' => 10000,
            ]);
        }

        return $order;
    }

    public function test_customer_can_view_own_order_and_invoice(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Order #'.$order->id)
            ->assertSee('Order timeline');

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.invoice', $order->id))
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee('INV-'.$order->id);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = $this->makeCustomer('owner');
        $intruder = $this->makeCustomer('intruder');
        $order = $this->makeOrder($owner);

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertForbidden();

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.invoice', $order->id))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_order_pages(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->get(route('customer.orders.show', $order->id))->assertRedirect(route('customer.login'));
        $this->get(route('customer.orders.invoice', $order->id))->assertRedirect(route('customer.login'));
    }
}
