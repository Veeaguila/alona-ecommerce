<?php

namespace App\Services;

use App\Models\Area;
use App\Models\SortingCenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DeliveryCoverageService
{
    public function __construct(private PhilippineAddressService $addresses) {}

    public static function normalizeCityName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/(^city of\s+|\s+city$)/iu', '', trim($name))));
    }

    public function cities(array $codes): array
    {
        $index = array_column($this->addresses->data()['cities'], null, 'code');
        $cities = [];
        foreach ($codes as $code) {
            if (! isset($index[$code])) {
                throw ValidationException::withMessages(['coverage_city_codes' => 'Choose valid Philippine cities or municipalities.']);
            }
            $cities[] = $index[$code];
        }

        return $cities;
    }

    public function directory(): array
    {
        $data = $this->addresses->data();
        $cities = array_column($data['cities'], null, 'code');
        $regions = array_column($data['regions'], 'name', 'code');
        $provinces = array_column($data['provinces'], 'name', 'code');
        $coverage = [];
        $areas = Area::whereNotNull('city_code')->whereHas('sortingCenter', fn ($center) => $center->availableForEnrollment())
            ->with('sortingCenter:id,business_name')->get();
        foreach ($areas as $area) {
            $city = $cities[$area->city_code] ?? null;
            if (! $city) {
                continue;
            }
            if (! isset($coverage[$city['code']])) {
                $coverage[$city['code']] = ['code' => $city['code'], 'name' => $city['name'],
                    'label' => implode(' · ', array_filter([$city['name'], $provinces[$city['province_code']] ?? null, $regions[$city['region_code']]])),
                    'partners' => []];
            }
            $coverage[$city['code']]['partners'][] = ['id' => $area->sorting_center_id, 'business_name' => $area->sortingCenter->business_name];
        }
        usort($coverage, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return array_values($coverage);
    }

    public function enrollmentAreas(array $codes, int $centerId, bool $lock = false): Collection
    {
        $centerQuery = SortingCenter::whereKey($centerId);
        if ($lock) {
            $centerQuery->lockForUpdate();
        }
        $center = $centerQuery->first();
        $userQuery = $center?->user();
        if ($lock) {
            $userQuery?->lockForUpdate();
        }
        $user = $userQuery?->first();
        if (! $center || $center->approval_status !== 'approved' || ! $user || $user->usertype !== 'sorting_center' || $user->status !== 'approved'
            || ($user->partnerApplication()->exists() && ! $user->hasVerifiedEmail())) {
            throw ValidationException::withMessages(['sorting_center_id' => 'Choose an approved Logistics Partner serving your selected areas.']);
        }
        $query = $center->areas()->whereIn('city_code', $codes);
        if ($lock) {
            $query->lockForUpdate();
        }
        $areas = $query->get();
        if (! $codes || $areas->count() !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['delivery_city_codes' => 'This Logistics Partner does not serve all selected delivery areas. Please choose available areas and a matching partner.']);
        }

        return $areas;
    }

    public function addCities(SortingCenter $center, array $codes): Collection
    {
        $center->newQuery()->whereKey($center->id)->lockForUpdate()->firstOrFail();
        $areas = new Collection;
        foreach ($this->cities($codes) as $city) {
            $area = $center->areas()->where('city_code', $city['code'])->first();
            if (! $area) {
                // An explicit geographic choice may confirm a legacy name. Never
                // overwrite a previously mapped area or detach existing riders/parcels.
                $legacy = $center->areas()->whereNull('city_code')->get()->filter(fn ($row) => self::normalizeCityName($row->name) === self::normalizeCityName($city['name']));
                if ($legacy->count() === 1) {
                    $area = $legacy->first();
                    $area->update(['city_code' => $city['code']]);
                } else {
                    $name = $city['name'];
                    if ($center->areas()->where('name', $name)->exists()) {
                        $name .= ' ['.$city['code'].']';
                    }
                    $area = $center->areas()->create(['name' => $name, 'city_code' => $city['code']]);
                }
            }
            $areas->push($area);
        }

        return $areas;
    }
}
