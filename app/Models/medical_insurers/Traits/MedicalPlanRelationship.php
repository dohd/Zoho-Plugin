<?php

namespace App\Models\medical_insurers\Traits;

use App\Models\medical_insurers\MedicalInsurer;
use App\Models\medical_insurers\OptionRate;
use App\Models\medical_insurers\PlanBenefit;
use App\Models\medical_insurers\PlanOption;
use App\Models\medical_insurers\SharedRate;

trait MedicalPlanRelationship
{
    public function medical_insurer()
    {
        return $this->belongsTo(MedicalInsurer::class, 'insurer_id');
    }

    public function plan_options()
    {
        return $this->hasMany(PlanOption::class, 'plan_id');
    }

    public function option_rates()
    {
        return $this->hasMany(OptionRate::class, 'plan_id');
    }

    public function shared_rates()
    {
        return $this->hasMany(SharedRate::class, 'plan_id');
    }

    public function plan_benefits()
    {
        return $this->hasMany(PlanBenefit::class, 'plan_id');
    }
}
