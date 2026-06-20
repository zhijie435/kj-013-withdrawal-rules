import request from '@/utils/request'

export function getWithdrawals(params) {
  return request({
    url: '/withdrawals',
    method: 'get',
    params
  })
}

export function getWithdrawalStatistics(params) {
  return request({
    url: '/withdrawals/statistics',
    method: 'get',
    params
  })
}

export function getWithdrawalStatusOptions() {
  return request({
    url: '/withdrawals/status-options',
    method: 'get'
  })
}

export function calculateWithdrawalFee(params) {
  return request({
    url: '/withdrawals/calculate-fee',
    method: 'get',
    params
  })
}

export function applyWithdrawal(data) {
  return request({
    url: '/withdrawals/apply',
    method: 'post',
    data
  })
}

export function getWithdrawal(id) {
  return request({
    url: `/withdrawals/${id}`,
    method: 'get'
  })
}

export function approveWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/approve`,
    method: 'post',
    data
  })
}

export function rejectWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/reject`,
    method: 'post',
    data
  })
}

export function processWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/process`,
    method: 'post',
    data
  })
}

export function completeWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/complete`,
    method: 'post',
    data
  })
}

export function failWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/fail`,
    method: 'post',
    data
  })
}

export function cancelWithdrawal(id, data) {
  return request({
    url: `/withdrawals/${id}/cancel`,
    method: 'post',
    data
  })
}

export function batchApproveWithdrawals(data) {
  return request({
    url: '/withdrawals/batch-approve',
    method: 'post',
    data
  })
}

export function batchProcessWithdrawals(data) {
  return request({
    url: '/withdrawals/batch-process',
    method: 'post',
    data
  })
}
