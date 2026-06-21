<?php

namespace Tests\Unit;

use App\Exceptions\WithdrawalRule\InvalidWithdrawalAmountException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleConflictException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleInUseException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleNotFoundException;
use Tests\TestCase;

class WithdrawalRuleExceptionsTest extends TestCase
{
    public function test_withdrawal_rule_exception_base_properties(): void
    {
        $exception = new WithdrawalRuleException('测试异常消息', ['key' => 'value']);

        $this->assertEquals('测试异常消息', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_ERROR', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals(['key' => 'value'], $exception->getDetails());
    }

    public function test_withdrawal_rule_exception_custom_http_code(): void
    {
        $exception = new WithdrawalRuleException('自定义状态码', [], 400);

        $this->assertEquals(400, $exception->getHttpCode());
    }

    public function test_not_found_exception_by_id(): void
    {
        $exception = WithdrawalRuleNotFoundException::byId(123);

        $this->assertEquals('提现规则不存在', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(404, $exception->getHttpCode());
        $this->assertEquals(['id' => 123], $exception->getDetails());
    }

    public function test_not_found_exception_for_dimensions(): void
    {
        $exception = WithdrawalRuleNotFoundException::for('vip', 'CNY', 'bank_transfer');

        $this->assertStringContainsString('vip', $exception->getMessage());
        $this->assertStringContainsString('CNY', $exception->getMessage());
        $this->assertStringContainsString('bank_transfer', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals([
            'user_level' => 'vip',
            'currency' => 'CNY',
            'method' => 'bank_transfer',
        ], $exception->getDetails());
    }

    public function test_not_found_exception_renders_json_response(): void
    {
        $exception = WithdrawalRuleNotFoundException::byId(999);

        $response = $exception->render();

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('WITHDRAWAL_RULE_NOT_FOUND', $data['error_code']);
    }

    public function test_conflict_exception_code_exists(): void
    {
        $exception = WithdrawalRuleConflictException::codeExists('DUPLICATE_CODE');

        $this->assertStringContainsString('DUPLICATE_CODE', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_CONFLICT', $exception->getErrorCode());
        $this->assertEquals(409, $exception->getHttpCode());
        $this->assertEquals(['code' => 'DUPLICATE_CODE'], $exception->getDetails());
    }

    public function test_conflict_exception_rule_exists(): void
    {
        $exception = WithdrawalRuleConflictException::ruleExists('vip', 'CNY', 'alipay');

        $this->assertStringContainsString('vip', $exception->getMessage());
        $this->assertStringContainsString('CNY', $exception->getMessage());
        $this->assertStringContainsString('alipay', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_CONFLICT', $exception->getErrorCode());
        $this->assertEquals(409, $exception->getHttpCode());
        $this->assertEquals([
            'user_level' => 'vip',
            'currency' => 'CNY',
            'method' => 'alipay',
        ], $exception->getDetails());
    }

    public function test_conflict_exception_renders_with_409_status(): void
    {
        $exception = WithdrawalRuleConflictException::codeExists('TEST');

        $response = $exception->render();

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function test_in_use_exception_has_withdrawals(): void
    {
        $exception = WithdrawalRuleInUseException::hasWithdrawals(5, 10);

        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_RULE_IN_USE', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals([
            'rule_id' => 5,
            'withdrawal_count' => 10,
        ], $exception->getDetails());
    }

    public function test_invalid_amount_below_minimum(): void
    {
        $exception = InvalidWithdrawalAmountException::belowMinimum(50, 100, 'CNY');

        $this->assertStringContainsString('100', $exception->getMessage());
        $this->assertStringContainsString('CNY', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_AMOUNT_INVALID', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals([
            'amount' => 50,
            'min_amount' => 100,
            'currency' => 'CNY',
            'type' => 'below_minimum',
        ], $exception->getDetails());
    }

    public function test_invalid_amount_above_maximum(): void
    {
        $exception = InvalidWithdrawalAmountException::aboveMaximum(60000, 50000, 'USD');

        $this->assertStringContainsString('50000', $exception->getMessage());
        $this->assertStringContainsString('USD', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_AMOUNT_INVALID', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals([
            'amount' => 60000,
            'max_amount' => 50000,
            'currency' => 'USD',
            'type' => 'above_maximum',
        ], $exception->getDetails());
    }

    public function test_invalid_amount_zero_actual(): void
    {
        $exception = InvalidWithdrawalAmountException::zeroActualAmount();

        $this->assertEquals('实际到账金额必须大于0', $exception->getMessage());
        $this->assertEquals('WITHDRAWAL_AMOUNT_INVALID', $exception->getErrorCode());
        $this->assertEquals(['type' => 'zero_actual'], $exception->getDetails());
    }

    public function test_all_exception_extend_base_exception(): void
    {
        $exceptions = [
            WithdrawalRuleNotFoundException::byId(1),
            WithdrawalRuleConflictException::codeExists('X'),
            WithdrawalRuleInUseException::hasWithdrawals(1, 1),
            InvalidWithdrawalAmountException::belowMinimum(1, 2, 'CNY'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(\App\Exceptions\BaseException::class, $exception);
        }
    }

    public function test_all_exceptions_have_render_method(): void
    {
        $exceptions = [
            WithdrawalRuleNotFoundException::byId(1),
            WithdrawalRuleConflictException::codeExists('X'),
            WithdrawalRuleInUseException::hasWithdrawals(1, 1),
            InvalidWithdrawalAmountException::belowMinimum(1, 2, 'CNY'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertTrue(method_exists($exception, 'render'));
            $response = $exception->render();
            $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        }
    }

    public function test_exception_render_returns_consistent_json_structure(): void
    {
        $exception = WithdrawalRuleNotFoundException::byId(1);
        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertArrayHasKey('details', $data);
        $this->assertFalse($data['success']);
    }
}
