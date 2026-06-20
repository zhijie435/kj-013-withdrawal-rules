<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isUpdate() ? 'sometimes|required' : 'required';

        return [
            'order_id' => ['nullable', 'exists:orders,id'],
            'distributor_id' => ['nullable', 'exists:distributors,id'],
            'type' => [$required, 'in:escrow_deposit,escrow_release,platform_fee,refund,recharge'],
            'method' => [$required, 'in:cash,bank_transfer,alipay,wechat,credit,other'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_date' => [$required, 'date'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,completed,failed'],
            'remark' => ['nullable', 'string'],
            'fail_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function isUpdate(): bool
    {
        return in_array($this->method(), ['PUT', 'PATCH']);
    }
}
