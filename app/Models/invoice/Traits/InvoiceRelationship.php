<?php

namespace App\Models\invoice\Traits;

use App\Models\invoice\InvoiceItem;
use App\Models\stockadj\Stockadj;

trait InvoiceRelationship
{
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockAdj()
    {
        return $this->hasOne(Stockadj::class);
    }
}
