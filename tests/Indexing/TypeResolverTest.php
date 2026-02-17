<?php

declare(strict_types=1);

namespace Tests\Indexing;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ScipPhp\Composer\Composer;
use ScipPhp\Indexing\TypeResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Internal\Type;
use ScipPhp\Types\Types;

use function count;

use const DIRECTORY_SEPARATOR;

#[RunTestsInSeparateProcesses]
final class TypeResolverTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__
        . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'Indexer'
        . DIRECTORY_SEPARATOR . 'testdata'
        . DIRECTORY_SEPARATOR . 'scip-php-test';

    private TypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $composer = new Composer(self::TESTDATA_DIR);
        $namer = new SymbolNamer($composer);
        $types = new Types($composer, $namer);

        $this->resolver = new TypeResolver($namer, $types);
    }

    public function testResolveCallKindConstructor(): void
    {
        $node = new New_(new Name('Foo'));
        self::assertSame('constructor', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindFuncCall(): void
    {
        $node = new FuncCall(new Name('array_map'));
        self::assertSame('function', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindStaticCall(): void
    {
        $node = new StaticCall(new Name('Foo'), 'bar');
        self::assertSame('method_static', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindMethodCall(): void
    {
        $node = new MethodCall(new Variable('obj'), 'method');
        self::assertSame('method', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindNullsafeMethodCall(): void
    {
        $node = new NullsafeMethodCall(new Variable('obj'), 'method');
        self::assertSame('method', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindPropertyFetch(): void
    {
        $node = new PropertyFetch(new Variable('obj'), 'prop');
        self::assertSame('access', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindNullsafePropertyFetch(): void
    {
        $node = new NullsafePropertyFetch(new Variable('obj'), 'prop');
        self::assertSame('access', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindStaticPropertyFetch(): void
    {
        $node = new StaticPropertyFetch(new Name('Foo'), 'prop');
        self::assertSame('access_static', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindArrayDimFetch(): void
    {
        $node = new ArrayDimFetch(new Variable('arr'));
        self::assertSame('access_array', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindCoalesce(): void
    {
        $node = new Coalesce(new Variable('a'), new Variable('b'));
        self::assertSame('coalesce', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindTernaryElvis(): void
    {
        $node = new Ternary(new Variable('a'), null, new Variable('b'));
        self::assertSame('ternary', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindTernaryFull(): void
    {
        $node = new Ternary(new Variable('a'), new Variable('b'), new Variable('c'));
        self::assertSame('ternary_full', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindMatch(): void
    {
        $node = new Match_(new Variable('x'), []);
        self::assertSame('match', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindVariable(): void
    {
        $node = new Variable('x');
        self::assertSame('local', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindClassConstFetch(): void
    {
        $node = new ClassConstFetch(new Name('Foo'), 'BAR');
        self::assertSame('constant', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindConstFetch(): void
    {
        $node = new ConstFetch(new Name('PHP_INT_MAX'));
        self::assertSame('constant', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindScalar(): void
    {
        $node = new String_('hello');
        self::assertSame('literal', $this->resolver->resolveCallKind($node));
    }

    public function testResolveCallKindArrayLiteral(): void
    {
        $node = new Array_([]);
        self::assertSame('literal', $this->resolver->resolveCallKind($node));
    }

    public function testResolveKindTypeInvocations(): void
    {
        self::assertSame('invocation', $this->resolver->resolveKindType('method'));
        self::assertSame('invocation', $this->resolver->resolveKindType('method_static'));
        self::assertSame('invocation', $this->resolver->resolveKindType('function'));
        self::assertSame('invocation', $this->resolver->resolveKindType('constructor'));
    }

    public function testResolveKindTypeAccess(): void
    {
        self::assertSame('access', $this->resolver->resolveKindType('access'));
        self::assertSame('access', $this->resolver->resolveKindType('access_static'));
        self::assertSame('access', $this->resolver->resolveKindType('access_array'));
    }

    public function testResolveKindTypeOperators(): void
    {
        self::assertSame('operator', $this->resolver->resolveKindType('coalesce'));
        self::assertSame('operator', $this->resolver->resolveKindType('ternary'));
        self::assertSame('operator', $this->resolver->resolveKindType('ternary_full'));
        self::assertSame('operator', $this->resolver->resolveKindType('match'));
    }

    public function testResolveKindTypeDefaultFallback(): void
    {
        self::assertSame('invocation', $this->resolver->resolveKindType('unknown'));
        self::assertSame('invocation', $this->resolver->resolveKindType(''));
    }

    public function testFindEnclosingScopeNoParent(): void
    {
        $node = new Variable('x');
        self::assertNull($this->resolver->findEnclosingScope($node));
    }

    public function testFormatTypeForDocNull(): void
    {
        self::assertSame('mixed', $this->resolver->formatTypeForDoc(null));
    }

    public function testFormatTypeForDocEmptyType(): void
    {
        $type = $this->createType([]);
        self::assertSame('mixed', $this->resolver->formatTypeForDoc($type));
    }

    public function testFormatTypeForDocBuiltinType(): void
    {
        $type = $this->createType(['scip-php php builtin . int#']);
        $result = $this->resolver->formatTypeForDoc($type);
        self::assertSame('int', $result);
    }

    public function testFormatTypeForDocClassType(): void
    {
        $type = $this->createType(['scip-php composer some/pkg 1.0 Foo#']);
        $result = $this->resolver->formatTypeForDoc($type);
        self::assertSame('scip-php composer some/pkg 1.0 Foo#', $result);
    }

    public function testFormatTypeSymbolNull(): void
    {
        self::assertNull($this->resolver->formatTypeSymbol(null));
    }

    public function testFormatTypeSymbolEmptyType(): void
    {
        $type = $this->createType([]);
        self::assertNull($this->resolver->formatTypeSymbol($type));
    }

    public function testFormatTypeSymbolSingleType(): void
    {
        $type = $this->createType(['scip-php php builtin . int#']);
        self::assertSame('scip-php php builtin . int#', $this->resolver->formatTypeSymbol($type));
    }

    public function testResolveExpressionReturnTypeNullType(): void
    {
        $node = new Variable('x');
        self::assertNull($this->resolver->resolveExpressionReturnType($node));
    }

    public function testResolveValueTypeNullType(): void
    {
        $node = new Variable('x');
        self::assertNull($this->resolver->resolveValueType($node));
    }

    /** @param list<non-empty-string> $symbols */
    private function createType(array $symbols): Type
    {
        return new class ($symbols) implements Type {
            /** @param list<non-empty-string> $symbols */
            public function __construct(private readonly array $symbols)
            {
            }

            /** @return list<non-empty-string> */
            public function flatten(): array
            {
                return $this->symbols;
            }

            public function isComposite(): bool
            {
                return count($this->symbols) > 1;
            }
        };
    }
}
