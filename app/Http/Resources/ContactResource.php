<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'service' => $this->service,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'sla_deadline' => $this->sla_deadline,
            'handled_by' => $this->handled_by,
            'request_timestamp' => $this->request_timestamp,
            'updation_timestamp' => $this->updation_timestamp,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}