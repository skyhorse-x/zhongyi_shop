/// <reference types="vite/client" />

interface ImportMetaEnv {
  /**
   * 应用标题
   */
  readonly VITE_APP_TITLE: string

  /**
   * 应用简称
   */
  readonly VITE_APP_SHORT_NAME: string

  /**
   * API 基础路径
   */
  readonly VITE_API_BASE_URL: string

  /**
   * API 请求超时（毫秒）
   */
  readonly VITE_API_TIMEOUT: string

  /**
   * 开发环境 Vite 代理目标后端地址
   */
  readonly VITE_PROXY_TARGET?: string

  /**
   * 上传文件最大体积（字节）
   */
  readonly VITE_UPLOAD_MAX_SIZE: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
