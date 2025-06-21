<?php

namespace App\Models\medical_insurers\Traits;

use App\Models\medical_insurers\MedicalInsurer;
use App\Models\medical_insurers\MedicalPlan;
use App\Models\medical_insurers\RateVariable;

trait PlanOptionRelationship
{
    public function medical_insurer()
    {
        return $this->belongsTo(MedicalInsurer::class, 'insurer_id');
    }

    public function medical_plan()
    {
        return $this->belongsTo(MedicalPlan::class, 'plan_id');
    }

    public function rate_variables()
    {
        return $this->hasMany(RateVariable::class, 'plan_option_id');
    }
}
