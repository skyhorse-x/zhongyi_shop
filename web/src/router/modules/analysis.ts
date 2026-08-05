/**
 * 分析与健康路由：舌诊/面诊/体质测试/健康档案
 */
import type { RouteRecordRaw } from 'vue-router'

const analysisRoutes: RouteRecordRaw[] = [
  // 舌诊分析
  {
    path: '/analysis/tongue',
    name: 'TongueAnalysis',
    component: () => import('@/views/analysis/tongue.vue'),
    meta: { title: '舌诊分析', needAuth: true },
  },
  {
    path: '/analysis/face',
    name: 'FaceAnalysis',
    component: () => import('@/views/analysis/face.vue'),
    meta: { title: '面诊分析', needAuth: true },
  },
  // 手相分析
  {
    path: '/analysis/palm',
    name: 'PalmAnalysis',
    component: () => import('@/views/analysis/palm.vue'),
    meta: { title: '手相分析', needAuth: true },
  },
  {
    path: '/analysis/result/:taskNo',
    name: 'AnalysisResult',
    component: () => import('@/views/analysis/result.vue'),
    meta: { title: '分析结果', needAuth: true },
  },
  // 体质测试
  {
    path: '/constitution/test',
    name: 'ConstitutionTest',
    component: () => import('@/views/constitution/test.vue'),
    meta: { title: '体质测试', needAuth: true },
  },
  {
    path: '/constitution/result/:taskNo',
    name: 'ConstitutionResult',
    component: () => import('@/views/constitution/result.vue'),
    meta: { title: '体质报告', needAuth: true },
  },
  // 健康档案
  {
    path: '/health/history',
    name: 'HealthHistory',
    component: () => import('@/views/health/history.vue'),
    meta: { title: '分析历史', needAuth: true },
  },
  {
    path: '/health/trend',
    name: 'HealthTrend',
    component: () => import('@/views/health/trend.vue'),
    meta: { title: '健康趋势', needAuth: true },
  },
  {
    path: '/health/constitution',
    name: 'HealthConstitution',
    component: () => import('@/views/health/constitution.vue'),
    meta: { title: '体质档案', needAuth: true },
  },
]

export default analysisRoutes
