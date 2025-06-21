<?php

namespace App\Models\medical_insurers\Traits;

use App\Models\medical_insurers\MedicalPlan;

trait MedicalInsurerRelationship
{
    public function plans()
    {
        return $this->HasMany(MedicalPlan::class, 'insurer_id');
    }
}
