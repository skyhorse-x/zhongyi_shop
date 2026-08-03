/**
 * 路由总入口
 *
 * 路由按业务模块拆分在 ./modules/ 下，本文件仅负责合并与实例化。
 * 模块化后业务可独立维护，不再因单文件超大导致冲突。
 */
import { createRouter, createWebHistory } from 'vue-router'

import commonRoutes from './modules/common'
import analysisRoutes from './modules/analysis'
import userRoutes from './modules/user'
import adminRoutes from './modules/admin'

const routes = [
  ...commonRoutes,
  ...userRoutes,
  ...analysisRoutes,
  ...adminRoutes,
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
