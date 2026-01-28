<?php

declare(strict_types=1);

namespace ScipPhp\Composer;

use Composer\Autoload\ClassLoader;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Composer\ClassMapGenerator\PhpFileParser;
use JetBrains\PHPStormStub\PhpStormStubsMap;
use ReflectionClass;
use ReflectionFunction;
use RuntimeException;
use ScipPhp\File\Reader;

use function array_keys;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_values;
use function class_exists;
use function count;
use function dirname;
use function enum_exists;
use function explode;
use function function_exists;
use function get_defined_constants;
use function get_included_files;
use function implode;
use function interface_exists;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function realpath;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trait_exists;
use function trim;

use Phar;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const PHP_VERSION;

final class Composer
{
    /** @var non-empty-string */
    private readonly string $pkgName;

    /** @var non-empty-string */
    private readonly string $pkgVersion;

    /** @var non-empty-string */
    private readonly string $vendorDir;

    /** @var non-empty-string */
    private readonly string $scipPhpVendorDir;

    /** @var list<non-empty-string> */
    private readonly array $projectFiles;

    private readonly ClassLoader $loader;

    /** @var ?ClassLoader Project's ClassLoader, used only for findFile() lookups, NOT registered for autoloading */
    private readonly ?ClassLoader $projectLoader;

    /** @var array<non-empty-string, array{name: non-empty-string, version: non-empty-string}> */
    private array $pkgsByPaths;

    /** @var array<non-empty-string, scalar> */
    private readonly array $userConsts;

    /**
     * Configuration for treating external packages as internal.
     * @var array{packages: list<string>, classes: list<string>, methods: list<string>}
     */
    private readonly array $internalConfig;

    /** @var non-empty-string Path to the composer.json file */
    private readonly string $composerJsonPath;

    /** @var ?non-empty-string Path to the scip-php.json config file */
    private readonly ?string $configPath;

    /**
     * @param  non-empty-string  $elem
     * @param  non-empty-string  $elems
     * @return non-empty-string
     */
    private static function join(string $elem, string ...$elems): string
    {
        return implode(DIRECTORY_SEPARATOR, [$elem, ...$elems]);
    }

    /**
     * Normalize a path to resolve .. and . without resolving symlinks.
     * @param  non-empty-string  $path
     * @return non-empty-string
     */
    private static function normalizePath(string $path): string
    {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '..' && !empty($normalized) && $normalized[count($normalized) - 1] !== '..') {
                array_pop($normalized);
            } elseif ($part !== '' && $part !== '.') {
                $normalized[] = $part;
            }
        }

        $result = implode(DIRECTORY_SEPARATOR, $normalized);
        if (str_starts_with($path, DIRECTORY_SEPARATOR) && !str_starts_with($result, DIRECTORY_SEPARATOR)) {
            $result = DIRECTORY_SEPARATOR . $result;
        }

        return $result;
    }

    /**
     * @param  non-empty-string       $projectRoot
     * @param  ?non-empty-string      $composerJsonPath  Optional path to composer.json (default: <projectRoot>/composer.json)
     * @param  ?non-empty-string      $configPath        Optional path to scip-php.json (default: <projectRoot>/scip-php.json)
     */
    public function __construct(
        private readonly string $projectRoot,
        ?string $composerJsonPath = null,
        ?string $configPath = null,
    ) {
        // Resolve composer.json path
        $this->composerJsonPath = $composerJsonPath ?? self::join($this->projectRoot, 'composer.json');
        $this->configPath = $configPath;

        $json = $this->parseJsonFile($this->composerJsonPath);
        $autoload = is_array($json['autoload'] ?? null) ? $json['autoload'] : [];
        $autoloadDev = is_array($json['autoload-dev'] ?? null) ? $json['autoload-dev'] : [];

        // Determine scip-php's vendor directory
        // When running from phar, use phar's bundled vendor; otherwise use project vendor
        $pharPath = Phar::running(false);
        if ($pharPath !== '') {
            // Running from phar - use bundled vendor
            $this->scipPhpVendorDir = 'phar://' . $pharPath . '/vendor';
        } else {
            // Running from source - use project vendor or scip-php's vendor
            $scipPhpVendorDir = dirname(__DIR__, 2) . '/vendor';
            if (!is_dir($scipPhpVendorDir)) {
                $scipPhpVendorDir = $this->projectRoot . '/vendor';
            }
            if (!is_dir($scipPhpVendorDir)) {
                throw new RuntimeException("Invalid scip-php vendor directory: {$scipPhpVendorDir}.");
            }
            $this->scipPhpVendorDir = $scipPhpVendorDir;
        }

        $bin = [];
        if (is_array($json['bin'] ?? null)) {
            $bin = $this->collectPaths($json['bin']);
        }
        $this->projectFiles = array_merge(
            $bin,
            $this->loadProjectFiles($autoload),
            $this->loadProjectFiles($autoloadDev),
        );

        $vendorDir = 'vendor';
        if (
            is_array($json['config'] ?? null)
            && is_string($json['config']['vendor-dir'] ?? null)
            && trim($json['config']['vendor-dir'], '/') !== ''
        ) {
            $vendorDir = trim($json['config']['vendor-dir'], '/');
        }
        $this->vendorDir = self::join($projectRoot, $vendorDir);

        // Always use scip-php's bundled vendor for runtime autoloading
        $this->loader = require self::join($this->scipPhpVendorDir, 'autoload.php');

        // Load the project's ClassLoader for file lookups only.
        // Immediately unregister it so it never interferes with runtime class loading.
        $projectVendorAutoload = self::join($this->vendorDir, 'autoload.php');
        $projectLoader = null;
        if (is_file($projectVendorAutoload)) {
            $loader = require $projectVendorAutoload; // @phpstan-ignore-line
            if ($loader instanceof ClassLoader) {
                $loader->unregister();
                $projectLoader = $loader;
            }
        }
        $this->projectLoader = $projectLoader;

        $installed = require self::join($this->vendorDir, 'composer', 'installed.php');
        $this->pkgName = $installed['root']['name'];
        $this->pkgVersion = $installed['root']['reference'] ?? $installed['root']['version'];

        $additionalClasses = [];
        foreach ($this->projectFiles as $f) {
            $classes = PhpFileParser::findClasses($f);
            foreach ($classes as $c) {
                if ($this->loader->findFile($c) === false) {
                    $additionalClasses[$c] = $f;
                }
            }
        }
        $this->loader->addClassMap($additionalClasses);

        $pkgsByPaths = [];
        foreach ($installed['versions'] as $name => $info) {
            // Replaced packages do not have an install path.
            // See https://getcomposer.org/doc/04-schema.md#replace
            if (!isset($info['install_path'])) {
                continue;
            }
            $path = $info['install_path'];
            if (!is_dir($path)) {
                throw new RuntimeException("Invalid install path of package {$name}: {$info['install_path']}.");
            }
            if ($name !== $this->pkgName) {
                $normalizedPath = self::normalizePath($path);
                $pkgsByPaths[$normalizedPath] = ['name' => $name, 'version' => $info['reference']];
                if ($normalizedPath !== $path) {
                    $pkgsByPaths[$path] = ['name' => $name, 'version' => $info['reference']];
                }
            }
        }

        $composerPath = self::join($this->vendorDir, 'composer');
        $normalizedComposerPath = self::normalizePath($composerPath);
        $pkgsByPaths[$normalizedComposerPath] = ['name' => 'composer', 'version' => 'dev'];
        if ($normalizedComposerPath !== $composerPath) {
            $pkgsByPaths[$composerPath] = ['name' => 'composer', 'version' => 'dev'];
        }
        $this->pkgsByPaths = $pkgsByPaths;

        // Load composer.lock from the same directory as composer.json
        $composerDir = dirname($this->composerJsonPath);
        $lockFile = self::join($composerDir, 'composer.lock');
        $lock = $this->parseJsonFile($lockFile);
        if (is_array($lock['packages'] ?? null)) {
            foreach ($lock['packages'] as $pkg) {
                if (
                    !is_array($pkg)
                    || !is_array($pkg['autoload'] ?? null)
                    || !is_array($pkg['autoload']['files'] ?? null)
                    || !is_string($pkg['name'] ?? null)
                    || $pkg['name'] === ''
                ) {
                    continue;
                }
                foreach ($pkg['autoload']['files'] as $f) {
                    if (!is_string($f) || $f === '') {
                        continue;
                    }
                    $f = self::join($this->vendorDir, $pkg['name'], $f);
                    $classes = PhpFileParser::findClasses($f);
                    foreach ($classes as $c) {
                        if ($this->loader->findFile($c) === false) {
                            $additionalClasses[$c] = $f;
                        }
                    }
                }
            }
        }
        $this->loader->addClassMap($additionalClasses);

        // Load project's autoload.files to define constants and functions
        if (is_array($autoload['files'] ?? null)) {
            foreach ($autoload['files'] as $f) {
                if (is_string($f) && $f !== '') {
                    $filePath = self::join($projectRoot, $f);
                    if (is_file($filePath)) {
                        require_once $filePath; // @phpstan-ignore-line
                    }
                }
            }
        }

        $this->userConsts = get_defined_constants(categorize: true)['user'] ?? []; // @phpstan-ignore-line

        // Load scip-php.json config for treating external packages as internal
        $this->internalConfig = $this->loadInternalConfig();
    }

    /**
     * Load the scip-php.json configuration file.
     * @return array{packages: list<string>, classes: list<string>, methods: list<string>}
     */
    private function loadInternalConfig(): array
    {
        $default = ['packages' => [], 'classes' => [], 'methods' => []];

        // Use provided config path, or default to <projectRoot>/scip-php.json
        $configFile = $this->configPath ?? self::join($this->projectRoot, 'scip-php.json');

        if (!is_file($configFile)) {
            return $default;
        }

        $content = Reader::read($configFile);
        $config = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($config)) {
            return $default;
        }

        return [
            'packages' => is_array($config['internal_packages'] ?? null)
                ? array_values(array_filter($config['internal_packages'], 'is_string'))
                : [],
            'classes' => is_array($config['internal_classes'] ?? null)
                ? array_values(array_filter($config['internal_classes'], 'is_string'))
                : [],
            'methods' => is_array($config['internal_methods'] ?? null)
                ? array_values(array_filter($config['internal_methods'], 'is_string'))
                : [],
        ];
    }

    /**
     * Parse a JSON file from an absolute path.
     * @param  non-empty-string  $filePath
     * @return array<string, mixed>
     */
    private function parseJsonFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}.");
        }
        $content = Reader::read($filePath);
        $json = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($json)) {
            throw new RuntimeException("Cannot parse {$filePath}.");
        }
        return $json;
    }

    /**
     * @param  array<string, mixed>  $autoload
     * @return list<non-empty-string>
     */
    private function loadProjectFiles(array $autoload): array
    {
        $generator = new ClassMapGenerator();
        $exclusionRegex = null;
        if (is_array($autoload['exclude-from-classmap'] ?? null) && count($autoload['exclude-from-classmap']) > 0) {
            $exclusionRegex = '{(' . implode('|', $autoload['exclude-from-classmap']) . ')}';
        }
        if (is_array($autoload['classmap'] ?? null)) {
            foreach ($autoload['classmap'] as $path) {
                $p = self::join($this->projectRoot, $path);
                $generator->scanPaths($p, $exclusionRegex);
            }
        }
        foreach (['psr-4', 'psr-0'] as $t) {
            if (!is_array($autoload[$t] ?? null)) {
                continue;
            }
            foreach ($autoload[$t] as $ns => $paths) {
                if (!is_string($ns) || $ns === '' || (!is_array($paths) && !is_string($paths))) {
                    continue;
                }
                $paths = is_string($paths) ? [$paths] : $paths;
                foreach ($paths as $path) {
                    if (!is_string($path) || $path === '') {
                        continue;
                    }
                    $p = self::join($this->projectRoot, $path);
                    $p = rtrim($p, DIRECTORY_SEPARATOR);
                    $generator->scanPaths($p, $exclusionRegex, $t, $ns);
                }
            }
        }

        $map = $generator->getClassMap();
        $map->sort();
        $classFiles = array_unique(array_values($map->getMap()));

        if (!is_array($autoload['files'] ?? null)) {
            return $classFiles;
        }
        $files = $this->collectPaths($autoload['files']);
        return array_merge($files, $classFiles);
    }

    /**
     * @param  list<string>  $paths
     * @return list<non-empty-string>
     */
    private function collectPaths(array $paths): array
    {
        $files = [];
        foreach ($paths as $p) {
            if (!is_string($p) || $p === '') {
                continue;
            }
            $p = self::join($this->projectRoot, $p);
            if (is_file($p) || is_dir($p)) {
                $files[] = $p;
            }
        }
        return $files;
    }

    /** @return list<non-empty-string> */
    public function projectFiles(): array
    {
        return $this->projectFiles;
    }

    /** @param  non-empty-string  $ident */
    public function isDependency(string $ident): bool
    {
        // Check if the identifier is configured as internal
        if ($this->isConfiguredAsInternal($ident)) {
            return false;
        }
        return !$this->isFromProject($ident);
    }

    /**
     * Check if an identifier is configured to be treated as internal.
     * @param  non-empty-string  $ident
     */
    private function isConfiguredAsInternal(string $ident): bool
    {
        // Check against configured methods (e.g., "App\\Service\\MyClass::myMethod")
        foreach ($this->internalConfig['methods'] as $method) {
            if ($ident === $method || str_ends_with($ident, '\\' . $method)) {
                return true;
            }
        }

        // Check against configured classes (e.g., "App\\Service\\MyClass")
        foreach ($this->internalConfig['classes'] as $class) {
            // Match exact class or any member of the class
            if ($ident === $class || str_starts_with($ident, $class . '::') || str_starts_with($ident, $class . '\\')) {
                return true;
            }
        }

        // Check against configured packages (e.g., "vendor/package")
        $f = $this->findFile($ident);
        if ($f !== null) {
            foreach ($this->internalConfig['packages'] as $pkg) {
                // Package names in vendor directories
                $pkgPath = DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkg) . DIRECTORY_SEPARATOR;
                if (str_contains($f, $pkgPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  non-empty-string  $c */
    public function isConst(string $c): bool
    {
        return isset(PhpStormStubsMap::CONSTANTS[$c]) || isset($this->userConsts[$c]);
    }

    /** @param  non-empty-string  $c */
    public function isClassLike(string $c): bool
    {
        return isset(PhpStormStubsMap::CLASSES[$c])
            || str_contains($c, 'anon-class-')
            || (str_starts_with($c, 'Composer\\Autoload\\') && class_exists($c))
            || (
                // The goal is to avoid calling {class,interface,trait,enum}_exists if it is not absolutely necessary.
                // This is because if the file contains a fatal error, it will generate a fatal error. Since findFile
                // also returns the path to the file of a namespaced function, check that the identifier is not a
                // function. However, since it is possible that a class-like and a function have the same name, we
                // must call {class,interface,trait,enum}_exists as a last resort.
                ($this->loader->findFile($c) !== false || ($this->projectLoader !== null && $this->projectLoader->findFile($c) !== false)) && (
                    !function_exists($c)
                    || class_exists($c) || interface_exists($c) || trait_exists($c) || enum_exists($c)
                )
            );
    }

    /** @param  non-empty-string  $f */
    public function isFunc(string $f): bool
    {
        return function_exists($f) || isset(PhpStormStubsMap::FUNCTIONS[$f]) || str_contains($f, 'anon-func-');
    }

    /**
     * @param  non-empty-string  $ident
     * @return ?non-empty-string
     */
    public function findFile(string $ident): ?string
    {
        // PHP built-ins are in the phpstorm-stubs files
        $stub = $this->stub($ident);
        if ($stub !== null) {
            $stubsPath = self::join($this->scipPhpVendorDir, 'jetbrains', 'phpstorm-stubs', $stub);
            if (is_file($stubsPath)) {
                return $stubsPath;
            }
            return null;
        }

        $f = $this->loader->findFile($ident);
        if ($f !== false && is_file($f)) {
            // If found in scip-php's vendor, check if the project has it too
            // (e.g., a shared dependency). Prefer the project's copy for indexing.
            if (str_contains($f, $this->scipPhpVendorDir) && $this->projectLoader !== null) {
                $pf = $this->projectLoader->findFile($ident);
                if ($pf !== false && is_file($pf)) {
                    return $pf;
                }
            }
            return $f;
        }

        // Fallback to project loader for classes only in the project's vendor
        if ($this->projectLoader !== null) {
            $f = $this->projectLoader->findFile($ident);
            if ($f !== false && is_file($f)) {
                return $f;
            }
        }

        if (function_exists($ident)) {
            $func = new ReflectionFunction($ident);
            $f = $func->getFileName();
            if ($f !== false && $f !== '') {
                if (!str_contains($f, $this->scipPhpVendorDir)) {
                    return $f;
                }
                // In case of a conflict between a function defined in a dependency of scip-php
                // and a function defined in the analyzed project or its dependencies, the
                // former is used here. Therefore, we patch the path, so that the latter is
                // analyzed instead.
                $vendorFile = str_replace($this->scipPhpVendorDir, $this->vendorDir, $f);
                if (is_file($vendorFile)) {
                    return $vendorFile;
                }
                // If the file is not found in the vendor directory, we probably analyze
                // a project which is also a dependency of scip-php.
                $f = str_replace($this->scipPhpVendorDir . DIRECTORY_SEPARATOR, '', $f);
                $f = preg_replace('/^\w+\/\w+\//', '', $f, limit: 1);
                if ($f === null || $f === '') {
                    throw new RuntimeException("Invalid path to function file: {$func->getFileName()}.");
                }
                return self::join($this->projectRoot, $f);
            }
        }

        if (str_starts_with($ident, 'Composer\\Autoload\\') && class_exists($ident)) {
            $class = new ReflectionClass($ident);
            $f = $class->getFileName();
            if ($f !== false && $f !== '') {
                // In case the analyzed project uses composer classes, patch
                // the path, so that the composer file of the project is analyzed.
                // There is no support for the global composer init classes.
                return str_replace($this->scipPhpVendorDir, $this->vendorDir, $f);
            }
        }
        return $this->findConstFile($ident);
    }

    /**
     * @param  non-empty-string  $ident
     * @return ?array{name: non-empty-string, version: non-empty-string}
     */
    public function pkg(string $ident): ?array
    {
        if ($this->isStub($ident)) {
            return ['name' => 'php', 'version' => PHP_VERSION];
        }
        if ($this->isFromProject($ident)) {
            return ['name' => $this->pkgName, 'version' => $this->pkgVersion];
        }
        $f = $this->findFile($ident);
        if ($f === null) {
            return null;
        }
        foreach ($this->pkgsByPaths as $path => $info) {
            $pathNormalized = rtrim($path, DIRECTORY_SEPARATOR);
            if (str_starts_with($f, $pathNormalized . DIRECTORY_SEPARATOR)) {
                return $info;
            }
        }
        throw new RuntimeException("Cannot find package for identifier {$ident} in file {$f}.");
    }

    /** @param  non-empty-string  $ident */
    private function isFromProject(string $ident): bool
    {
        if (str_contains($ident, 'anon-class-') || str_contains($ident, 'anon-func-')) {
            return true;
        }
        if ($this->isStub($ident)) {
            return false;
        }
        $f = $this->findFile($ident);
        if ($f === null) {
            return false;
        }
        foreach (array_keys($this->pkgsByPaths) as $path) {
            if (str_starts_with($f, $path)) {
                return false;
            }
        }
        return !str_starts_with($f, $this->vendorDir);
    }

    /** @param  non-empty-string  $ident */
    private function isStub(string $ident): bool
    {
        return $this->stub($ident) !== null || $ident === 'IntBackedEnum' || $ident === 'StringBackedEnum';
    }

    /**
     * @param  non-empty-string  $ident
     * @return ?non-empty-string
     */
    private function stub(string $ident): ?string
    {
        return PhpStormStubsMap::CLASSES[$ident]
            ?? PhpStormStubsMap::FUNCTIONS[$ident]
            ?? PhpStormStubsMap::CONSTANTS[$ident]
            ?? null;
    }

    /**
     * @param  non-empty-string  $c
     * @return ?non-empty-string
     */
    private function findConstFile(string $c): ?string
    {
        if (!isset($this->userConsts[$c])) {
            return null;
        }

        $parts = explode('\\', $c);
        $last = count($parts) - 1;
        $hasNs = $last > 0;
        $ns = implode('\\', array_slice($parts, 0, $last));
        $const = $parts[$last];
        $ns = preg_quote($ns);
        $qualifiedConst = str_replace('\\', '\\\\', $c);
        $qualifiedConst = preg_quote($qualifiedConst);

        // TODO(drj): replace with an AST visitor.
        $defineConstPattern = "/^\s*define\s*\(\s*['\"]{$qualifiedConst}['\"]\s*,/m";
        $assignConstPattern = "/^\s*const\s+{$const}\s*=/m";
        $nsPattern = "/^\s*namespace\s+{$ns};/m";
        $anyNsPattern = '/^\s*namespace\s+.+;/m';

        $files = get_included_files();
        foreach ($files as $f) {
            if ($f === '' || !is_file($f)) {
                continue;
            }

            $content = Reader::read($f);
            if (preg_match($defineConstPattern, $content) === 1) {
                return $f;
            }
            if (preg_match($assignConstPattern, $content) !== 1) {
                continue;
            }
            if ($hasNs && preg_match($nsPattern, $content) === 1) {
                return $f;
            }
            if (!$hasNs && preg_match($anyNsPattern, $content) === 0) {
                return $f;
            }
        }
        return null;
    }
}
