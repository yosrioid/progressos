declare function route(name?: string, params?: any, absolute?: boolean): any;

interface ImportMeta {
  glob: (pattern: string, options?: Record<string, unknown>) => Record<string, any>;
}
