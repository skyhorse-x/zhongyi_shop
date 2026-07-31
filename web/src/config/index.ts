export const appConfig = {
  title: import.meta.env.VITE_APP_TITLE || 'AI中医健康管理平台',
  shortName: import.meta.env.VITE_APP_SHORT_NAME || '中医商城',
  version: '1.0.0',

  // API
  apiBaseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: Number(import.meta.env.VITE_API_TIMEOUT) || 30000,

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
  uploadMaxSize: Number(import.meta.env.VITE_UPLOAD_MAX_SIZE) || 2 * 1024 * 1024, // 2MB
  uploadAccept: 'image/*',

  // Date
  dateFormat: 'YYYY-MM-DD',
  dateTimeFormat: 'YYYY-MM-DD HH:mm:ss',
}
