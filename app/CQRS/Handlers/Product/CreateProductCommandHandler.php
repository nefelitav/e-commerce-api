<?php

namespace App\CQRS\Handlers\Product;

use App\CQRS\Commands\CommandInterface;
use App\CQRS\Commands\Product\CreateProductCommand;
use App\CQRS\Handlers\CommandHandlerInterface;
use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductAlreadyExistsException;
use App\Services\Product\ProductServiceInterface;

/**
 * @implements CommandHandlerInterface<CreateProductCommand, Product>
 */
readonly class CreateProductCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ProductServiceInterface $productService,
    ) {}

    /**
     * @param CreateProductCommand $command
     * @throws ProductAlreadyExistsException
     */
    public function handle(CommandInterface $command): Product
    {
        $unpersistedProduct = new UnpersistedProduct(
            name:        $command->name,
            description: $command->description,
            price:       $command->price,
            quantity:    $command->quantity,
            categoryId:  $command->categoryId,
        );

        return $this->productService->createProduct($unpersistedProduct);
    }
}



