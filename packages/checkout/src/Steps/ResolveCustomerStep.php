<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Services\CustomerResolver;

final class ResolveCustomerStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'resolve_customer';
    }

    public function getName(): string
    {
        return 'Resolve Customer';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['validate_cart'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        // Customer can be resolved from session, authenticated user, or guest checkout
        // This step allows guest checkout by default when no customer_id is set

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $customer = $session->customer_id !== null ? $session->customer : null;
        $billingData = $session->billing_data ?? [];
        $shippingData = $session->shipping_data ?? [];
        $user = auth()->check() ? auth()->user() : null;

        if (class_exists(CustomerResolver::class) && class_exists(Customer::class)) {
            $resolver = app(CustomerResolver::class);
            $resolvedCustomer = $resolver->resolve($user, $customer, $billingData, $shippingData);

            if ($resolvedCustomer !== null) {
                $session->update(['customer_id' => $resolvedCustomer->id]);

                $this->loadCustomerDefaults($session);

                return $this->success('Customer resolved', ['customer_id' => $resolvedCustomer->id]);
            }
        }

        if ($customer !== null) {
            $session->update(['customer_id' => $customer->id]);

            $this->loadCustomerDefaults($session);

            return $this->success('Customer resolved', ['customer_id' => $customer->id]);
        }

        return $this->success('Proceeding as guest checkout');
    }

    private function loadCustomerDefaults(CheckoutSession $session): void
    {
        if (! class_exists(\AIArmada\Customers\Models\Customer::class)) {
            return;
        }

        $customer = $session->customer;

        if ($customer === null) {
            return;
        }

        // Load default addresses if available
        $billingData = $session->billing_data ?? [];
        $shippingData = $session->shipping_data ?? [];

        if (empty($billingData) && method_exists($customer, 'getDefaultBillingAddress')) {
            $address = $customer->getDefaultBillingAddress();
            if ($address !== null) {
                $billingData = $address->toArray();
            }
        }

        if (empty($shippingData) && method_exists($customer, 'getDefaultShippingAddress')) {
            $address = $customer->getDefaultShippingAddress();
            if ($address !== null) {
                $shippingData = $address->toArray();
            }
        }

        $session->update([
            'billing_data' => $billingData,
            'shipping_data' => $shippingData,
        ]);
    }
}
