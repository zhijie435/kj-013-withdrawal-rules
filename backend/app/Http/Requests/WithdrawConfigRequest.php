<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'daily_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0'],
            'fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_min' => ['nullable', 'numeric', 'min:0'],
            'fee_max' => ['nullable', 'numeric', 'min:0'],
            'processing_days' => ['nullable', 'integer', 'min:0'],
            'allow_methods' => ['nullable', 'array'],
            'allow_methods.*' => ['string', 'in:bank_transfer,alipay,wechat,cash'],
            'require_audit' => ['nullable', 'boolean'],
            'audit_threshold' => ['nullable', 'numeric', 'min:0'],
            'min_balance_keep' => ['nullable', 'numeric', 'min:0'],
            'freeze_days' => ['nullable', 'integer', 'min:0'],
            'quick_amounts' => ['nullable', 'array'],
            'quick_amounts.*' => ['numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $minAmount = $this->input('min_amount');
            $maxAmount = $this->input('max_amount');

            if ($minAmount !== null && $maxAmount !== null && $minAmount > $maxAmount) {
                $validator->errors()->add('min_amount', '最低提现金额不能大于最高提现金额');
            }

            $feeMin = $this->input('fee_min');
            $feeMax = $this->input('fee_max');

            if ($feeMin !== null && $feeMax !== null && $feeMax > 0 && $feeMin > $feeMax) {
                $validator->errors()->add('fee_min', '最低手续费不能大于最高手续费');
            }
        });
    }
}
