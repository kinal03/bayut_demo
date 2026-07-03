<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::disableQueryLog();
        DB::beginTransaction();

        try {
            $filePath = public_path('location.json');

            if (!File::exists($filePath)) {
                throw new \Exception('location.json file not found');
            }

            $json = File::get($filePath);
            $countries = json_decode($json, true);

            if (!$countries) {
                throw new \Exception('Invalid JSON format');
            }

            $now = Carbon::now();
            foreach ($countries as $country) {

                /*
                |---------------------------------------------------
                | COUNTRY UPSERT (avoid duplicate)
                |---------------------------------------------------
                */
                $countryId = DB::table('countries')->updateOrInsert(
                    ['iso_code' => $country['iso2'] ?? null],
                    [
                        'name'        => $country['name'],
                        'iso3'        => $country['iso3'],
                        'phone_code'  => str_replace('+', '', $country['phone_code']),
                        'currency'    => $country['currency'] ?? null,
                        'currency_symbol'  => $country['currency_symbol'],
                        'timezones'   => $country['timezones'][0]['zoneName'],
                        'status'      => 'active',
                        'flag'        => 'flgs/' . strtolower($country['iso2'] ?? '') . '.png',
                        'is_default'  => ($country['iso2'] === 'IN') ? 1 : 0,
                        'updated_at'  => $now,
                        'created_at'  => $now,
                    ]
                );

                // fetch id properly
                $countryId = DB::table('countries')->where('iso_code', $country['iso2'] ?? null)->value('id');

                /*
                |---------------------------------------------------
                | STATES
                |---------------------------------------------------
                */
                if (!empty($country['states'])) {
                    foreach ($country['states'] as $state) {
                        $stateId = DB::table('states')->updateOrInsert(
                            [
                                'name'       => $state['name'],
                                'country_id' => $countryId
                            ],
                            [
                                'state_code' => $state['state_code'],
                                'status'     => 'active',
                                'is_default' => 0,
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );

                        $stateId = DB::table('states')
                            ->where('name', $state['name'])
                            ->where('country_id', $countryId)
                            ->value('id');

                        /*
                        |---------------------------------------------------
                        | CITIES BULK INSERT
                        |---------------------------------------------------
                        */
                        if (!empty($state['cities'])) {
                            $citiesData = [];
                            foreach ($state['cities'] as $city) {
                                $citiesData[] = [
                                    'name'       => $city['name'],
                                    'state_id'   => $stateId,
                                    'country_id' => $countryId,
                                    'status'     => 'active',
                                    'is_default' => 0,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }

                            foreach (array_chunk($citiesData, 500) as $chunk) {
                                DB::table('cities')->insertOrIgnore($chunk);
                            }
                        }
                    }
                }
            }

            DB::commit();

            $this->call(CountryPhoneLengthSeeder::class);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}