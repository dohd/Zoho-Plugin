<?php

namespace App\Models\medical_insurers\Traits;

trait MedicalInsurerAttribute
{
    /**
     * Action Button Attribute to show in grid
     * @return string
     */
    public function getActionButtonsAttribute()
    {
        return $this->getButtonWrapperAttribute(null,
            // $this->getViewButtonAttribute('attendances.show', 'view-attendance'),
            $this->getEditButtonAttribute('attendances.edit', 'edit-attendance'),
            $this->getDeleteButtonAttribute('attendances.destroy', 'delete-attendance'),
        );
    }
}
