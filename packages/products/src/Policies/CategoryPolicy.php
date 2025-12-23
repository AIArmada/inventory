<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class CategoryPolicy
{
    use HandlesAuthorization;

    private function canAccessCategory(Category $category): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($category);
        }

        if ($this->belongsToOwner($category, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($category);
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

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Category $category): bool
    {
        return $this->canAccessCategory($category);
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Category $category): bool
    {
        return $this->canAccessCategory($category);
    }

    public function delete($user, Category $category): bool
    {
        // Prevent deletion if category has products
        if ($category->products()->exists()) {
            return false;
        }

        return $this->canAccessCategory($category);
    }
}
