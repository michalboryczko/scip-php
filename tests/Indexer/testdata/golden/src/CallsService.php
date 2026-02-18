  <?php
  
  declare(strict_types=1);
  
  namespace TestData;
  
//⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#
  class CallsService
//      ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#
//      documentation
//      > ```php
//      > class CallsService
//      > ```
  {
      private CallsRepository $repo;
//            ^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
//                            ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                            documentation
//                            > ```php
//                            > private \TestData\CallsRepository $repo
//                            > ```
//                            relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository# type_definition
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#__construct().
      public function __construct(CallsRepository $repo)
//                    ^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#__construct().
//                    documentation
//                    > ```php
//                    > public function __construct(\TestData\CallsRepository $repo)
//                    > ```
//                                ^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
//                                                ^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#__construct().($repo)
//                                                documentation
//                                                > ```php
//                                                > \TestData\CallsRepository $repo
//                                                > ```
//                                                relationship scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository# type_definition
      {
          $this->repo = $repo;
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                      ^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#__construct().($repo)
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#__construct().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#process().
      public function process(object $order): void
//                    ^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#process().
//                    documentation
//                    > ```php
//                    > public function process(object $order): void
//                    > ```
//                                   ^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#process().($order)
//                                   documentation
//                                   > ```php
//                                   > object $order
//                                   > ```
      {
          // Method call with arguments
          $this->repo->save($order, true);
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                     ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
//                          ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#process().($order)
  
          // Method call with zero arguments
          $items = $this->repo->findAll();
//        ^^^^^^ definition local 0
//        documentation
//        > ```php
//        documentation
//        > $items: array
//        documentation
//        > ```
//                        ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                              ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
  
          // Static call
          $newRepo = CallsRepository::create('test');
//        ^^^^^^^^ definition local 1
//        documentation
//        > ```php
//        documentation
//        > $newRepo: mixed
//        documentation
//        > ```
//                   ^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
//                                    ^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#create().
  
          // Constructor call (new)
          $anotherRepo = new CallsRepository();
//        ^^^^^^^^^^^^ definition local 2
//        documentation
//        > ```php
//        documentation
//        > $anotherRepo: scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
//        documentation
//        > ```
//                           ^^^^^^^^^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#process().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#chainedCalls().
      public function chainedCalls(): void
//                    ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#chainedCalls().
//                    documentation
//                    > ```php
//                    > public function chainedCalls(): void
//                    > ```
      {
          // Not truly chained (no fluent interface), but two separate calls
          $this->repo->findAll();
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                     ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
          $this->repo->save(new \stdClass());
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                     ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
//                              ^^^^^^^^^ reference scip-php composer php 8.4.15 stdClass#
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#chainedCalls().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#namedArgs().
      public function namedArgs(): void
//                    ^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#namedArgs().
//                    documentation
//                    > ```php
//                    > public function namedArgs(): void
//                    > ```
      {
          // Named argument (PHP 8)
          $this->repo->save(flush: true, entity: new \stdClass());
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                     ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#save().
//                                                   ^^^^^^^^^ reference scip-php composer php 8.4.15 stdClass#
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#namedArgs().
  
//    ⌄ enclosing_range_start scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#nullsafeCall().
      public function nullsafeCall(): void
//                    ^^^^^^^^^^^^ definition scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#nullsafeCall().
//                    documentation
//                    > ```php
//                    > public function nullsafeCall(): void
//                    > ```
      {
          // Nullsafe method call (uses $this->repo which has known type)
          $this->repo?->findAll();
//               ^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#$repo.
//                      ^^^^^^^ reference scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsRepository#findAll().
      }
//    ⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#nullsafeCall().
  }
//⌃ enclosing_range_end scip-php composer davidrjenni/scip-php-test 2879a47ba00225b1d0cf31ebe8b9fc7f6cd28be5 TestData/CallsService#
  
