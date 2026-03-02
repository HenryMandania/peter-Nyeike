<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_name' => $this->name,
            'contact' => $this->phone,
            'location' => $this->location,
            'registered_on' => $this->date_of_registration?->format('Y-m-d'),
            'created_by_user' => $this->creator?->name,
        ];
    }
}
