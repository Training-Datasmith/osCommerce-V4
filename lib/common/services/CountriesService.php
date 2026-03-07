<?php

declare(strict_types=1);

namespace common\services;

use common\models\PlatformsCountries;
use common\models\repositories\CountriesRepositiry;

class CountriesService
{
    private $countriesRepositiry;

    public function __construct(CountriesRepositiry $countriesRepositiry)
    {
        $this->countriesRepositiry = $countriesRepositiry;
    }

    public function saveCountries($array, $platformId)
    {
        $platformsCountries = PlatformsCountries::create($array, $platformId);
        $this->countriesRepositiry->save($platformsCountries);
    }

    public function deleteCountries(int $platformId)
    {
        $this->countriesRepositiry->deleteCountry($platformId);
    }
}
