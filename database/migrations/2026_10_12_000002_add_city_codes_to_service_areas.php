<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->string('city_code', 10)->nullable()->index();
            $table->unique(['sorting_center_id', 'city_code']);
        });

        // Preserve every operational area and pivot. Only unambiguous city names
        // can be linked automatically; ambiguous/custom names need explicit selection.
        $normalize = fn ($name) => mb_strtolower(trim(preg_replace('/(^city of\s+|\s+city$)/iu', '', trim($name))));
        $cities = json_decode(file_get_contents(resource_path('data/psgc.json')), true, 512, JSON_THROW_ON_ERROR)['cities'];
        $names = [];
        foreach ($cities as $city) {
            $names[$normalize($city['name'])][] = $city['code'];
        }
        $used = [];
        foreach (DB::table('areas')->orderBy('id')->get() as $area) {
            $matches = $names[$normalize($area->name)] ?? [];
            if (count($matches) !== 1) {
                continue;
            }
            $key = $area->sorting_center_id.':'.$matches[0];
            if (isset($used[$key])) {
                continue;
            }
            DB::table('areas')->where('id', $area->id)->update(['city_code' => $matches[0]]);
            $used[$key] = true;
        }
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['sorting_center_id', 'city_code']);
            $table->dropIndex(['city_code']);
            $table->dropColumn('city_code');
        });
    }
};
