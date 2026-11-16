<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViewOnlyAdminRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_view_only_user_opens_every_admin_get_route(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $views = Permission::where('name', 'like', '%.view')->pluck('name')->all();
        foreach ($views as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $user = User::factory()->create(['account_status' => 'active', 'portal_access' => true]);
        $user->givePermissionTo($views);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $failures = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $action = $route->getAction('uses');
            if (! is_string($action) || ! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }
            $ref = new ReflectionMethod($class, $method);
            $typed = [];
            foreach ($ref->getParameters() as $p) {
                $t = $p->getType();
                if ($t instanceof ReflectionNamedType && ! $t->isBuiltin() && is_subclass_of($t->getName(), Model::class)) {
                    $typed[$p->getName()] = $t->getName();
                }
            }
            $params = [];
            $skip = false;
            foreach ($route->parameterNames() as $param) {
                if (! isset($typed[$param])) {
                    $skip = true;
                    break;
                }
                $m = $typed[$param]::query()->first();
                if (! $m) {
                    $skip = true;
                    break;
                }
                $params[$param] = $m->getKey();
            }
            if ($skip) {
                continue;
            }
            try {
                $status = $this->actingAs($user, 'web')->get(route($name, $params))->getStatusCode();
            } catch (\Throwable $e) {
                $status = 'EX '.$e->getMessage();
            }
            if (! is_int($status) || $status >= 500) {
                $failures[] = "$name -> $status";
            }
        }

        $this->assertSame([], $failures, "View-only rendering failures:\n".implode("\n", $failures));
    }
}
