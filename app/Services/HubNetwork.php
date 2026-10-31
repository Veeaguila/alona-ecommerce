<?php

namespace App\Services;

use App\Models\{LogisticsHub, LogisticsRoute, Parcel, ParcelHubJourney, Rider, User};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class HubNetwork
{
    public function available(): bool
    {
        foreach (['logistics_hubs', 'logistics_routes', 'parcel_hub_journeys', 'hub_transfers'] as $table) {
            if (!Schema::hasTable($table)) return false;
        }
        return Schema::hasColumn('riders', 'logistics_hub_id') && Schema::hasColumn('order_items', 'hub_route_plan');
    }

    public function requireStorage(): void
    {
        abort_unless($this->available(), 503, 'Hub operations are not available yet. Ask the ALONA administrator to complete hub setup.');
    }

    public function enforced(): bool { return (bool) config('logistics_hubs.enforce_checkout_routes'); }

    public function cityCode(string $address): ?string
    {
        return app(CheckoutServiceability::class)->forAddresses([$address])[0]['city_code'];
    }

    /** A route belongs to one company, with explicit coverage and eligible riders at both ends. */
    public function plan(string $origin, string $destination): ?array
    {
        $this->requireStorage();
        $from = $this->cityCode($origin); $to = $this->cityCode($destination);
        if (!$from || !$to) return null;
        $hubs = LogisticsHub::where('is_active', true)
            ->whereHas('sortingCenter', fn ($query) => $query->availableForEnrollment())->orderBy('id')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->get();
        foreach ($hubs as $source) {
            if (!in_array($from, $source->pickup_city_codes, true) || !$this->hasRider($source, $from)) continue;
            foreach ($hubs as $target) {
                if ((int) $source->sorting_center_id !== (int) $target->sorting_center_id
                    || !in_array($to, $target->delivery_city_codes, true) || !$this->hasRider($target, $to)) continue;
                $route = $source->id === $target->id ? null : LogisticsRoute::where('sorting_center_id', $source->sorting_center_id)
                    ->where('origin_hub_id', $source->id)->where('destination_hub_id', $target->id)->where('is_active', true)
                    ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first();
                if ($source->id !== $target->id && !$route) continue;
                return ['sorting_center_id' => $source->sorting_center_id, 'origin_city_code' => $from, 'destination_city_code' => $to,
                    'origin_hub_id' => $source->id, 'destination_hub_id' => $target->id, 'route_id' => $route?->id,
                    'transport_mode' => $route?->transport_mode ?? 'local',
                    'company_name' => $source->sortingCenter->business_name,
                    'origin_hub_name' => $source->name, 'destination_hub_name' => $target->name];
            }
        }
        return null;
    }

    private function hasRider(LogisticsHub $hub, string $city): bool
    {
        return Rider::where('sorting_center_id', $hub->sorting_center_id)->where('logistics_hub_id', $hub->id)
            ->operationallyEligible()->whereHas('areas', fn ($query) => $query->where('city_code', $city))->exists();
    }

    /** Validate before stock, voucher, order or payment mutation. Plans are server-derived. */
    public function checkoutPlans($items, string $destination, string $method): array
    {
        if (!$this->enforced() || $method === 'pickup') return [];
        $this->requireStorage();
        $plans = [];
        foreach ($items->groupBy(fn ($item) => $item->product?->seller_id) as $sellerId => $group) {
            $seller = $group->first()->product?->seller;
            $plan = $seller ? $this->plan((string) $seller->address, $destination) : null;
            if (!$plan) throw ValidationException::withMessages(['shipping_address' => 'No verified pickup-to-delivery hub route is available for every seller in this order. Choose another address or contact the seller.']);
            $plans[$sellerId] = $plan;
        }
        return $plans;
    }

    public function checkoutCoverage($items, array $addresses): array
    {
        $results = app(CheckoutServiceability::class)->forAddresses($addresses);
        if (!$this->enforced()) return $results;
        foreach ($addresses as $id => $address) {
            try {
                $plans = $this->checkoutPlans($items, $address, 'standard');
                $results[$id] = ['state' => 'supported', 'message' => 'Pickup and delivery are available through a verified hub route.',
                    'couriers' => array_values(array_unique(array_column($plans, 'company_name')))];
            } catch (ValidationException $error) {
                $results[$id] = ['state' => 'unsupported', 'message' => $error->errors()['shipping_address'][0]];
            }
        }
        return $results;
    }

    public function attach(Parcel $parcel, array $plan): ParcelHubJourney
    {
        $this->requireStorage();
        // The immutable booking snapshot is produced by checkout, never accepted from the client.
        $hubs = LogisticsHub::where('sorting_center_id', $plan['sorting_center_id'])
            ->whereIn('id', [$plan['origin_hub_id'], $plan['destination_hub_id']])->lockForUpdate()->get()->keyBy('id');
        abort_unless(isset($hubs[$plan['origin_hub_id']], $hubs[$plan['destination_hub_id']]), 422, 'The booked company hub route needs administrator review.');
        $origin = $this->cityCode((string) $parcel->collection_address);
        $destination = $this->cityCode((string) $parcel->delivery_address);
        abort_unless($origin === $plan['origin_city_code'] && $destination === $plan['destination_city_code'], 422,
            'The pickup or delivery city differs from the booked hub route. Ask Logistics to review the booking.');
        return ParcelHubJourney::create(['parcel_id' => $parcel->id, 'sorting_center_id' => $plan['sorting_center_id'],
            'origin_hub_id' => $plan['origin_hub_id'], 'destination_hub_id' => $plan['destination_hub_id'], 'route_snapshot' => $plan]);
    }

    public function journey(Parcel $parcel): ?ParcelHubJourney
    {
        return $this->available() ? ParcelHubJourney::where('parcel_id', $parcel->id)->first() : null;
    }

    public function assertRider(Parcel $parcel, Rider $rider, string $leg): void
    {
        $journey = $this->journey($parcel);
        if (!$journey) return; // Legacy assignments are preserved; no physical hub is guessed.
        $hub = $leg === 'pickup' ? $journey->origin_hub_id : $journey->destination_hub_id;
        abort_unless((int) $rider->sorting_center_id === (int) $journey->sorting_center_id && (int) $rider->logistics_hub_id === (int) $hub,
            422, 'Choose a Rider assigned to the correct pickup or delivery hub.');
        if ($leg === 'delivery') abort_unless($journey->destination_received_at && (int) $journey->current_hub_id === (int) $hub,
            422, 'The destination hub must physically receive this parcel before delivery assignment.');
    }

    public function receiveOrigin(Parcel $parcel, ?int $hubId, User $actor): void
    {
        $journey = $this->journey($parcel);
        if (!$journey) return;
        $journey = ParcelHubJourney::whereKey($journey->id)->lockForUpdate()->firstOrFail();
        abort_unless($hubId && $hubId === (int) $journey->origin_hub_id, 422, 'Confirm arrival at the booked origin hub.');
        abort_if($journey->origin_received_at, 409, 'This parcel has already been received at its origin hub.');
        $local = (int) $journey->origin_hub_id === (int) $journey->destination_hub_id;
        $journey->update(['current_hub_id' => $hubId, 'origin_received_at' => now(), 'destination_received_at' => $local ? now() : null]);
        app(ParcelTrackingTimeline::class)->record($parcel, 'Received at origin hub: '.$journey->route_snapshot['origin_hub_name'].'.');
        app(LogisticsAudit::class)->record($actor, 'hub_origin_received', $parcel, ['hub_id' => $hubId, 'journey_id' => $journey->id]);
    }
}
