<?php

namespace Innonazarene\LaravelInertiaJsKit\Tests\Feature;

use Illuminate\Support\Facades\File;
use Innonazarene\LaravelInertiaJsKit\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    /**
     * The shared testbench skeleton app doesn't ship routes/web.php,
     * app/Models/User.php, package.json, or vite.config.js by default,
     * unlike a real Laravel app — seed them here so the install command
     * exercises its real wiring logic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(app_path('Models'));
        File::put(app_path('Models/User.php'), <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Foundation\Auth\User as Authenticatable;
            use Illuminate\Notifications\Notifiable;

            class User extends Authenticatable
            {
                use Notifiable;

                protected $fillable = ['name', 'email', 'password'];
            }
            PHP);

        File::ensureDirectoryExists(base_path('routes'));
        File::put(base_path('routes/web.php'), "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        File::put(base_path('package.json'), json_encode([
            'private' => true,
            'scripts' => ['dev' => 'vite', 'build' => 'vite build'],
            'devDependencies' => ['vite' => '^5.0.0'],
        ], JSON_PRETTY_PRINT));

        File::put(base_path('vite.config.js'), <<<'JS'
            import { defineConfig } from 'vite';
            import laravel from 'laravel-vite-plugin';

            export default defineConfig({
                plugins: [
                    laravel({
                        input: ['resources/css/app.css', 'resources/js/app.js'],
                        refresh: true,
                    }),
                ],
            });
            JS);
    }

    protected function tearDown(): void
    {
        File::delete([
            app_path('Models/User.php'),
            app_path('Http/Controllers/AuthController.php'),
            app_path('Http/Middleware/HandleInertiaRequests.php'),
            app_path('Http/Requests/Auth/LoginRequest.php'),
            app_path('Http/Requests/Auth/RegisterRequest.php'),
            app_path('Http/Requests/Auth/ChangePasswordRequest.php'),
            app_path('Http/Requests/Auth/ForgotPasswordRequest.php'),
            app_path('Http/Requests/Auth/ResetPasswordRequest.php'),
            base_path('routes/web.php'),
            base_path('routes/auth.php'),
            base_path('package.json'),
            base_path('vite.config.js'),
            resource_path('js/app.tsx'),
            resource_path('css/app.css'),
            resource_path('views/app.blade.php'),
        ]);

        File::deleteDirectory(resource_path('js/Pages/Auth'));
        File::deleteDirectory(resource_path('js/Layouts'));
        File::deleteDirectory(resource_path('js/Components'));

        parent::tearDown();
    }

    public function test_it_publishes_the_auth_controller(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $this->assertFileExists(app_path('Http/Controllers/AuthController.php'));
    }

    public function test_it_publishes_all_auth_form_requests(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        foreach ([
            'LoginRequest',
            'RegisterRequest',
            'ChangePasswordRequest',
            'ForgotPasswordRequest',
            'ResetPasswordRequest',
        ] as $request) {
            $this->assertFileExists(app_path("Http/Requests/Auth/{$request}.php"));
        }
    }

    public function test_it_publishes_and_wires_auth_routes(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $this->assertFileExists(base_path('routes/auth.php'));

        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("require __DIR__.'/auth.php';", $webRoutes);
    }

    public function test_it_publishes_the_inertia_middleware_and_shares_the_auth_user(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString("'auth' =>", $middleware);
    }

    public function test_it_publishes_react_pages_layouts_and_components(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        foreach (['Register', 'Login', 'ForgotPassword', 'ResetPassword', 'VerifyEmail', 'ChangePassword'] as $page) {
            $this->assertFileExists(resource_path("js/Pages/Auth/{$page}.tsx"));
        }

        foreach (['GuestLayout', 'AuthenticatedLayout'] as $layout) {
            $this->assertFileExists(resource_path("js/Layouts/{$layout}.tsx"));
        }

        foreach (['TextInput', 'InputLabel', 'InputError', 'PrimaryButton', 'TextLink'] as $component) {
            $this->assertFileExists(resource_path("js/Components/{$component}.tsx"));
        }

        $this->assertFileExists(resource_path('js/app.tsx'));
        $this->assertFileExists(resource_path('css/app.css'));
        $this->assertFileExists(resource_path('views/app.blade.php'));
    }

    public function test_it_adds_frontend_dependencies_to_package_json(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $package = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey('react', $package['dependencies']);
        $this->assertArrayHasKey('@inertiajs/react', $package['dependencies']);
        $this->assertArrayHasKey('tailwindcss', $package['devDependencies']);
    }

    /**
     * Regression test: a stale version pinned by an earlier install run (or manually)
     * must stay untouched without --force, but --force must actually refresh it —
     * otherwise re-running after a package upgrade can never fix an outdated constraint
     * (e.g. a @vitejs/plugin-react range too old for the host's Vite version).
     */
    public function test_it_preserves_existing_npm_dependency_versions_without_force(): void
    {
        $packageJsonPath = base_path('package.json');
        $package = json_decode(file_get_contents($packageJsonPath), true);
        $package['devDependencies']['@vitejs/plugin-react'] = '^4.2.0';
        file_put_contents($packageJsonPath, json_encode($package, JSON_PRETTY_PRINT));

        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $package = json_decode(file_get_contents($packageJsonPath), true);

        $this->assertSame('^4.2.0', $package['devDependencies']['@vitejs/plugin-react']);
    }

    public function test_force_flag_refreshes_stale_npm_dependency_versions(): void
    {
        $packageJsonPath = base_path('package.json');
        $package = json_decode(file_get_contents($packageJsonPath), true);
        $package['devDependencies']['@vitejs/plugin-react'] = '^4.2.0';
        file_put_contents($packageJsonPath, json_encode($package, JSON_PRETTY_PRINT));

        $this->artisan('inertia-js-kit:install', ['--force' => true])->assertExitCode(0);

        $package = json_decode(file_get_contents($packageJsonPath), true);

        $this->assertNotSame('^4.2.0', $package['devDependencies']['@vitejs/plugin-react']);
        $this->assertStringContainsString('^6.0.0', $package['devDependencies']['@vitejs/plugin-react']);
    }

    public function test_it_wires_react_and_tailwind_into_vite_config(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('@vitejs/plugin-react', $vite);
        $this->assertStringContainsString('@tailwindcss/vite', $vite);
        $this->assertStringContainsString('resources/js/app.tsx', $vite);
    }

    public function test_it_does_not_overwrite_existing_files_without_force(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $controllerPath = app_path('Http/Controllers/AuthController.php');
        file_put_contents($controllerPath, "<?php\n// customized by developer\n");

        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $this->assertStringContainsString('customized by developer', file_get_contents($controllerPath));
    }

    public function test_force_flag_overwrites_existing_files(): void
    {
        $this->artisan('inertia-js-kit:install')->assertExitCode(0);

        $controllerPath = app_path('Http/Controllers/AuthController.php');
        file_put_contents($controllerPath, "<?php\n// customized by developer\n");

        $this->artisan('inertia-js-kit:install', ['--force' => true])->assertExitCode(0);

        $this->assertStringNotContainsString('customized by developer', file_get_contents($controllerPath));
    }
}
