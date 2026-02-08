  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
  /**
   * Test fixtures for union and intersection type tracking.
   *
   * Tests that:
   * - Union types produce synthetic type symbols
   * - Methods called on union-typed receivers use synthetic symbols
   * - Return types of union-typed methods are tracked correctly
   * - Nullable types are properly represented as union with null
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#
  class UnionTypesFixture
//      ^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#
//      documentation
//      > ```php
//      > class UnionTypesFixture
//      > ```
//      documentation
//      > Test fixtures for union and intersection type tracking.<br>Tests that:<br>- Union types produce synthetic type symbols<br>- Methods called on union-typed receivers use synthetic symbols<br>- Return types of union-typed methods are tracked correctly<br>- Nullable types are properly represented as union with null<br>
  {
      /**
       * Property with union type.
       */
      public string|int $unionProperty;
//                      ^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#$unionProperty.
//                      documentation
//                      > ```php
//                      > public string|int $unionProperty
//                      > ```
//                      documentation
//                      > Property with union type.<br>
  
      /**
       * Property with nullable type (sugar for union with null).
       */
      public ?string $nullableProperty;
//                   ^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#$nullableProperty.
//                   documentation
//                   > ```php
//                   > public ?string $nullableProperty
//                   > ```
//                   documentation
//                   > Property with nullable type (sugar for union with null).<br>
  
      /**
       * Method with union type parameter.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#acceptUnion().
      public function acceptUnion(string|int $value): void
//                    ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#acceptUnion().
//                    documentation
//                    > ```php
//                    > public function acceptUnion(string|int $value): void
//                    > ```
//                    documentation
//                    > Method with union type parameter.<br>
//                                           ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#acceptUnion().($value)
//                                           documentation
//                                           > ```php
//                                           > string|int $value
//                                           > ```
      {
          // Method call on union-typed parameter
          // The callee should be a synthetic union type method
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#acceptUnion().
  
      /**
       * Method with union type return.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnUnion().
      public function returnUnion(): string|int
//                    ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnUnion().
//                    documentation
//                    > ```php
//                    > public function returnUnion(): string|int
//                    > ```
//                    documentation
//                    > Method with union type return.<br>
      {
          return 'result';
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnUnion().
  
      /**
       * Method with nullable return.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnNullable().
      public function returnNullable(): ?string
//                    ^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnNullable().
//                    documentation
//                    > ```php
//                    > public function returnNullable(): ?string
//                    > ```
//                    documentation
//                    > Method with nullable return.<br>
      {
          return null;
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#returnNullable().
  
      /**
       * Calling a method on a union-typed receiver.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#callOnUnionReceiver().
      public function callOnUnionReceiver(Logger|Auditor $handler): void
//                    ^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#callOnUnionReceiver().
//                    documentation
//                    > ```php
//                    > public function callOnUnionReceiver(\TestData\Logger|\TestData\Auditor $handler): void
//                    > ```
//                    documentation
//                    > Calling a method on a union-typed receiver.<br>
//                                        ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//                                                       ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#callOnUnionReceiver().($handler)
//                                                       documentation
//                                                       > ```php
//                                                       > \TestData\Logger|\TestData\Auditor $handler
//                                                       > ```
//                                                       relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                                                       relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
      {
          // This should produce a call with callee = scip-php synthetic union . Auditor|Logger#log().
          $handler->log('event');
//        ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#callOnUnionReceiver().($handler)
//                  ^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#callOnUnionReceiver().
  
      /**
       * Chained call on union-typed receiver.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#chainedUnionCall().
      public function chainedUnionCall(Logger|Auditor $handler): string
//                    ^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#chainedUnionCall().
//                    documentation
//                    > ```php
//                    > public function chainedUnionCall(\TestData\Logger|\TestData\Auditor $handler): string
//                    > ```
//                    documentation
//                    > Chained call on union-typed receiver.<br>
//                                     ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                            ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//                                                    ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#chainedUnionCall().($handler)
//                                                    documentation
//                                                    > ```php
//                                                    > \TestData\Logger|\TestData\Auditor $handler
//                                                    > ```
//                                                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                                                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
      {
          // Chain: $handler->getTag()->process()
          // Each step produces a call record
          return $handler->getTag();
//               ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#chainedUnionCall().($handler)
//                         ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#getTag().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#chainedUnionCall().
  
      /**
       * Union with multiple class types.
       * ClassA, ClassB, and ClassC all have a1() or similar methods.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#multiClassUnion().
      public function multiClassUnion(ClassA|ClassB $obj): int
//                    ^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#multiClassUnion().
//                    documentation
//                    > ```php
//                    > public function multiClassUnion(\TestData\ClassA|\TestData\ClassB $obj): int
//                    > ```
//                    documentation
//                    > Union with multiple class types.<br>ClassA, ClassB, and ClassC all have a1() or similar methods.<br>
//                                    ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClassA#
//                                           ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClassB#
//                                                  ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#multiClassUnion().($obj)
//                                                  documentation
//                                                  > ```php
//                                                  > \TestData\ClassA|\TestData\ClassB $obj
//                                                  > ```
//                                                  relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClassA# type_definition
//                                                  relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClassB# type_definition
      {
          // Method call on union - b2 exists on both ClassA (property) and ClassB (property)
          return $obj->b2;
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#multiClassUnion().($obj)
//                     ^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClassB#$b2.
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#multiClassUnion().
  
      /**
       * Coalesce on nullable property produces union type result.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#coalesceNullable().
      public function coalesceNullable(): string
//                    ^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#coalesceNullable().
//                    documentation
//                    > ```php
//                    > public function coalesceNullable(): string
//                    > ```
//                    documentation
//                    > Coalesce on nullable property produces union type result.<br>
      {
          // $this->nullableProperty is ?string = string|null
          // Coalesce removes null: string|null ?? 'default' => string
          return $this->nullableProperty ?? 'default';
//                      ^^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#$nullableProperty.
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#coalesceNullable().
  
      /**
       * Ternary with union-typed branches.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().
      public function ternaryWithUnions(bool $flag, Logger $a, Auditor $b): Logger|Auditor
//                    ^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().
//                    documentation
//                    > ```php
//                    > public function ternaryWithUnions(bool $flag, \TestData\Logger $a, \TestData\Auditor $b): \TestData\Logger|\TestData\Auditor
//                    > ```
//                    documentation
//                    > Ternary with union-typed branches.<br>
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
//                                           ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($flag)
//                                           documentation
//                                           > ```php
//                                           > bool $flag
//                                           > ```
//                                                  ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                                         ^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($a)
//                                                         documentation
//                                                         > ```php
//                                                         > \TestData\Logger $a
//                                                         > ```
//                                                         relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
//                                                             ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//                                                                     ^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($b)
//                                                                     documentation
//                                                                     > ```php
//                                                                     > \TestData\Auditor $b
//                                                                     > ```
//                                                                     relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                                                                          ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                                                                 ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
      {
          // Result type is Logger|Auditor (union of branch types)
          return $flag ? $a : $b;
//               ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($flag)
//                       ^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($a)
//                            ^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().($b)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#ternaryWithUnions().
  
      /**
       * Match expression with different return types.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#matchWithUnionResult().
      public function matchWithUnionResult(string $status): Logger|Auditor|null
//                    ^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#matchWithUnionResult().
//                    documentation
//                    > ```php
//                    > public function matchWithUnionResult(string $status): \TestData\Logger|\TestData\Auditor|null
//                    > ```
//                    documentation
//                    > Match expression with different return types.<br>
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
//                                                ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#matchWithUnionResult().($status)
//                                                documentation
//                                                > ```php
//                                                > string $status
//                                                > ```
//                                                          ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                                                 ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
      {
          return match ($status) {
//                      ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#matchWithUnionResult().($status)
              'log' => new Logger(),
//                         ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
              'audit' => new Auditor(),
//                           ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
              default => null,
          };
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#matchWithUnionResult().
  
      /**
       * Nested call with union type argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#nestedCallWithUnion().
      public function nestedCallWithUnion(Logger|Auditor $handler): void
//                    ^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#nestedCallWithUnion().
//                    documentation
//                    > ```php
//                    > public function nestedCallWithUnion(\TestData\Logger|\TestData\Auditor $handler): void
//                    > ```
//                    documentation
//                    > Nested call with union type argument.<br>
//                                        ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//                                                       ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#nestedCallWithUnion().($handler)
//                                                       documentation
//                                                       > ```php
//                                                       > \TestData\Logger|\TestData\Auditor $handler
//                                                       > ```
//                                                       relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                                                       relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
      {
          // The argument's value_type should be the union type symbol
          $this->processHandler($handler);
//               ^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().
//                              ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#nestedCallWithUnion().($handler)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#nestedCallWithUnion().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().
      private function processHandler(Logger|Auditor $handler): void
//                     ^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().
//                     documentation
//                     > ```php
//                     > private function processHandler(\TestData\Logger|\TestData\Auditor $handler): void
//                     > ```
//                                    ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//                                           ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//                                                   ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().($handler)
//                                                   documentation
//                                                   > ```php
//                                                   > \TestData\Logger|\TestData\Auditor $handler
//                                                   > ```
//                                                   relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor# type_definition
//                                                   relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger# type_definition
      {
          $handler->log('processed');
//        ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().($handler)
//                  ^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#processHandler().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/UnionTypesFixture#
  
  /**
   * Interface for union type testing.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
  interface Logger
//          ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
//          documentation
//          > ```php
//          > interface Logger
//          > ```
//          documentation
//          > Interface for union type testing.<br>
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().
      public function log(string $msg): void;
//                    ^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().
//                    documentation
//                    > ```php
//                    > public function log(string $msg): void
//                    > ```
//                               ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().($msg)
//                               documentation
//                               > ```php
//                               > string $msg
//                               > ```
//                                          ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#log().
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#getTag().
      public function getTag(): string;
//                    ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#getTag().
//                    documentation
//                    > ```php
//                    > public function getTag(): string
//                    > ```
//                                    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#getTag().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Logger#
  
  /**
   * Interface for union type testing.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
  interface Auditor
//          ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
//          documentation
//          > ```php
//          > interface Auditor
//          > ```
//          documentation
//          > Interface for union type testing.<br>
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#log().
      public function log(string $msg): void;
//                    ^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#log().
//                    documentation
//                    > ```php
//                    > public function log(string $msg): void
//                    > ```
//                               ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#log().($msg)
//                               documentation
//                               > ```php
//                               > string $msg
//                               > ```
//                                          ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#log().
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#getTag().
      public function getTag(): string;
//                    ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#getTag().
//                    documentation
//                    > ```php
//                    > public function getTag(): string
//                    > ```
//                                    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#getTag().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Auditor#
  
