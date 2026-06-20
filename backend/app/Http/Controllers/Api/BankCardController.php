<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankCard;
use Illuminate\Http\Request;

class BankCardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-bank-cards');

        $query = BankCard::with('user')
            ->when(!empty($request->input('user_id')), function ($q) use ($request) {
                $q->where('user_id', $request->input('user_id'));
            })
            ->when(!empty($request->input('keyword')), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('card_holder_name', 'like', "%{$request->input('keyword')}%")
                        ->orWhere('card_number', 'like', "%{$request->input('keyword')}%")
                        ->orWhere('bank_name', 'like', "%{$request->input('keyword')}%")
                        ->orWhereHas('user', function ($q) use ($request) {
                            $q->where('name', 'like', "%{$request->input('keyword')}%");
                        });
                });
            })
            ->when(!empty($request->input('card_type')), function ($q) use ($request) {
                $q->where('card_type', $request->input('card_type'));
            })
            ->when(isset($request->input('is_active')) && $request->input('is_active') !== '', function ($q) use ($request) {
                $q->where('is_active', $request->input('is_active'));
            })
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');

        if (!$request->user()->can('manage-bank-cards')) {
            $query->where('user_id', $request->user()->id);
        }

        $cards = $query->paginate($request->input('per_page', 15), ['*'], 'page', $request->input('page', 1));

        return $this->respondPaginated($cards);
    }

    public function allActive(Request $request)
    {
        $this->authorize('view-bank-cards');

        $query = BankCard::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');

        if (!$request->user()->can('manage-bank-cards')) {
            $query->where('user_id', $request->user()->id);
        }

        $cards = $query->get(['id', 'bank_name', 'card_number', 'card_type', 'card_holder_name', 'is_default', 'currency']);

        return $this->respond($cards);
    }

    public function getTypeOptions()
    {
        $this->authorize('view-bank-cards');

        return $this->respond(BankCard::getTypeOptions());
    }

    public function getBankOptions()
    {
        $this->authorize('view-bank-cards');

        return $this->respond(BankCard::getBankOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('manage-bank-cards');

        $validated = $request->validate([
            'card_type' => 'required|string|max:30',
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:50',
            'branch_name' => 'nullable|string|max:100',
            'card_number' => 'required|string|max:50',
            'card_holder_name' => 'required|string|max:100',
            'currency' => 'nullable|string|max:10',
            'province' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:50',
            'iban' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
            'remark' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->can('manage-bank-cards') && $request->has('user_id')
            ? $request->input('user_id')
            : $request->user()->id;

        $card = BankCard::create(array_merge($validated, [
            'user_id' => $userId,
            'is_active' => true,
        ]));

        return $this->respondCreated($card, '银行卡创建成功');
    }

    public function show(BankCard $card)
    {
        $this->authorize('view-bank-cards');

        if (!$request->user()->can('manage-bank-cards') && $card->user_id !== $request->user()->id) {
            abort(403, '无权查看该银行卡');
        }

        return $this->respond($card->load('user'));
    }

    public function update(Request $request, BankCard $card)
    {
        $this->authorize('manage-bank-cards');

        if (!$request->user()->can('manage-bank-cards') && $card->user_id !== $request->user()->id) {
            abort(403, '无权编辑该银行卡');
        }

        $validated = $request->validate([
            'card_type' => 'sometimes|string|max:30',
            'bank_name' => 'sometimes|string|max:100',
            'bank_code' => 'sometimes|nullable|string|max:50',
            'branch_name' => 'sometimes|nullable|string|max:100',
            'card_number' => 'sometimes|string|max:50',
            'card_holder_name' => 'sometimes|string|max:100',
            'currency' => 'sometimes|nullable|string|max:10',
            'province' => 'sometimes|nullable|string|max:50',
            'city' => 'sometimes|nullable|string|max:50',
            'swift_code' => 'sometimes|nullable|string|max:50',
            'iban' => 'sometimes|nullable|string|max:50',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
            'remark' => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($validated['is_verified']) && $validated['is_verified']) {
            $validated['verified_at'] = now();
        }

        $card->update($validated);

        return $this->respond($card, '银行卡更新成功');
    }

    public function destroy(BankCard $card, Request $request)
    {
        $this->authorize('manage-bank-cards');

        if (!$request->user()->can('manage-bank-cards') && $card->user_id !== $request->user()->id) {
            abort(403, '无权删除该银行卡');
        }

        if ($card->withdrawals()->exists()) {
            return $this->respondError('该银行卡下存在提现记录，无法删除', 42200, 422);
        }

        $card->delete();

        return $this->respond(null, '银行卡删除成功');
    }

    public function setDefault(BankCard $card, Request $request)
    {
        $this->authorize('manage-bank-cards');

        if (!$request->user()->can('manage-bank-cards') && $card->user_id !== $request->user()->id) {
            abort(403, '无权操作该银行卡');
        }

        if (!$card->is_active) {
            return $this->respondError('无法将已禁用的银行卡设为默认', 42200, 422);
        }

        $card->update(['is_default' => true]);

        return $this->respond($card, '已设为默认银行卡');
    }
}
