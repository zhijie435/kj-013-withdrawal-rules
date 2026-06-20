import request from '@/utils/request'

export function getBankCards(params) {
  return request({
    url: '/bank-cards',
    method: 'get',
    params
  })
}

export function getActiveBankCards() {
  return request({
    url: '/bank-cards/all-active',
    method: 'get'
  })
}

export function getBankCardTypeOptions() {
  return request({
    url: '/bank-cards/type-options',
    method: 'get'
  })
}

export function getBankOptions() {
  return request({
    url: '/bank-cards/bank-options',
    method: 'get'
  })
}

export function createBankCard(data) {
  return request({
    url: '/bank-cards',
    method: 'post',
    data
  })
}

export function getBankCard(id) {
  return request({
    url: `/bank-cards/${id}`,
    method: 'get'
  })
}

export function updateBankCard(id, data) {
  return request({
    url: `/bank-cards/${id}`,
    method: 'put',
    data
  })
}

export function deleteBankCard(id) {
  return request({
    url: `/bank-cards/${id}`,
    method: 'delete'
  })
}

export function setDefaultBankCard(id) {
  return request({
    url: `/bank-cards/${id}/set-default`,
    method: 'post'
  })
}
