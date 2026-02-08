  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#
  class ForeachRefs
//      ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#
//      documentation
//      > ```php
//      > class ForeachRefs
//      > ```
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#iterate().
      public function iterate(array $items): void
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#iterate().
//                    documentation
//                    > ```php
//                    > public function iterate(array $items): void
//                    > ```
//                                  ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#iterate().($items)
//                                  documentation
//                                  > ```php
//                                  > array $items
//                                  > ```
      {
          foreach ($items as $key => $value) {
//                 ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#iterate().($items)
//                           ^^^^ definition local 0
//                           documentation
//                           > ```php
//                           documentation
//                           > $key: int|string
//                           documentation
//                           > ```
//                           ^^^^ reference local 0
//                           ^^^^ reference local 3
//                                   ^^^^^^ definition local 1
//                                   documentation
//                                   > ```php
//                                   documentation
//                                   > $value: mixed
//                                   documentation
//                                   > ```
//                                   ^^^^^^ reference local 1
//                                   ^^^^^^ reference local 2
              echo $key;
//                 ^^^^ reference local 0
              echo $value;
//                 ^^^^^^ reference local 1
          }
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#iterate().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ForeachRefs#
  
