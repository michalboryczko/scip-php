  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#
  class OverrideChild extends OverrideMiddle
//      ^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#
//      documentation
//      > ```php
//      > class OverrideChild extends TestData\OverrideMiddle
//      > ```
//      relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideMiddle# reference
//                            ^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideMiddle#
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#process().
      public function process(): int
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#process().
//                    documentation
//                    > ```php
//                    > public function process(): int
//                    > ```
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideBase#process(). implementation reference
//                    relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideMiddle#process(). implementation reference
      {
          return parent::process() + 1;
//               ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideMiddle#
//                       ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideMiddle#process().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#process().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/OverrideChild#
  
