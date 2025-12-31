export interface SentryCliOptions {
    org?: string;
    project?: string;
}
/**
 * Execute a sentry-cli command and return the output
 */
export declare function runSentryCli(args: string[], options?: SentryCliOptions): Promise<string>;
/**
 * Parse table output from sentry-cli into structured data
 */
export declare function parseCliTable(output: string): Record<string, string>[];
/**
 * Get Sentry API base URL and token from configuration
 */
export declare function getSentryConfig(): Promise<{
    url: string;
    token: string;
}>;
/**
 * Make a request to the Sentry API
 */
export declare function sentryApiRequest<T>(endpoint: string, options?: {
    method?: string;
    body?: unknown;
}): Promise<T>;
//# sourceMappingURL=sentry-cli.d.ts.map