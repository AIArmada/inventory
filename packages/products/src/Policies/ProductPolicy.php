<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy
{
    use HandlesAuthorization;

    private function canAccessProduct(Product $product): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($product);
        }

        if ($this->belongsToOwner($product, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($product);
    }

    private function belongsToOwner(Model $model, Model $owner): bool
    {
        if (! method_exists($model, 'belongsToOwner')) {
            return false;
        }

        /** @var bool $belongs */
        $belongs = $model->belongsToOwner($owner);

        return $belongs;
    }

    private function isGlobalModel(Model $model): bool
    {
        if (! method_exists($model, 'isGlobal')) {
            return false;
        }

        /** @var bool $isGlobal */
        $isGlobal = $model->isGlobal();

        return $isGlobal;
    }

    /**
     * Determine whether the user can view any products.
     */
    public function viewAny($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view($user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can create products.
     */
    public function create($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update($user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete($user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can duplicate the product.
     */
    public function duplicate($user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
