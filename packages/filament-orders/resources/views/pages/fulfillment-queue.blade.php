<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Orders Ready for Fulfillment
            </x-slot>

            <x-slot name="description">
                Manage orders that are ready to be shipped. Ship individual orders or use bulk actions for efficiency.
            </x-slot>

            <div class="mt-4">
                <div
                    x-data="{ __filamentOrdersIsIntersecting: true }"
                    x-init="
                        const update = () => {
                            $wire.set('isTableVisible', __filamentOrdersIsIntersecting && document.visibilityState === 'visible')
                        }

                        const observer = new IntersectionObserver((entries) => {
                            __filamentOrdersIsIntersecting = entries?.[0]?.isIntersecting ?? true
                            update()
                        }, { threshold: 0.01 })

                        observer.observe($el)
                        document.addEventListener('visibilitychange', update)
                        update()
                    "
                >
                    {{ $this->table }}
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>