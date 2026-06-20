import request from '@/utils/request'

export function getBalance(currency = 'CNY') {
  return request({
    url: '/wallet/balance',
    method: 'get',
    params: { currency }
  })
}

export function getTransactions(params) {
  return request({
    url: '/wallet/transactions',
    method: 'get',
    params
  })
}

export function transfer(data) {
  return request({
    url: '/wallet/transfer',
    method: 'post',
    data
  })
}
