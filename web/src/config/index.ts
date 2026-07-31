export const appConfig = {
  title: 'AI中医健康管理平台',
  shortName: '中医商城',
  version: '1.0.0',

  // API
  apiBaseURL: '/api/v1',
  timeout: 30000,

  // Pagination
  defaultPageSize: 10,
  pageSizeOptions: [10, 20, 50, 100],

  // Cache
  tokenKey: 'token',
  adminTokenKey: 'admin_token',
  refreshTokenKey: 'refresh_token',
  langKey: 'app_lang',
  themeKey: 'app_theme',

  // Upload
  uploadMaxSize: 2 * 1024 * 1024, // 2MB
  uploadAccept: 'image/*',

  // Date
  dateFormat: 'YYYY-MM-DD',
  dateTimeFormat: 'YYYY-MM-DD HH:mm:ss',
}
