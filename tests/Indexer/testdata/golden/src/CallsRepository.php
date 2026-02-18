  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
  class CallsRepository
//      ^^^^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
//      documentation
//      > ```php
//      > class CallsRepository
//      > ```
  {
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
      public function save(object $entity, bool $flush = false): void
//                    ^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
//                    documentation
//                    > ```php
//                    > public function save(object $entity, bool $flush = false): void
//                    > ```
//                                ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().($entity)
//                                documentation
//                                > ```php
//                                > object $entity
//                                > ```
//                                              ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().($flush)
//                                              documentation
//                                              > ```php
//                                              > bool $flush = false
//                                              > ```
      {
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
  
      /** @return list<object> */
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
      public function findAll(): array
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
//                    documentation
//                    > ```php
//                    > public function findAll(): array
//                    > ```
//                    documentation
//                    > @return list<object>
      {
          return [];
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#create().
      public static function create(string $name): self
//                           ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#create().
//                           documentation
//                           > ```php
//                           > public static function create(string $name): self
//                           > ```
//                                         ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#create().($name)
//                                         documentation
//                                         > ```php
//                                         > string $name
//                                         > ```
//                                                 ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
      {
          return new self();
//                   ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#create().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
  
