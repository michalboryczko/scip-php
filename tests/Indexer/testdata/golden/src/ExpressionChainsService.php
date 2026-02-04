  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
  /**
   * Test fixtures for expression chain tracking in calls.json.
   *
   * This file exercises the full expression chain tracking feature,
   * including variable accesses, property chains, operators, and literals.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#
  class ExpressionChainsService
//      ^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#
//      documentation
//      > ```php
//      > class ExpressionChainsService
//      > ```
//      documentation
//      > Test fixtures for expression chain tracking in calls.json.<br>This file exercises the full expression chain tracking feature,<br>including variable accesses, property chains, operators, and literals.<br>
  {
      /**
       * Simple variable access as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#variableAsArgument().
      public function variableAsArgument(string $name): void
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#variableAsArgument().
//                    documentation
//                    > ```php
//                    > public function variableAsArgument(string $name): void
//                    > ```
//                    documentation
//                    > Simple variable access as argument.<br>
//                                              ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#variableAsArgument().($name)
//                                              documentation
//                                              > ```php
//                                              > string $name
//                                              > ```
      {
          // $name is tracked as a variable call, then passed as argument
          $this->logName($name);
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#variableAsArgument().($name)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#variableAsArgument().
  
      /**
       * Property chain as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#propertyChainAsArgument().
      public function propertyChainAsArgument(Message $msg): void
//                    ^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#propertyChainAsArgument().
//                    documentation
//                    > ```php
//                    > public function propertyChainAsArgument(\TestData\Message $msg): void
//                    > ```
//                    documentation
//                    > Property chain as argument.<br>
//                                            ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message#
//                                                    ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#propertyChainAsArgument().($msg)
//                                                    documentation
//                                                    > ```php
//                                                    > \TestData\Message $msg
//                                                    > ```
//                                                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message# type_definition
      {
          // $msg->address->street is a chain:
          // 1. $msg (variable)
          // 2. ->address (property)
          // 3. ->street (property)
          $this->logName($msg->address->street ?? 'unknown');
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#propertyChainAsArgument().($msg)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#propertyChainAsArgument().
  
      /**
       * Nullsafe property chain with coalesce.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().
      public function nullsafeChainWithCoalesce(Message $msg): ?Coordinates
//                    ^^^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().
//                    documentation
//                    > ```php
//                    > public function nullsafeChainWithCoalesce(\TestData\Message $msg): ?\TestData\Coordinates
//                    > ```
//                    documentation
//                    > Nullsafe property chain with coalesce.<br>
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Coordinates# type_definition
//                                              ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message#
//                                                      ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().($msg)
//                                                      documentation
//                                                      > ```php
//                                                      > \TestData\Message $msg
//                                                      > ```
//                                                      relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message# type_definition
//                                                              ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Coordinates#
      {
          // Full chain: $msg->address->coordinates?->latitude ?? 0.0
          return new Coordinates(
//                   ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Coordinates#
              $msg->address?->coordinates?->latitude ?? 0.0,
//            ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().($msg)
              $msg->address?->coordinates?->longitude ?? 0.0
//            ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().($msg)
          );
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nullsafeChainWithCoalesce().
  
      /**
       * Ternary expression as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().
      public function ternaryAsArgument(bool $flag, string $a, string $b): void
//                    ^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().
//                    documentation
//                    > ```php
//                    > public function ternaryAsArgument(bool $flag, string $a, string $b): void
//                    > ```
//                    documentation
//                    > Ternary expression as argument.<br>
//                                           ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($flag)
//                                           documentation
//                                           > ```php
//                                           > bool $flag
//                                           > ```
//                                                         ^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($a)
//                                                         documentation
//                                                         > ```php
//                                                         > string $a
//                                                         > ```
//                                                                    ^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($b)
//                                                                    documentation
//                                                                    > ```php
//                                                                    > string $b
//                                                                    > ```
      {
          // Full ternary: $flag ? $a : $b
          $this->logName($flag ? $a : $b);
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($flag)
//                               ^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($a)
//                                    ^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().($b)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#ternaryAsArgument().
  
      /**
       * Elvis operator as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#elvisAsArgument().
      public function elvisAsArgument(?string $name): void
//                    ^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#elvisAsArgument().
//                    documentation
//                    > ```php
//                    > public function elvisAsArgument(?string $name): void
//                    > ```
//                    documentation
//                    > Elvis operator as argument.<br>
//                                            ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#elvisAsArgument().($name)
//                                            documentation
//                                            > ```php
//                                            > ?string $name
//                                            > ```
      {
          // Elvis: $name ?: 'default'
          $this->logName($name ?: 'default');
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#elvisAsArgument().($name)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#elvisAsArgument().
  
      /**
       * Match expression as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#matchAsArgument().
      public function matchAsArgument(string $status): void
//                    ^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#matchAsArgument().
//                    documentation
//                    > ```php
//                    > public function matchAsArgument(string $status): void
//                    > ```
//                    documentation
//                    > Match expression as argument.<br>
//                                           ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#matchAsArgument().($status)
//                                           documentation
//                                           > ```php
//                                           > string $status
//                                           > ```
      {
          // Match expression
          $label = match($status) {
//        ^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $label: mixed
//        documentation
//        > ```
//                       ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#matchAsArgument().($status)
              'active' => 'Active Status',
              'pending' => 'Pending Status',
              default => 'Unknown Status',
          };
          $this->logName($label);
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^^^ reference local 0
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#matchAsArgument().
  
      /**
       * Nested constructor calls.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nestedConstructors().
      public function nestedConstructors(): Message
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nestedConstructors().
//                    documentation
//                    > ```php
//                    > public function nestedConstructors(): \TestData\Message
//                    > ```
//                    documentation
//                    > Nested constructor calls.<br>
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message# type_definition
//                                          ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message#
      {
          // Nested: new Message(new Address(..., new Coordinates(...)))
          return new Message(
//                   ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message#
              new Address(
//                ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Address#
                  'Main Street',
                  new Coordinates(51.5074, -0.1278)
//                    ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Coordinates#
              )
          );
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#nestedConstructors().
  
      /**
       * Array access as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#arrayAccessAsArgument().
      public function arrayAccessAsArgument(array $data): void
//                    ^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#arrayAccessAsArgument().
//                    documentation
//                    > ```php
//                    > public function arrayAccessAsArgument(array $data): void
//                    > ```
//                    documentation
//                    > Array access as argument.<br>
//                                                ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#arrayAccessAsArgument().($data)
//                                                documentation
//                                                > ```php
//                                                > array $data
//                                                > ```
      {
          // Array access: $data['name']
          $this->logName($data['name'] ?? 'unknown');
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                       ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#arrayAccessAsArgument().($data)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#arrayAccessAsArgument().
  
      /**
       * Class constant as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#constantAsArgument().
      public function constantAsArgument(): void
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#constantAsArgument().
//                    documentation
//                    > ```php
//                    > public function constantAsArgument(): void
//                    > ```
//                    documentation
//                    > Class constant as argument.<br>
      {
          $this->logPrecision(self::DEFAULT_PRECISION);
//               ^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().
//                            ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#
//                                  ^^^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#DEFAULT_PRECISION.
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#constantAsArgument().
  
      /**
       * Literal values as arguments.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#literalsAsArguments().
      public function literalsAsArguments(): void
//                    ^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#literalsAsArguments().
//                    documentation
//                    > ```php
//                    > public function literalsAsArguments(): void
//                    > ```
//                    documentation
//                    > Literal values as arguments.<br>
      {
          $this->logName('literal string');
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
          $this->logPrecision(42);
//               ^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().
          $this->logFlag(true);
//               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logFlag().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#literalsAsArguments().
  
      /**
       * Complex chained expression.
       * $msg->address->coordinates?->latitude ?? $fallback->coordinates->latitude ?? 0.0
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().
      public function complexChain(Message $msg, Address $fallback): float
//                    ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().
//                    documentation
//                    > ```php
//                    > public function complexChain(\TestData\Message $msg, \TestData\Address $fallback): float
//                    > ```
//                    documentation
//                    > Complex chained expression.<br>$msg->address->coordinates?->latitude ?? $fallback->coordinates->latitude ?? 0.0<br>
//                                 ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message#
//                                         ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().($msg)
//                                         documentation
//                                         > ```php
//                                         > \TestData\Message $msg
//                                         > ```
//                                         relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Message# type_definition
//                                               ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Address#
//                                                       ^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().($fallback)
//                                                       documentation
//                                                       > ```php
//                                                       > \TestData\Address $fallback
//                                                       > ```
//                                                       relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Address# type_definition
      {
          return $msg->address?->coordinates?->latitude
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().($msg)
              ?? $fallback->coordinates?->latitude
//               ^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().($fallback)
              ?? 0.0;
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#complexChain().
  
      private const DEFAULT_PRECISION = 6;
//                  ^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#DEFAULT_PRECISION.
//                  documentation
//                  > ```php
//                  > private DEFAULT_PRECISION = 6
//                  > ```
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
      private function logName(?string $name): void
//                     ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
//                     documentation
//                     > ```php
//                     > private function logName(?string $name): void
//                     > ```
//                                     ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().($name)
//                                     documentation
//                                     > ```php
//                                     > ?string $name
//                                     > ```
      {
          // Just a sink for arguments
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logName().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().
      private function logPrecision(int $precision): void
//                     ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().
//                     documentation
//                     > ```php
//                     > private function logPrecision(int $precision): void
//                     > ```
//                                      ^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().($precision)
//                                      documentation
//                                      > ```php
//                                      > int $precision
//                                      > ```
      {
          // Just a sink for arguments
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logPrecision().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logFlag().
      private function logFlag(bool $flag): void
//                     ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logFlag().
//                     documentation
//                     > ```php
//                     > private function logFlag(bool $flag): void
//                     > ```
//                                  ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logFlag().($flag)
//                                  documentation
//                                  > ```php
//                                  > bool $flag
//                                  > ```
      {
          // Just a sink for arguments
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#logFlag().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ExpressionChainsService#
  
