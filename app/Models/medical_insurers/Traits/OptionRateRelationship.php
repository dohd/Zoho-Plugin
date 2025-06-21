<?php

namespace App\Models\medical_insurers\Traits;

use App\Models\medical_insurers\RateVariable;

trait OptionRateRelationship
{
    public function rate_variables()
    {
        return $this->hasMany(RateVariable::class, 'option_rate_id');
    }
}
