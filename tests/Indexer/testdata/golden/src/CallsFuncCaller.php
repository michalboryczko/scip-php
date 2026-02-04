  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#
  class CallsFuncCaller
//      ^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#
//      documentation
//      > ```php
//      > class CallsFuncCaller
//      > ```
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#callFunction().
      public function callFunction(): void
//                    ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#callFunction().
//                    documentation
//                    > ```php
//                    > public function callFunction(): void
//                    > ```
      {
          // Function call (fully qualified for proper resolution)
          $result = \TestData\callsHelperFunction('test');
//        ^^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $result: mixed
//        documentation
//        > ```
//                  ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/callsHelperFunction().
  
          // Built-in function call
          $len = strlen('hello');
//        ^^^^ definition local 1
//        documentation
//        > ```php
//        documentation
//        > $len: mixed
//        documentation
//        > ```
//               ^^^^^^ reference scip-php composer php 8.4.15 strlen().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#callFunction().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsFuncCaller#
  
