<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'order_id' => $this->order_id,
            'distributor_id' => $this->distributor_id,
            'created_by' => $this->created_by,
            'type' => $this->type,
            'type_label' => $this->getTypeEnum()?->label(),
            'method' => $this->method,
            'amount' => $this->amount,
            'fee_amount' => $this->fee_amount,
            'currency' => $this->currency,
            'payment_date' => $this->payment_date,
            'transaction_no' => $this->transaction_no,
            'status' => $this->status,
            'status_label' => $this->getStatusEnum()?->label(),
            'remark' => $this->remark,
            'fail_reason' => $this->fail_reason,
            'can_retry' => $this->canRetry(),
            'is_insufficient_balance' => $this->isInsufficientBalance(),
            'order' => new OrderResource($this->whenLoaded('order')),
            'distributor' => $this->whenLoaded('distributor', function () {
                return [
                    'id' => $this->distributor->id,
                    'name' => $this->distributor->name,
                    'balance' => $this->distributor->balance,
                ];
            }),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
