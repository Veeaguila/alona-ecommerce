<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class RiderParcelDetails
{
    public function append(Collection $parcels, bool $pickup): void
    {
        $relation = $pickup ? 'seller' : 'buyer';
        $columns = $pickup
            ? 'id,name,contact_no,address,street_address,barangay,municipality,province,address_geography'
            : 'id,name,contact_no';
        $parcels->load($relation.':'.$columns);
        foreach ($parcels as $parcel) {
            $person = $parcel->getRelation($relation);
            $details = ['name' => $person?->name, 'contact_no' => $person?->contact_no,
                'house_number' => null, 'street' => null, 'barangay' => null,
                'city' => null, 'province' => null, 'postal_code' => null, 'instructions' => null];
            if ($pickup) {
                $profileAddress = $person?->address ?: implode(', ', array_filter([
                    $person?->street_address, $person?->barangay, $person?->municipality, $person?->province,
                ]));
                $collection = trim((string) $parcel->collection_address);
                $details['complete_address'] = $collection ?: ($profileAddress ?: null);
                // A seller's current home/profile must not overwrite a different collection location.
                $normalize = fn ($value) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
                if (! $collection || ($profileAddress && $normalize($collection) === $normalize($profileAddress))) {
                    $details = array_merge($details, ['street' => $person?->street_address,
                        'barangay' => $person?->barangay, 'city' => $person?->municipality,
                        'province' => $person?->province,
                        'postal_code' => data_get($person?->address_geography, 'postal_code')]);
                }
            } else {
                $details = array_merge($details, ['complete_address' => $parcel->delivery_address,
                    'house_number' => $parcel->delivery_house_number, 'street' => $parcel->delivery_street,
                    'barangay' => $parcel->delivery_barangay, 'city' => $parcel->delivery_municipality,
                    'province' => $parcel->delivery_province]);
            }
            $parcel->setAttribute('address_details', $details);
            // Return only operational fields, never the entire account model.
            $parcel->unsetRelation($relation);
        }
    }
}
