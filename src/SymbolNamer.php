<?php

declare(strict_types=1);

namespace ScipPhp;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use RuntimeException;
use ScipPhp\Composer\Composer;

use function count;
use function explode;
use function implode;
use function is_string;
use function rtrim;
use function sort;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

final readonly class SymbolNamer
{
    private const string SCHEME = 'scip-php';

    private const string MANAGER = 'composer';

    /**
     * Prefix for built-in type symbols.
     * Format: scip-php php builtin . <type>#
     * This follows the SCIP format: scheme manager package version descriptor
     * where '.' is the version placeholder (empty version).
     */
    private const string SCHEME_BUILTIN = 'scip-php php builtin . ';

    /**
     * Prefix for operator symbols.
     * Format: scip-php operator . <operator>#
     * Used for coalesce, elvis, ternary, match operators.
     */
    private const string SCHEME_OPERATOR = 'scip-php operator . ';

    /**
     * Mapping of operator names to their canonical symbol names.
     */
    private const array OPERATOR_NAMES = [
        'coalesce' => 'coalesce',    // ?? operator
        'elvis'    => 'elvis',       // ?: operator (short ternary)
        'ternary'  => 'ternary',     // ? : operator (full ternary)
        'match'    => 'match',       // match expression
    ];

    /**
     * Mapping of PHP built-in type names to their canonical symbol names.
     * These are global symbols with no source-level definition.
     */
    private const array BUILTIN_TYPES = [
        'null'   => 'null',
        'string' => 'string',
        'int'    => 'int',
        'integer' => 'int',  // Alias
        'float'  => 'float',
        'double' => 'float', // Alias
        'bool'   => 'bool',
        'boolean' => 'bool', // Alias
        'array'  => 'array',
        'void'   => 'void',
        'never'  => 'never',
        'mixed'  => 'mixed',
        'true'   => 'true',
        'false'  => 'false',
        'object' => 'object',
        'iterable' => 'iterable',
        'callable' => 'callable',
        'resource' => 'resource',
    ];

    public function __construct(private Composer $composer)
    {
    }

    /**
     * Returns the symbol for a PHP built-in type.
     *
     * @param  string  $type  The built-in type name (e.g., 'null', 'string', 'int')
     * @return ?non-empty-string  The symbol (e.g., 'scip-php builtin . null#'), or null if not a built-in
     */
    public function nameBuiltin(string $type): ?string
    {
        $canonical = self::BUILTIN_TYPES[strtolower($type)] ?? null;
        if ($canonical === null) {
            return null;
        }
        return self::SCHEME_BUILTIN . $canonical . '#';
    }

    /**
     * Check if a type name is a built-in type.
     *
     * @param  string  $type  The type name to check
     */
    public function isBuiltinType(string $type): bool
    {
        return isset(self::BUILTIN_TYPES[strtolower($type)]);
    }

    /**
     * Returns the symbol for a PHP operator.
     *
     * @param  string  $operator  The operator name (e.g., 'coalesce', 'elvis', 'ternary', 'match')
     * @return ?non-empty-string  The symbol (e.g., 'scip-php operator . coalesce#'), or null if not a known operator
     */
    public function nameOperator(string $operator): ?string
    {
        $canonical = self::OPERATOR_NAMES[strtolower($operator)] ?? null;
        if ($canonical === null) {
            return null;
        }
        return self::SCHEME_OPERATOR . $canonical . '#';
    }

    /**
     * Creates a synthetic union type symbol from constituent types.
     *
     * The types are sorted alphabetically to ensure a canonical symbol
     * regardless of declaration order. Built-in types are normalized to their
     * short names (e.g., 'null' not 'scip-php builtin . null#').
     *
     * Format: scip-php union . Foo|Bar|null#
     *
     * @param  list<non-empty-string>  $types  Constituent type short names (sorted alphabetically)
     * @return non-empty-string  The synthetic union symbol
     */
    public function nameUnion(array $types): string
    {
        $shortNames = [];
        foreach ($types as $type) {
            $shortNames[] = $this->extractShortTypeName($type);
        }
        sort($shortNames);
        $typeList = implode('|', $shortNames);
        // Format: scip-php synthetic union . Foo|Bar|null#
        // (scheme manager package version descriptor)
        return 'scip-php synthetic union . ' . $typeList . '#';
    }

    /**
     * Creates a synthetic intersection type symbol from constituent types.
     *
     * The types are sorted alphabetically to ensure a canonical symbol
     * regardless of declaration order.
     *
     * Format: scip-php synthetic intersection . Countable&Serializable#
     *
     * @param  list<non-empty-string>  $types  Constituent type symbols
     * @return non-empty-string  The synthetic intersection symbol
     */
    public function nameIntersection(array $types): string
    {
        $shortNames = [];
        foreach ($types as $type) {
            $shortNames[] = $this->extractShortTypeName($type);
        }
        sort($shortNames);
        $typeList = implode('&', $shortNames);
        // Format: scip-php synthetic intersection . Countable&Serializable#
        // (scheme manager package version descriptor)
        return 'scip-php synthetic intersection . ' . $typeList . '#';
    }

    /**
     * Extract the short type name from a full SCIP symbol or type name.
     *
     * Examples:
     * - 'scip-php composer foo/bar 1.0.0 App/User#' -> 'User'
     * - 'scip-php php builtin . null#' -> 'null'
     * - 'User' -> 'User'
     * - 'App\User' -> 'User'
     *
     * @param  non-empty-string  $type
     * @return non-empty-string
     */
    public function extractShortTypeName(string $type): string
    {
        // Handle builtin types: scip-php php builtin . null#
        if (str_starts_with($type, 'scip-php php builtin . ')) {
            $name = substr($type, 23); // After 'scip-php php builtin . '
            return rtrim($name, '#');
        }

        // Handle composer symbols: scip-php composer pkg ver Namespace/Class#
        if (str_starts_with($type, 'scip-php composer ')) {
            $parts = explode(' ', $type);
            if (count($parts) >= 5) {
                $desc = $parts[4];
                $desc = rtrim($desc, '#');
                // Extract last part after / (the class name)
                $slashPos = strrpos($desc, '/');
                if ($slashPos !== false) {
                    return substr($desc, $slashPos + 1);
                }
                return $desc;
            }
        }

        // Handle union types: scip-php synthetic union . Foo|Bar#
        if (str_starts_with($type, 'scip-php synthetic union . ')) {
            $name = substr($type, 27); // After 'scip-php synthetic union . '
            return rtrim($name, '#');
        }

        // Handle intersection types: scip-php synthetic intersection . Foo&Bar#
        if (str_starts_with($type, 'scip-php synthetic intersection . ')) {
            $name = substr($type, 34); // After 'scip-php synthetic intersection . '
            return rtrim($name, '#');
        }

        // Handle operator symbols: scip-php operator . coalesce#
        if (str_starts_with($type, 'scip-php operator . ')) {
            $name = substr($type, 20); // After 'scip-php operator . '
            return rtrim($name, '#');
        }

        // Handle plain type names (with backslash namespace)
        $backslashPos = strrpos($type, '\\');
        if ($backslashPos !== false) {
            return substr($type, $backslashPos + 1);
        }

        // Already a short name
        return $type;
    }

    /**
     * Returns the fully-qualified class, function or constant name of the given symbol.
     *
     * @param  non-empty-string  $symbol
     * @return non-empty-string
     */
    public function extractIdent(string $symbol): string
    {
        $parts = explode(' ', $symbol);
        if (count($parts) !== 5) {
            throw new RuntimeException("Invalid symbol: {$symbol}.");
        }

        $desc = $parts[4];
        $i = strpos($desc, '#');
        if ($i !== false) {
            $desc = substr($desc, 0, $i);
        }

        $desc = str_replace('/', '\\', $desc);
        $desc = rtrim($desc, '.');
        $desc = rtrim($desc, '()');
        if ($desc === '') {
            throw new LogicException("Cannot extract identifier from symbol: {$symbol}.");
        }
        return $desc;
    }

    /**
     * @param  non-empty-string  $symbol
     * @param  non-empty-string  $const
     * @return non-empty-string
     */
    public function nameConst(string $symbol, string $const): string
    {
        return "{$symbol}{$const}.";
    }

    /**
     * @param  non-empty-string  $symbol
     * @param  non-empty-string  $meth
     * @return non-empty-string
     */
    public function nameMeth(string $symbol, string $meth): string
    {
        return "{$symbol}{$meth}().";
    }

    /**
     * @param  non-empty-string  $symbol
     * @param  non-empty-string  $param
     * @return non-empty-string
     */
    public function nameParam(string $symbol, string $param): string
    {
        return "{$symbol}(\${$param})";
    }

    /**
     * Generate a local variable symbol with scope and line.
     * Format: {scope}.local${name}@{line}
     *
     * Each assignment to a local variable gets a unique symbol based on its line number.
     * This enables tracking variable reassignments and their values separately.
     *
     * @param  non-empty-string  $scope    The enclosing scope symbol (method/function)
     * @param  non-empty-string  $varName  The variable name (without $)
     * @param  int               $line     The line number of the assignment (1-based)
     * @return non-empty-string  The local variable symbol
     */
    public function nameLocalVar(string $scope, string $varName, int $line): string
    {
        return $scope . 'local$' . $varName . '@' . $line;
    }

    /**
     * @param  non-empty-string  $symbol
     * @param  non-empty-string  $prop
     * @return non-empty-string
     */
    public function nameProp(string $symbol, string $prop): string
    {
        return "{$symbol}\${$prop}.";
    }

    /** @return ?non-empty-string */
    public function nameNearestClassLike(Node $n): ?string
    {
        $ns = $this->namespaceName($n);
        $class = $this->classLikeName($n);
        if ($class === null) {
            return null;
        }
        return $this->desc("{$ns}{$class}", '#');
    }

    /** @return ?non-empty-string */
    public function name(Const_|ClassLike|EnumCase|FunctionLike|Name|Param|PropertyItem $n): ?string
    {
        if ($n instanceof ArrowFunction || $n instanceof Closure) {
            $ns = $this->namespaceName($n);
            $func = "anon-func-{$n->getStartTokenPos()}";
            return $this->desc("{$ns}{$func}", '().');
        }

        if ($n instanceof Const_) {
            $ns = $this->namespaceName($n);
            $class = $this->classLikeName($n);
            if ($class === null) {
                return null;
            }
            return $this->desc("{$ns}{$class}", "#{$n->name}.");
        }

        if ($n instanceof ClassLike) {
            $ns = $this->namespaceName($n);
            $class = $n->name?->toString() ?? null;
            if ($class === null || $class === '') {
                $class = "anon-class-{$n->getStartTokenPos()}";
            }
            return $this->desc("{$ns}{$class}", '#');
        }

        if ($n instanceof ClassMethod) {
            $ns = $this->namespaceName($n);
            $class = $this->classLikeName($n);
            if ($class === null) {
                return null;
            }
            return $this->desc("{$ns}{$class}", "#{$n->name}().");
        }

        if ($n instanceof EnumCase) {
            $ns = $this->namespaceName($n);
            $class = $this->classLikeName($n);
            if ($class === null) {
                return null;
            }
            return $this->desc("{$ns}{$class}", "#{$n->name}.");
        }

        if ($n instanceof Function_ && $n->name->toString() !== '') {
            $name = $n->namespacedName?->toString();
            if ($name === null || $name === '') {
                $name = $n->name->toString();
            }
            $name = str_replace('\\', '/', $name);
            return $this->desc($name, '().');
        }

        if ($n instanceof Name && $n->toString() !== '') {
            if ($n->toString() === 'self' || $n->toString() ===  'static') {
                $ns = $this->namespaceName($n);
                $class = $this->classLikeName($n);
                if ($class === null) {
                    return null;
                }
                return $this->desc("{$ns}{$class}", '#');
            }

            if ($n->toString() === 'parent') {
                $classLike = $this->classLike($n);
                if ($classLike === null) {
                    return null;
                }
                if ($classLike instanceof Class_) {
                    if ($classLike->extends === null) {
                        // Reference to parent in class without parent class.
                        return null;
                    }
                    return $this->name($classLike->extends);
                }
                if ($classLike instanceof Trait_) {
                    return null;
                }
                throw new LogicException('Reference to parent in unexpected node type: ' . $classLike::class . '.');
            }

            $name = $n->toString();
            $desc = str_replace('\\', '/', $name);

            if ($this->composer->isConst($name)) {
                return $this->desc($desc, '.');
            }
            if ($this->composer->isClassLike($name)) {
                return $this->desc($desc, '#');
            }
            if ($this->composer->isFunc($name)) {
                return $this->desc($desc, '().');
            }
            // TODO(drj): could be a constant
            return null;
        }

        if ($n instanceof Param && $n->var instanceof Variable && is_string($n->var->name) && $n->var->name !== '') {
            $name = $this->funcLikeName($n);
            if ($name === null) {
                return null;
            }
            return $this->nameParam($name, $n->var->name);
        }

        if ($n instanceof PropertyItem) {
            $ns = $this->namespaceName($n);
            $class = $this->classLikeName($n);
            if ($class === null) {
                return null;
            }
            return $this->desc("{$ns}{$class}", "#\${$n->name}.");
        }

        throw new LogicException('Unexpected node type: ' . $n::class . '.');
    }

    private function namespaceName(Node $n): string
    {
        while (true) {
            $n = $n->getAttribute('parent');
            if ($n === null) {
                return '';
            }
            if ($n instanceof Namespace_) {
                $ns = str_replace("\\", '/', $n->name?->toString() ?? '');
                if ($ns !== '') {
                    return "{$ns}/";
                }
                return '';
            }
        }
    }

    /** @return ?non-empty-string */
    private function classLikeName(Node $n): ?string
    {
        $c = $this->classLike($n);
        if ($c === null) {
            return null;
        }
        $name = $c->name?->toString();
        if ($name === null || $name === '') {
            return "anon-class-{$c->getStartTokenPos()}";
        }
        return $name;
    }

    private function classLike(Node $n): ?ClassLike
    {
        while (true) {
            $n = $n->getAttribute('parent');
            if ($n === null) {
                return null;
            }
            if ($n instanceof ClassLike) {
                return $n;
            }
        }
    }

    /** @return ?non-empty-string */
    public function funcLikeName(Node $n): ?string
    {
        while (true) {
            $n = $n->getAttribute('parent');
            if ($n === null) {
                return null;
            }
            if ($n instanceof FunctionLike) {
                return $this->name($n);
            }
        }
    }

    /**
     * @param  non-empty-string  $name
     * @param  non-empty-string  $suffix
     * @return ?non-empty-string
     */
    private function desc(string $name, string $suffix): ?string
    {
        $c = str_replace('/', '\\', $name);
        $pkg = $this->composer->pkg($c);
        if ($pkg === null) {
            return null;
        }
        ['name' => $pkgName, 'version' => $version] = $pkg;
        return self::SCHEME . ' ' . self::MANAGER . " {$pkgName} {$version} {$name}{$suffix}";
    }
}
