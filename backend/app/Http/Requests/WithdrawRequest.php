<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['nullable', 'exists:distributors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:bank_transfer,alipay,wechat,cash'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'alipay_account' => ['nullable', 'string', 'max:255'],
            'wechat_account' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $method = $this->input('method');

            if ($method === 'bank_transfer') {
                if (empty($this->input('bank_name'))) {
                    $validator->errors()->add('bank_name', '银行转账请提供开户银行');
                }
                if (empty($this->input('bank_account'))) {
                    $validator->errors()->add('bank_account', '银行转账请提供银行账号');
                }
                if (empty($this->input('account_name'))) {
                    $validator->errors()->add('account_name', '银行转账请提供开户姓名');
                }
            }

            if ($method === 'alipay' && empty($this->input('alipay_account'))) {
                $validator->errors()->add('alipay_account', '支付宝提现请提供支付宝账号');
            }

            if ($method === 'wechat' && empty($this->input('wechat_account'))) {
                $validator->errors()->add('wechat_account', '微信提现请提供微信账号');
            }
        });
    }
}
