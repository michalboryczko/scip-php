  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#
  class ParameterRefs
//      ^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#
//      documentation
//      > ```php
//      > class ParameterRefs
//      > ```
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().
      public function process(array $items, int $count): array
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().
//                    documentation
//                    > ```php
//                    > public function process(array $items, int $count): array
//                    > ```
//                                  ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().($items)
//                                  documentation
//                                  > ```php
//                                  > array $items
//                                  > ```
//                                              ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().($count)
//                                              documentation
//                                              > ```php
//                                              > int $count
//                                              > ```
      {
          $result = [];
//        ^^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $result: mixed
//        documentation
//        > ```
          foreach ($items as $item) {
//                 ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().($items)
//                           ^^^^^ definition local 1
//                           documentation
//                           > ```php
//                           documentation
//                           > $item: mixed
//                           documentation
//                           > ```
//                           ^^^^^ reference local 1
//                           ^^^^^ reference local 1
              $result[] = $item;
//            ^^^^^^^ reference local 0
//                        ^^^^^ reference local 1
          }
          return array_slice($result, 0, $count);
//               ^^^^^^^^^^^ reference scip-php composer php 8.4.15 array_slice().
//                           ^^^^^^^ reference local 0
//                                       ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().($count)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#process().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/ParameterRefs#
  
