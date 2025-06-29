<?php

namespace App\Models\invoice\Traits;

use App\Models\invoice\InvoiceItem;

trait InvoiceRelationship
{
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
