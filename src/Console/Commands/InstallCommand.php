<?php

namespace Innonazarene\LaravelInertiaJsKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'inertia-js-kit:install {--force : Overwrite any existing files}';

    protected $description = 'Install session-based Inertia + React + Tailwind auth boilerplate (AuthController, requests, routes, and auth pages)';

    private Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->components->info('Installing Laravel Inertia JS Kit');

        $this->ensureInertiaMiddleware($force);
        $this->publishController($force);
        $this->publishRequests($force);
        $this->publishRoutes($force);
        $this->publishFrontend($force);
        $this->publishEntryPoints($force);
        $this->updatePackageJson();
        $this->updateViteConfig();

        $this->components->info('Laravel Inertia JS Kit installed successfully.');

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Run <fg=yellow>npm install</> to pull in React, Inertia, and Tailwind.');
        $this->line('  2. Run <fg=yellow>php artisan migrate</> to create the users table (if not already run).');
        $this->line('  3. Confirm <fg=yellow>app/Models/User.php</> exists and is a standard Authenticatable model.');
        $this->line('  4. Auth routes are registered under <fg=yellow>routes/auth.php</> and mounted on <fg=yellow>routes/web.php</>.');
        $this->line('  5. Run <fg=yellow>npm run dev</> (or <fg=yellow>npm run build</>) and visit <fg=yellow>/register</> or <fg=yellow>/login</>.');
        $this->line('  6. Make sure a <fg=yellow>dashboard</> named route exists — the controller redirects there after login/register.');

        return self::SUCCESS;
    }

    private function ensureInertiaMiddleware(bool $force): void
    {
        $middlewarePath = app_path('Http/Middleware/HandleInertiaRequests.php');

        if (! $this->files->exists($middlewarePath) || $force) {
            $this->publishStub(
                $this->stubPath('middleware/HandleInertiaRequests.stub'),
                $middlewarePath,
                $force,
                'Publishing HandleInertiaRequests middleware'
            );
        } else {
            $this->components->task('Sharing authenticated user via Inertia middleware', function () use ($middlewarePath) {
                $this->patchMiddlewareShare($middlewarePath);

                return true;
            });
        }

        $this->registerMiddleware();
    }

    private function patchMiddlewareShare(string $path): void
    {
        $contents = $this->files->get($path);

        if (Str::contains($contents, "'auth'")) {
            return;
        }

        // Modern skeleton: `return [\n    ...parent::share($request),\n];`
        if (preg_match('/return\s*\[\s*\.\.\.parent::share\(\$request\),/', $contents)) {
            $contents = preg_replace(
                '/(return\s*\[\s*\.\.\.parent::share\(\$request\),)/',
                "$1\n            'auth' => [\n                'user' => \$request->user(),\n            ],",
                $contents,
                1
            );
            $this->files->put($path, $contents);

            return;
        }

        // Older skeleton: `return array_merge(parent::share($request), [`
        if (preg_match('/return\s*array_merge\(\s*parent::share\(\$request\),\s*\[/', $contents)) {
            $contents = preg_replace(
                '/(return\s*array_merge\(\s*parent::share\(\$request\),\s*\[)/',
                "$1\n            'auth' => [\n                'user' => \$request->user(),\n            ],",
                $contents,
                1
            );
            $this->files->put($path, $contents);

            return;
        }

        $this->components->warn(
            "Couldn't automatically patch {$path} — add this to the array returned from share():\n".
            "        'auth' => ['user' => \$request->user()],"
        );
    }

    private function registerMiddleware(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if ($this->files->exists($bootstrapPath)) {
            $this->components->task('Registering HandleInertiaRequests in bootstrap/app.php', function () use ($bootstrapPath) {
                $contents = $this->files->get($bootstrapPath);

                if (Str::contains($contents, 'HandleInertiaRequests::class')) {
                    return true;
                }

                if (! Str::contains($contents, 'use App\\Http\\Middleware\\HandleInertiaRequests;')) {
                    $contents = preg_replace(
                        '/(<\?php\s*\R)/',
                        "$1\nuse App\\Http\\Middleware\\HandleInertiaRequests;",
                        $contents,
                        1
                    );
                }

                if (preg_match('/->withMiddleware\(function \(Middleware \$middleware\)[^{]*\{\s*\R/', $contents)) {
                    $contents = preg_replace(
                        '/(->withMiddleware\(function \(Middleware \$middleware\)[^{]*\{\s*\R)/',
                        "$1        \$middleware->web(append: [HandleInertiaRequests::class]);\n\n",
                        $contents,
                        1
                    );
                    $this->files->put($bootstrapPath, $contents);

                    return true;
                }

                $this->components->warn(
                    "Couldn't automatically register the middleware in {$bootstrapPath} — ".
                    'add `$middleware->web(append: [HandleInertiaRequests::class]);` inside withMiddleware().'
                );

                return true;
            });

            return;
        }

        $kernelPath = app_path('Http/Kernel.php');

        if (! $this->files->exists($kernelPath)) {
            return;
        }

        $this->components->task('Registering HandleInertiaRequests in app/Http/Kernel.php', function () use ($kernelPath) {
            $contents = $this->files->get($kernelPath);

            if (Str::contains($contents, 'HandleInertiaRequests::class')) {
                return true;
            }

            if (preg_match("/'web' => \[\s*\R/", $contents)) {
                $contents = preg_replace(
                    "/('web' => \[\s*\R)/",
                    "$1            \\App\\Http\\Middleware\\HandleInertiaRequests::class,\n",
                    $contents,
                    1
                );
                $this->files->put($kernelPath, $contents);

                return true;
            }

            $this->components->warn(
                "Couldn't automatically register the middleware in {$kernelPath} — ".
                "add `\\App\\Http\\Middleware\\HandleInertiaRequests::class` to the 'web' middleware group."
            );

            return true;
        });
    }

    private function publishController(bool $force): void
    {
        $this->publishStub(
            $this->stubPath('controllers/AuthController.stub'),
            app_path('Http/Controllers/AuthController.php'),
            $force,
            'Publishing AuthController'
        );
    }

    private function publishRequests(bool $force): void
    {
        $requests = [
            'LoginRequest',
            'RegisterRequest',
            'ChangePasswordRequest',
            'ForgotPasswordRequest',
            'ResetPasswordRequest',
        ];

        foreach ($requests as $request) {
            $this->publishStub(
                $this->stubPath("requests/{$request}.stub"),
                app_path("Http/Requests/Auth/{$request}.php"),
                $force,
                "Publishing {$request}"
            );
        }
    }

    private function publishRoutes(bool $force): void
    {
        $this->publishStub(
            $this->stubPath('routes/auth.stub'),
            base_path('routes/auth.php'),
            $force,
            'Publishing routes/auth.php'
        );

        $webRoutesPath = base_path('routes/web.php');
        $requireLine = "require __DIR__.'/auth.php';";

        if (! $this->files->exists($webRoutesPath)) {
            $this->components->task('Creating routes/web.php', function () use ($webRoutesPath) {
                $this->files->ensureDirectoryExists(dirname($webRoutesPath));
                $this->files->put($webRoutesPath, "<?php\n");

                return true;
            });
        }

        $this->components->task('Wiring routes/auth.php into routes/web.php', function () use ($webRoutesPath, $requireLine) {
            $contents = $this->files->get($webRoutesPath);

            if (Str::contains($contents, $requireLine)) {
                return true;
            }

            $this->files->append($webRoutesPath, "\n".$requireLine."\n");

            return true;
        });
    }

    private function publishFrontend(bool $force): void
    {
        $pages = [
            'Register', 'Login', 'ForgotPassword', 'ResetPassword', 'VerifyEmail', 'ChangePassword',
        ];

        foreach ($pages as $page) {
            $this->publishStub(
                $this->stubPath("react/Pages/Auth/{$page}.tsx.stub"),
                resource_path("js/Pages/Auth/{$page}.tsx"),
                $force,
                "Publishing Pages/Auth/{$page}.tsx"
            );
        }

        $layouts = ['GuestLayout', 'AuthenticatedLayout'];

        foreach ($layouts as $layout) {
            $this->publishStub(
                $this->stubPath("react/Layouts/{$layout}.tsx.stub"),
                resource_path("js/Layouts/{$layout}.tsx"),
                $force,
                "Publishing Layouts/{$layout}.tsx"
            );
        }

        $components = ['TextInput', 'InputLabel', 'InputError', 'PrimaryButton', 'TextLink'];

        foreach ($components as $component) {
            $this->publishStub(
                $this->stubPath("react/Components/{$component}.tsx.stub"),
                resource_path("js/Components/{$component}.tsx"),
                $force,
                "Publishing Components/{$component}.tsx"
            );
        }
    }

    private function publishEntryPoints(bool $force): void
    {
        $this->publishStub(
            $this->stubPath('react/app.tsx.stub'),
            resource_path('js/app.tsx'),
            $force,
            'Publishing resources/js/app.tsx'
        );

        $this->publishStub(
            $this->stubPath('react/app.css.stub'),
            resource_path('css/app.css'),
            $force,
            'Publishing resources/css/app.css'
        );

        $this->publishStub(
            $this->stubPath('views/app.blade.php.stub'),
            resource_path('views/app.blade.php'),
            $force,
            'Publishing resources/views/app.blade.php'
        );
    }

    private function updatePackageJson(): void
    {
        $packageJsonPath = base_path('package.json');

        if (! $this->files->exists($packageJsonPath)) {
            $this->components->warn('package.json not found — skipped wiring npm dependencies.');

            return;
        }

        $this->components->task('Adding npm dependencies to package.json', function () use ($packageJsonPath) {
            $package = json_decode($this->files->get($packageJsonPath), true) ?? [];

            // Ranges span multiple majors (react 18 vs 19, Inertia 1/2/3, vite plugin-react 4/5/6)
            // so npm's resolver can settle on whichever mutually-compatible set matches the
            // host's existing peers (e.g. Vite 8 requires @vitejs/plugin-react ^6, which in turn
            // pulls in react 19 via @inertiajs/react 3.x) instead of us hard-pinning one combo.
            $dependencies = [
                'react' => '^18.2.0 || ^19.0.0',
                'react-dom' => '^18.2.0 || ^19.0.0',
                '@inertiajs/react' => '^1.0.0 || ^2.0.0 || ^3.0.0',
            ];

            $devDependencies = [
                '@vitejs/plugin-react' => '^4.2.0 || ^5.0.0 || ^6.0.0',
                '@tailwindcss/vite' => '^4.0.0',
                'tailwindcss' => '^4.0.0',
                'typescript' => '^5.4.0',
                '@types/react' => '^18.2.0 || ^19.0.0',
                '@types/react-dom' => '^18.2.0 || ^19.0.0',
            ];

            $package['dependencies'] = array_merge($dependencies, $package['dependencies'] ?? []);
            $package['devDependencies'] = array_merge($devDependencies, $package['devDependencies'] ?? []);

            $this->files->put(
                $packageJsonPath,
                json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );

            return true;
        });
    }

    private function updateViteConfig(): void
    {
        foreach (['vite.config.ts', 'vite.config.js'] as $filename) {
            $path = base_path($filename);

            if ($this->files->exists($path)) {
                $this->patchViteConfig($path);

                return;
            }
        }

        $this->components->warn('No vite.config.(ts|js) found — skipped wiring the React and Tailwind Vite plugins.');
    }

    private function patchViteConfig(string $path): void
    {
        $this->components->task('Wiring React and Tailwind into '.basename($path), function () use ($path) {
            $contents = $this->files->get($path);
            $original = $contents;

            if (! Str::contains($contents, "from '@vitejs/plugin-react'")) {
                $contents = preg_replace(
                    "/(import laravel from 'laravel-vite-plugin';\s*\R)/",
                    "$1import react from '@vitejs/plugin-react';\nimport tailwindcss from '@tailwindcss/vite';\n",
                    $contents,
                    1
                );
            }

            if (! Str::contains($contents, 'react()')) {
                $contents = preg_replace(
                    '/(plugins:\s*\[\s*\R)/',
                    "$1        react(),\n        tailwindcss(),\n",
                    $contents,
                    1
                );
            }

            $contents = str_replace("'resources/js/app.js'", "'resources/js/app.tsx'", $contents);

            if ($contents === $original) {
                $this->components->warn(
                    "Couldn't automatically confirm {$path} was updated — verify the react() and tailwindcss() ".
                    'plugins are registered and the entry point points at resources/js/app.tsx.'
                );
            }

            $this->files->put($path, $contents);

            return true;
        });
    }

    private function publishStub(string $stub, string $destination, bool $force, string $label): void
    {
        $this->components->task($label, function () use ($stub, $destination, $force) {
            if ($this->files->exists($destination) && ! $force) {
                $this->components->warn(" {$destination} already exists — use --force to overwrite.");

                return true;
            }

            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copy($stub, $destination);

            return true;
        });
    }

    private function stubPath(string $path): string
    {
        return __DIR__.'/../../../stubs/'.$path;
    }
}
