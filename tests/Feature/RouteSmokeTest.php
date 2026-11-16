<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    /** @return array<string, \Illuminate\Routing\Route> */
    private function namedGetRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $name = $route->getName();
            if (! $name) {
                continue;
            }
            $out[$name] = $route;
        }

        return $out;
    }

    private function isGuestRoute($route): bool
    {
        $hasAuth = false;
        $hasGuest = false;
        foreach ($route->gatherMiddleware() as $mw) {
            if (! is_string($mw)) {
                continue;
            }
            if (str_starts_with($mw, 'auth')) {
                $hasAuth = true;
            }
            if (str_starts_with($mw, 'guest')) {
                $hasGuest = true;
            }
        }

        return $hasGuest || ! $hasAuth;
    }

    /** Resolve route params via the controller method's model type-hints; null if unresolvable. */
    private function resolveUrl($route): ?string
    {
        $action = $route->getAction('uses');
        if (! is_string($action) || ! str_contains($action, '@')) {
            return null;
        }
        [$class, $method] = explode('@', $action);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return 'MISSING_METHOD:'.$action;
        }

        $ref = new ReflectionMethod($class, $method);
        $typed = [];
        foreach ($ref->getParameters() as $p) {
            $t = $p->getType();
            if ($t instanceof ReflectionNamedType && ! $t->isBuiltin()) {
                $c = $t->getName();
                if (is_subclass_of($c, Model::class)) {
                    $typed[$p->getName()] = $c;
                }
            }
        }

        $bindingFields = method_exists($route, 'bindingFields') ? $route->bindingFields() : [];
        $params = [];
        foreach ($route->parameterNames() as $name) {
            if (! isset($typed[$name])) {
                if (str_contains($route->uri(), '{'.$name.'?}')) {
                    continue;
                }

                return null;
            }
            $model = $typed[$name]::query()->first();
            if (! $model) {
                return null;
            }
            $field = $bindingFields[$name] ?? $model->getRouteKeyName();
            $params[$name] = $model->{$field};
        }

        return route($route->getName(), $params);
    }

    private function probe(callable $selector, $actor, ?string $guard): array
    {
        $failures = [];
        foreach ($this->namedGetRoutes() as $name => $route) {
            if (! $selector($name, $route)) {
                continue;
            }
            $url = $this->resolveUrl($route);
            if ($url === null) {
                continue;
            }
            if (str_starts_with($url, 'MISSING_METHOD:')) {
                $failures[] = sprintf('%s [%s] -> %s', $name, $route->uri(), $url);

                continue;
            }
            try {
                $req = $actor ? $this->actingAs($actor, $guard) : $this;
                $status = $req->get($url)->getStatusCode();
            } catch (\Throwable $e) {
                $status = 'EX '.$e->getMessage();
            }
            if (is_int($status) && $status >= 500) {
                $failures[] = sprintf('%s [%s] -> %d', $name, $route->uri(), $status);
            } elseif (! is_int($status)) {
                $failures[] = sprintf('%s [%s] -> %s', $name, $route->uri(), $status);
            }
        }

        return $failures;
    }

    public function test_guest_get_routes_do_not_error(): void
    {
        $failures = $this->probe(
            fn ($n, $r) => $this->isGuestRoute($r),
            null,
            null
        );
        $this->assertSame([], $failures, "Guest GET routes failing:\n".implode("\n", $failures));
    }

    public function test_admin_get_routes_do_not_error(): void
    {
        $failures = $this->probe(
            fn ($n, $r) => str_starts_with($n, 'admin.'),
            $this->admin(),
            'web'
        );
        $this->assertSame([], $failures, "Admin GET routes failing:\n".implode("\n", $failures));
    }

    public function test_vendor_get_routes_do_not_error(): void
    {
        $vendor = Vendor::where('status', 'active')->where('is_approved', 1)->first()
            ?? Vendor::factory()->create(['status' => 'active', 'is_approved' => 1]);
        $failures = $this->probe(
            fn ($n, $r) => str_starts_with($n, 'vendor.') && ! $this->isGuestRoute($r),
            $vendor,
            'vendor'
        );
        $this->assertSame([], $failures, "Vendor GET routes failing:\n".implode("\n", $failures));
    }

    public function test_customer_get_routes_do_not_error(): void
    {
        $customer = Customer::first() ?? Customer::factory()->create();
        $failures = $this->probe(
            fn ($n, $r) => str_starts_with($n, 'customer.') && ! $this->isGuestRoute($r),
            $customer,
            'customer'
        );
        $this->assertSame([], $failures, "Customer GET routes failing:\n".implode("\n", $failures));
    }
}
