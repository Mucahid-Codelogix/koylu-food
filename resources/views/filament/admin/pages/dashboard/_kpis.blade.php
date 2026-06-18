@php
    $deliveryProgress = $totalStopsToday > 0 ? (int) round(($deliveredStopsToday / $totalStopsToday) * 100) : 0;
@endphp

<div class="koylu-kpi-grid">
    <x-koylu.kpi
        label="Te plannen"
        :value="$newOrdersCount"
        meta="Nieuwe orders"
        :href="$this->ordersIndexUrl()"
    />

    <x-koylu.kpi
        label="Leveringen"
        :value="$deliveredStopsToday . '/' . $totalStopsToday"
        :meta="$deliveryProgress . '% voltooid'"
        :href="$this->routesTodayUrl()"
    >
        <div class="koylu-progress mt-3">
            <div class="koylu-progress-bar" style="width: {{ $deliveryProgress }}%"></div>
        </div>
    </x-koylu.kpi>

    <x-koylu.kpi
        label="Te boeken"
        :value="$conceptInvoicesCount"
        meta="Conceptfacturen"
        :href="$this->conceptInvoicesUrl()"
    />

    <x-koylu.kpi
        label="Onderweg"
        :value="$activeRoutesCount"
        :meta="$pendingStopsToday . ' stops open'"
    />
</div>
