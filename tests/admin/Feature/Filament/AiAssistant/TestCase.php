<?php

namespace Lunar\Tests\Admin\Feature\Filament\AiAssistant;

use Lunar\Admin\Models\Staff;
use Lunar\Tests\Admin\Feature\Filament\TestCase as BaseTestCase;
use Minic\LaravelAiAssistant\Providers\AppServiceProvider as LaravelAiAssistantServiceProvider;
use Spatie\Permission\Models\Permission;

class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $packageRoot = dirname((new \ReflectionClass(LaravelAiAssistantServiceProvider::class))->getFileName(), 3);
        $this->loadMigrationsFrom($packageRoot.'/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LaravelAiAssistantServiceProvider::class,
        ];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function makeAiStaff(bool $admin = false, array $permissions = []): Staff
    {
        $staff = Staff::factory()->create(['admin' => $admin]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'staff',
            ]);

            $staff->givePermissionTo($permission);
        }

        return $staff;
    }
}
