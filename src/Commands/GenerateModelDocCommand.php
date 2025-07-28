<?php

namespace TriQuang\LaravelModelDoc\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class GenerateModelDocCommand extends Command
{
    protected $signature = 'gen:model-doc
        {--sort=type : Sort by type, name, or db}
        {--model= : Fully qualified model class name to process only one}
        {--dry-run : Preview the output without modifying files}
        {--ns= : Extra namespaces to scan (comma-separated, like App\\Domain\\Models)}';

    protected $description = 'Generate PHPDoc for Models including @property and @property-read from relationships';

    protected $defaultLaravelRelations = [
        'tokens' => \Laravel\Sanctum\PersonalAccessToken::class,
        'notifications' => \Illuminate\Notifications\DatabaseNotification::class,
        'readNotifications' => \Illuminate\Notifications\DatabaseNotification::class,
        'unreadNotifications' => \Illuminate\Notifications\DatabaseNotification::class,
    ];

    public function handle()
    {
        $sortOption = $this->option('sort') ?? 'type';
        $modelClass = $this->option('model');

        if (! in_array($sortOption, ['type', 'name', 'db'])) {
            $this->warn("⚠️ Invalid sort option. Allowed: type, name, db. Defaulting to 'type'.");
            $sortOption = 'type';
        }

        if ($modelClass) {
            $modelClass = str_replace('/', '\\', $modelClass);

            if (! class_exists($modelClass)) {
                $this->error("⛔ Class {$modelClass} does not exist.");

                return;
            }

            $reflection = new ReflectionClass($modelClass);
            if (! $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                $this->error("⛔ Class {$modelClass} is not a subclass of Eloquent Model.");

                return;
            }

            $filePath = $reflection->getFileName();
            $namespace = $reflection->getNamespaceName() . '\\';
            $this->line("📄 Processing model: {$modelClass}");

            $this->processModelsInPath(dirname($filePath), $namespace, $sortOption, [$reflection->getShortName()]);

            return;
        }

        $modelPaths = [];

        if ($this->option('ns')) {
            $namespaces = array_map('trim', explode(',', $this->option('ns')));
            foreach ($namespaces as $ns) {
                $path = $this->namespaceToPath($ns);
                if ($path && is_dir($path)) {
                    $modelPaths[] = [
                        'path' => $path,
                        'namespace' => rtrim($ns, '\\') . '\\',
                    ];
                } else {
                    $this->warn("⚠️ Cannot resolve namespace '{$ns}' to a valid path.");
                }
            }
        } else {
            // Default namespaces
            $modelPaths[] = ['path' => app_path('Models'), 'namespace' => 'App\\Models\\'];

            $modulesPath = base_path('Modules');
            if (File::isDirectory($modulesPath)) {
                foreach (File::directories($modulesPath) as $module) {
                    $moduleName = basename($module);
                    $modelDir = $module . '/app/Models';
                    if (File::isDirectory($modelDir)) {
                        $modelPaths[] = [
                            'path' => $modelDir,
                            'namespace' => "Modules\\{$moduleName}\\Models\\",
                        ];
                    }
                }
            }
        }

        foreach ($modelPaths as $entry) {
            $this->line("📁 Scanning: {$entry['namespace']}");
            $this->processModelsInPath($entry['path'], $entry['namespace'], $sortOption);
        }

        $this->info('✅ Done generating model docs.');
    }

    protected function processModelsInPath(string $dir, string $namespace, string $sortOption, array $only = [])
    {
        $files = File::allFiles($dir);

        foreach ($files as $file) {
            $bareName = $file->getFilenameWithoutExtension();

            if (! empty($only) && ! in_array($bareName, $only)) {
                continue;
            }

            $filePath = $file->getRealPath();
            $className = $namespace . $bareName;

            if (! class_exists($className)) {
                try {
                    require_once $filePath;
                } catch (\Throwable $e) {
                    $this->warn("⚠️ Cannot load class: {$filePath}");

                    continue;
                }
            }

            if (! class_exists($className)) {
                $this->warn("⚠️ Class does not exist: {$className}");

                continue;
            }

            $reflection = new ReflectionClass($className);
            if (! $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            $model = new $className;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                $this->warn("⛔ Table '{$table}' not found for model {$className}");

                continue;
            }

            $columnTypes = $this->getColumnTypes($table);
            if (empty($columnTypes)) {
                continue;
            }

            $props = [];
            foreach ($columnTypes as $name => $sql) {
                $php = $this->shortType($this->mapToPhpType($sql));
                $props[] = [
                    'name' => $name,
                    'sql' => $sql,
                    'php' => $php,
                ];
            }

            // Sort
            switch ($sortOption) {
                case 'type':
                    $builtin = ['int', 'string', 'bool', 'float', 'mixed'];
                    usort($props, function ($a, $b) use ($builtin) {
                        $aBuilt = in_array($a['php'], $builtin);
                        $bBuilt = in_array($b['php'], $builtin);
                        if ($aBuilt !== $bBuilt) {
                            return $aBuilt ? -1 : 1;
                        }
                        $cmp = strcmp($a['php'], $b['php']);

                        return $cmp === 0 ? strcmp($a['name'], $b['name']) : $cmp;
                    });
                    break;
                case 'name':
                    usort($props, fn ($a, $b) => strcmp($a['name'], $b['name']));
                    break;
                case 'db':
                    $builtin = ['int', 'string', 'bool', 'float', 'mixed'];
                    usort($props, function ($a, $b) use ($builtin) {
                        $aBuilt = in_array($a['php'], $builtin);
                        $bBuilt = in_array($b['php'], $builtin);

                        if ($aBuilt !== $bBuilt) {
                            return $aBuilt ? -1 : 1;
                        }

                        $cmpPhp = strcmp($a['php'], $b['php']);
                        if ($cmpPhp !== 0) {
                            return $cmpPhp;
                        }

                        $cmpSql = strcmp($a['sql'], $b['sql']);
                        if ($cmpSql !== 0) {
                            return $cmpSql;
                        }

                        return strcmp($a['name'], $b['name']);
                    });
                    break;
            }

            $maxSql = max(array_map(fn ($p) => strlen($p['sql']), $props));
            $maxPhp = max(array_map(fn ($p) => strlen($p['php']), $props));

            $doc = [];
            $doc[] = '/**';
            $doc[] = " * @table {$table}";
            foreach ($props as $p) {
                $doc[] = sprintf(" * @property  %-{$maxSql}s  %-{$maxPhp}s  \$%s", $p['sql'], $p['php'], $p['name']);
            }

            // Add relationships
            $relations = $this->detectRelations($model);
            foreach ($relations as $r) {
                $doc[] = " * @property-read {$r['type']} \${$r['name']}";
            }

            $doc[] = " */\n";
            $docBlock = implode("\n", $doc);

            if ($this->option('dry-run')) {
                $this->line("📄 {$bareName} (preview):\n{$docBlock}");
            } else {
                $this->writeDocBlock($filePath, $docBlock);
                $this->info("✨ Updated: {$bareName}");
            }
        }
    }

    protected function detectRelations($model): array
    {
        $relations = [];
        foreach (get_class_methods($model) as $method) {
            if (array_key_exists($method, $this->defaultLaravelRelations)) {
                continue;
            }

            try {
                $ref = new ReflectionMethod($model, $method);
                if (
                    $ref->isPublic()
                    && ! $ref->isStatic()
                    && ! $ref->isAbstract()
                    && $ref->getNumberOfParameters() === 0
                    && $ref->getDeclaringClass()->getName() === get_class($model)
                ) {
                    $return = $model->$method();
                    if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $related = $this->shortClass(get_class($return->getRelated()));
                        $type = Str::startsWith(get_class($return), 'Illuminate\Database\Eloquent\Relations\HasMany')
                            ? "Collection|{$related}[]"
                            : $related;
                        $relations[] = [
                            'name' => $method,
                            'type' => $type,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $relations;
    }

    protected function writeDocBlock(string $filePath, string $docBlock): void
    {
        $content = File::get($filePath);
        $content = preg_replace('/^<\?php\s+\/\*\*(.|\s)*?\*\/\s+/s', '<?php' . PHP_EOL, $content);
        $content = preg_replace('/^<\?php\s+/s', "<?php\n{$docBlock}\n", $content, 1);
        File::put($filePath, $content);
    }

    protected function getColumnTypes(string $table): array
    {
        $driver = DB::getDriverName();
        $columns = [];

        try {
            switch ($driver) {
                case 'mysql':
                    $results = DB::select('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_name = ? AND table_schema = ?', [$table, DB::getDatabaseName()]);
                    foreach ($results as $r) {
                        $columns[$r->COLUMN_NAME] = strtolower($r->DATA_TYPE);
                    }
                    break;
                case 'pgsql':
                    $results = DB::select('SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? AND table_schema = current_schema()', [$table]);
                    foreach ($results as $r) {
                        $columns[$r->column_name] = strtolower($r->data_type);
                    }
                    break;
                case 'sqlite':
                    $results = DB::select("PRAGMA table_info('$table')");
                    foreach ($results as $r) {
                        $columns[$r->name] = strtolower($r->type);
                    }
                    break;
                case 'sqlsrv':
                    $results = DB::select('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_name = ?', [$table]);
                    foreach ($results as $r) {
                        $columns[$r->COLUMN_NAME] = strtolower($r->DATA_TYPE);
                    }
                    break;
                case 'oci':
                    $results = DB::select('SELECT COLUMN_NAME, DATA_TYPE FROM USER_TAB_COLUMNS WHERE TABLE_NAME = ?', [strtoupper($table)]);
                    foreach ($results as $r) {
                        $columns[$r->COLUMN_NAME] = strtolower($r->DATA_TYPE);
                    }
                    break;
                default:
                    $this->warn("⚠️ Unsupported driver: {$driver}. Using Schema fallback.");
                    $colNames = Schema::getColumnListing($table);
                    foreach ($colNames as $col) {
                        $columns[$col] = Schema::getColumnType($table, $col);
                    }
            }
        } catch (\Throwable $e) {
            $this->error("❌ Failed to get columns for table '{$table}': {$e->getMessage()}");
        }

        return $columns;
    }

    protected function mapToPhpType(string $sql): string
    {
        return match (true) {
            str_contains($sql, 'int') => 'int',
            str_contains($sql, 'bool') => 'bool',
            str_contains($sql, 'decimal'),
            str_contains($sql, 'double'),
            str_contains($sql, 'float'),
            str_contains($sql, 'real') => 'float',
            str_contains($sql, 'char'),
            str_contains($sql, 'text'),
            str_contains($sql, 'enum'),
            str_contains($sql, 'set') => 'string',
            str_contains($sql, 'date'),
            str_contains($sql, 'time'),
            str_contains($sql, 'year'),
            str_contains($sql, 'timestamp') => '\Carbon\Carbon',
            default => 'mixed',
        };
    }

    protected function shortType(string $type): string
    {
        return $this->shortClass($type);
    }

    protected function shortClass(string $fqcn): string
    {
        return class_exists($fqcn) ? (new \ReflectionClass($fqcn))->getShortName() : $fqcn;
    }

    protected function namespaceToPath(string $namespace): ?string
    {
        $paths = [];

        $paths[] = base_path('composer.json');

        $modulesPath = base_path('Modules');
        if (File::isDirectory($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $moduleComposer = $moduleDir . '/composer.json';
                if (File::exists($moduleComposer)) {
                    $paths[] = $moduleComposer;
                }
            }
        }

        foreach ($paths as $jsonFile) {
            $composer = json_decode(file_get_contents($jsonFile), true);
            $psr4 = $composer['autoload']['psr-4'] ?? [];

            foreach ($psr4 as $baseNs => $basePath) {
                $baseNs = rtrim($baseNs, '\\');
                if (str_starts_with($namespace, $baseNs)) {
                    $relative = str_replace('\\', '/', substr($namespace, strlen($baseNs)));
                    $dir = dirname($jsonFile); // root hoặc module folder
                    $fullPath = realpath($dir . '/' . trim($basePath . '/' . $relative, '/'));

                    if ($fullPath && is_dir($fullPath)) {
                        return $fullPath;
                    }
                }
            }
        }

        return null;
    }
}
