<?php

namespace Modules\UserManagement\App\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class UserManagementServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(
            module_path('UserManagement', 'resources/views'),
            'usermanagement'
        );
    }
    /**
     * The name of the module.
     */
    protected string $name = 'UserManagement';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'usermanagement';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        \Modules\UserManagement\App\Providers\EventServiceProvider::class,
        \Modules\UserManagement\App\Providers\RouteServiceProvider::class
    ];

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
