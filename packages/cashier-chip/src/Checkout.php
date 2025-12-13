<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;
use ReturnTypeWillChange;
use Symfony\Component\HttpFoundation\Response;

/**
 * CHIP Checkout wrapper class.
 *
 * Creates CHIP purchases that redirect the customer to CHIP's checkout page.
 */
class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * The owner of the checkout session.
     */
    protected ?Model $owner;

    /**
     * The CHIP purchase instance.
     */
    protected PurchaseData $purchase;

    /**
     * Create a new checkout instance.
     */
    public function __construct(?Model $owner, PurchaseData $purchase)
    {
        $this->owner = $owner;
        $this->purchase = $purchase;
    }

    /**
     * Dynamically get values from the purchase.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->purchase->{$key} ?? null;
    }

    /**
     * Begin a new guest checkout session.
     */
    public static function guest(): CheckoutBuilder
    {
        return new CheckoutBuilder;
    }

    /**
     * Begin a new customer checkout session.
     *
     * @param  Model  $owner
     */
    public static function customer($owner): CheckoutBuilder
    {
        return new CheckoutBuilder($owner);
    }

    /**
     * Create a new checkout session.
     *
     * @param  Model|null  $owner
     * @param  int  $amount  Amount in cents
     * @param  array<string, mixed>  $options
     */
    public static function create($owner, int $amount, array $options = []): self
    {
        $builder = Cashier::chip()->purchase()
            ->currency($options['currency'] ?? config('cashier-chip.currency', 'MYR'));

        // Add products
        if (isset($options['products'])) {
            foreach ($options['products'] as $product) {
                $builder->addProduct(
                    $product['name'],
                    $product['price'],
                    $product['quantity'] ?? 1
                );
            }
        } else {
            $builder->addProduct(
                $options['reference'] ?? 'Payment',
                $amount
            );
        }

        // Add client information if owner exists
        if ($owner) {
            if (method_exists($owner, 'chipId') && $owner->chipId()) {
                $builder->clientId($owner->chipId());
            } else {
                $builder->customer(
                    email: $owner->email ?? '',
                    fullName: $owner->name ?? null,
                    phone: $owner->phone ?? null
                );
            }
        }

        // Add redirect URLs
        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['cancel_url'])) {
            $builder->failureUrl($options['cancel_url']);
        }

        if (isset($options['webhook_url'])) {
            $builder->webhook($options['webhook_url']);
        }

        // Configure receipt
        if (isset($options['send_receipt'])) {
            $builder->sendReceipt($options['send_receipt']);
        }

        // Add recurring token request if specified
        if ($options['recurring'] ?? false) {
            $builder->forceRecurring(true);
        }

        // Add reference
        if (isset($options['reference'])) {
            $builder->reference($options['reference']);
        }

        // Merge any additional metadata
        if (isset($options['metadata'])) {
            $builder->metadata($options['metadata']);
        }

        $purchase = $builder->create();

        return new static($owner, $purchase);
    }

    /**
     * Get the checkout URL.
     */
    public function url(): ?string
    {
        return $this->purchase->getCheckoutUrl();
    }

    /**
     * Get the purchase ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->url() ?? '', 303);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return $this->redirect();
    }

    /**
     * Get the owner of the checkout session.
     *
     * @return Model|null
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the underlying CHIP purchase.
     */
    public function asChipPurchase(): PurchaseData
    {
        return $this->purchase;
    }

    /**
     * Convert to a Payment instance.
     */
    public function asPayment(): Payment
    {
        return new Payment($this->purchase);
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->purchase->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
