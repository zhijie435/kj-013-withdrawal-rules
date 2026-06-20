import request from '@/utils/request'

export function getWithdrawalRules(params) {
  return request({
    url: '/withdrawal-rules',
    method: 'get',
    params
  })
}

export function getCurrentRule(params) {
  return request({
    url: '/withdrawal-rules/current',
    method: 'get',
    params
  })
}

export function getRuleStatusOptions() {
  return request({
    url: '/withdrawal-rules/status-options',
    method: 'get'
  })
}

export function getRuleLevelOptions() {
  return request({
    url: '/withdrawal-rules/level-options',
    method: 'get'
  })
}

export function getRuleMethodOptions() {
  return request({
    url: '/withdrawal-rules/method-options',
    method: 'get'
  })
}

export function getRuleCurrencyOptions() {
  return request({
    url: '/withdrawal-rules/currency-options',
    method: 'get'
  })
}

export function createWithdrawalRule(data) {
  return request({
    url: '/withdrawal-rules',
    method: 'post',
    data
  })
}

export function getWithdrawalRule(id) {
  return request({
    url: `/withdrawal-rules/${id}`,
    method: 'get'
  })
}

export function updateWithdrawalRule(id, data) {
  return request({
    url: `/withdrawal-rules/${id}`,
    method: 'put',
    data
  })
}

export function deleteWithdrawalRule(id) {
  return request({
    url: `/withdrawal-rules/${id}`,
    method: 'delete'
  })
}

export function toggleRuleActive(id) {
  return request({
    url: `/withdrawal-rules/${id}/toggle-active`,
    method: 'post'
  })
}
