import request from '@/utils/request'

export function getUsers(params) {
  return request({
    url: '/users',
    method: 'get',
    params
  })
}

export function getUserRoleOptions() {
  return request({
    url: '/users/role-options',
    method: 'get'
  })
}

export function getUserLevelOptions() {
  return request({
    url: '/users/level-options',
    method: 'get'
  })
}

export function createUser(data) {
  return request({
    url: '/users',
    method: 'post',
    data
  })
}

export function getUser(id) {
  return request({
    url: `/users/${id}`,
    method: 'get'
  })
}

export function updateUser(id, data) {
  return request({
    url: `/users/${id}`,
    method: 'put',
    data
  })
}

export function deleteUser(id) {
  return request({
    url: `/users/${id}`,
    method: 'delete'
  })
}
