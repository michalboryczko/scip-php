  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#
  class ClosureCapture
//      ^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#
//      documentation
//      > ```php
//      > class ClosureCapture
//      > ```
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#run().
      public function run(): void
//                    ^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#run().
//                    documentation
//                    > ```php
//                    > public function run(): void
//                    > ```
      {
          $service = new ParameterRefs();
//        ^^^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $service: scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#
//        documentation
//        > ```
//                       ^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#
          $fn = function () use ($service) {
//        ^^^ definition local 1
//        documentation
//        > ```php
//        documentation
//        > $fn: mixed
//        documentation
//        > ```
//                               ^^^^^^^^ reference local 0
              return $service->process([], 0);
//                   ^^^^^^^^ reference local 0
//                             ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().
          };
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#run().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ClosureCapture#
  
