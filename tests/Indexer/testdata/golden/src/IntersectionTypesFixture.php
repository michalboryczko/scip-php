  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
  use Countable;
//    ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
  use Iterator;
//    ^^^^^^^^ reference scip-php composer php 8.4.15 Iterator#
  use Stringable;
//    ^^^^^^^^^^ reference scip-php composer php 8.4.15 Stringable#
  
  /**
   * Test fixtures for intersection type tracking.
   *
   * Tests that:
   * - Intersection types produce synthetic intersection symbols
   * - Methods called on intersection-typed receivers use synthetic symbols
   * - Intersection relationships point to all constituent types
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#
  class IntersectionTypesFixture
//      ^^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#
//      documentation
//      > ```php
//      > class IntersectionTypesFixture
//      > ```
//      documentation
//      > Test fixtures for intersection type tracking.<br>Tests that:<br>- Intersection types produce synthetic intersection symbols<br>- Methods called on intersection-typed receivers use synthetic symbols<br>- Intersection relationships point to all constituent types<br>
  {
      /**
       * Method with intersection type parameter.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().
      public function acceptIntersection(Countable&Iterator $collection): void
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().
//                    documentation
//                    > ```php
//                    > public function acceptIntersection(\Countable&\Iterator $collection): void
//                    > ```
//                    documentation
//                    > Method with intersection type parameter.<br>
//                                       ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
//                                                 ^^^^^^^^ reference scip-php composer php 8.4.15 Iterator#
//                                                          ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().($collection)
//                                                          documentation
//                                                          > ```php
//                                                          > \Countable&\Iterator $collection
//                                                          > ```
//                                                          relationship scip-php composer php 8.4.15 Countable# type_definition
//                                                          relationship scip-php composer php 8.4.15 Iterator# type_definition
      {
          // Method calls on intersection type - both methods must exist
          $count = $collection->count();
//        ^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $count: mixed
//        documentation
//        > ```
//                 ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().($collection)
          $collection->rewind();
//        ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().($collection)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#acceptIntersection().
  
      /**
       * Method returning intersection type.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#returnIntersection().
      public function returnIntersection(): Countable&Stringable
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#returnIntersection().
//                    documentation
//                    > ```php
//                    > public function returnIntersection(): \Countable&\Stringable
//                    > ```
//                    documentation
//                    > Method returning intersection type.<br>
//                    relationship scip-php composer php 8.4.15 Countable# type_definition
//                    relationship scip-php composer php 8.4.15 Stringable# type_definition
//                                          ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
//                                                    ^^^^^^^^^^ reference scip-php composer php 8.4.15 Stringable#
      {
          return new CountableStringable();
//                   ^^^^^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#returnIntersection().
  
      /**
       * Intersection with custom interfaces.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().
      public function customIntersection(Loggable&Taggable $obj): void
//                    ^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().
//                    documentation
//                    > ```php
//                    > public function customIntersection(\TestData\Loggable&\TestData\Taggable $obj): void
//                    > ```
//                    documentation
//                    > Intersection with custom interfaces.<br>
//                                       ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#
//                                                ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
//                                                         ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().($obj)
//                                                         documentation
//                                                         > ```php
//                                                         > \TestData\Loggable&\TestData\Taggable $obj
//                                                         > ```
//                                                         relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable# type_definition
//                                                         relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable# type_definition
      {
          // Both log() from Loggable and getTag() from Taggable should work
          $obj->log('message');
//        ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().($obj)
          $tag = $obj->getTag();
//        ^^^^ definition local 1
//        documentation
//        > ```php
//        documentation
//        > $tag: mixed
//        documentation
//        > ```
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().($obj)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#customIntersection().
  
      /**
       * Chained method call on intersection type.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().
      public function chainedIntersectionCall(Countable&Iterator $collection): int
//                    ^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().
//                    documentation
//                    > ```php
//                    > public function chainedIntersectionCall(\Countable&\Iterator $collection): int
//                    > ```
//                    documentation
//                    > Chained method call on intersection type.<br>
//                                            ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
//                                                      ^^^^^^^^ reference scip-php composer php 8.4.15 Iterator#
//                                                               ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().($collection)
//                                                               documentation
//                                                               > ```php
//                                                               > \Countable&\Iterator $collection
//                                                               > ```
//                                                               relationship scip-php composer php 8.4.15 Countable# type_definition
//                                                               relationship scip-php composer php 8.4.15 Iterator# type_definition
      {
          // Chain on intersection-typed receiver
          $collection->rewind();
//        ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().($collection)
          return $collection->count();
//               ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().($collection)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#chainedIntersectionCall().
  
      /**
       * DNF type: union containing intersection.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#dnfType().
      public function dnfType((Countable&Iterator)|null $collection): void
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#dnfType().
//                    documentation
//                    > ```php
//                    > public function dnfType((\Countable&\Iterator)|null $collection): void
//                    > ```
//                    documentation
//                    > DNF type: union containing intersection.<br>
//                             ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
//                                       ^^^^^^^^ reference scip-php composer php 8.4.15 Iterator#
//                                                      ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#dnfType().($collection)
//                                                      documentation
//                                                      > ```php
//                                                      > (\Countable&\Iterator)|null $collection
//                                                      > ```
//                                                      relationship scip-php composer php 8.4.15 Countable# type_definition
//                                                      relationship scip-php composer php 8.4.15 Iterator# type_definition
      {
          // This is nullable intersection = union of intersection and null
          $collection?->count();
//        ^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#dnfType().($collection)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#dnfType().
  
      /**
       * Passing intersection-typed value as argument.
       */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#passIntersectionAsArgument().
      public function passIntersectionAsArgument(Loggable&Taggable $obj): void
//                    ^^^^^^^^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#passIntersectionAsArgument().
//                    documentation
//                    > ```php
//                    > public function passIntersectionAsArgument(\TestData\Loggable&\TestData\Taggable $obj): void
//                    > ```
//                    documentation
//                    > Passing intersection-typed value as argument.<br>
//                                               ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#
//                                                        ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
//                                                                 ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#passIntersectionAsArgument().($obj)
//                                                                 documentation
//                                                                 > ```php
//                                                                 > \TestData\Loggable&\TestData\Taggable $obj
//                                                                 > ```
//                                                                 relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable# type_definition
//                                                                 relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable# type_definition
      {
          $this->processTagged($obj);
//               ^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#processTagged().
//                             ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#passIntersectionAsArgument().($obj)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#passIntersectionAsArgument().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#processTagged().
      private function processTagged(Taggable $tagged): void
//                     ^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#processTagged().
//                     documentation
//                     > ```php
//                     > private function processTagged(\TestData\Taggable $tagged): void
//                     > ```
//                                   ^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
//                                            ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#processTagged().($tagged)
//                                            documentation
//                                            > ```php
//                                            > \TestData\Taggable $tagged
//                                            > ```
//                                            relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable# type_definition
      {
          // $tagged is Taggable only, but we receive a Loggable&Taggable
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#processTagged().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/IntersectionTypesFixture#
  
  /**
   * Interface for intersection type testing.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#
  interface Loggable
//          ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#
//          documentation
//          > ```php
//          > interface Loggable
//          > ```
//          documentation
//          > Interface for intersection type testing.<br>
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#log().
      public function log(string $msg): void;
//                    ^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#log().
//                    documentation
//                    > ```php
//                    > public function log(string $msg): void
//                    > ```
//                               ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#log().($msg)
//                               documentation
//                               > ```php
//                               > string $msg
//                               > ```
//                                          ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#log().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Loggable#
  
  /**
   * Interface for intersection type testing.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
  interface Taggable
//          ^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
//          documentation
//          > ```php
//          > interface Taggable
//          > ```
//          documentation
//          > Interface for intersection type testing.<br>
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#getTag().
      public function getTag(): string;
//                    ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#getTag().
//                    documentation
//                    > ```php
//                    > public function getTag(): string
//                    > ```
//                                    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#getTag().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/Taggable#
  
  /**
   * Class implementing multiple interfaces for intersection testing.
   */
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#
  class CountableStringable implements Countable, Stringable
//      ^^^^^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#
//      documentation
//      > ```php
//      > class CountableStringable implements \Countable
//      > \Stringable
//      > ```
//      documentation
//      > Class implementing multiple interfaces for intersection testing.<br>
//      relationship scip-php composer php 8.4.15 Countable# implementation
//      relationship scip-php composer php 8.4.15 Stringable# implementation
//                                     ^^^^^^^^^ reference scip-php composer php 8.4.15 Countable#
//                                                ^^^^^^^^^^ reference scip-php composer php 8.4.15 Stringable#
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#count().
      public function count(): int
//                    ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#count().
//                    documentation
//                    > ```php
//                    > public function count(): int
//                    > ```
//                    relationship scip-php composer php 8.4.15 Countable#count(). implementation reference
      {
          return 0;
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#count().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#__toString().
      public function __toString(): string
//                    ^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#__toString().
//                    documentation
//                    > ```php
//                    > public function __toString(): string
//                    > ```
//                    relationship scip-php composer php 8.4.15 Stringable#__toString(). implementation reference
      {
          return 'CountableStringable';
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#__toString().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CountableStringable#
  
