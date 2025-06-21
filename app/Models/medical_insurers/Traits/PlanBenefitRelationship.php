<?php

namespace App\Models\medical_insurers\Traits;

use App\Models\medical_insurers\MedicalPlan;

trait PlanBenefitRelationship
{
    public function medical_plan()
    {
        return $this->belongsTo(MedicalPlan::class, 'plan_id');
    }
}
