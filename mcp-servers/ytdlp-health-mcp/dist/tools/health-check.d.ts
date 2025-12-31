import { TestExtractorResult } from "./test-extractor.js";
export interface HealthCheckParams {
    extractors?: string[];
    timeout?: number;
    parallel?: boolean;
}
export interface ExtractorHealthResult extends TestExtractorResult {
    name: string;
    url: string;
}
export interface HealthCheckResult {
    timestamp: string;
    totalTested: number;
    passed: number;
    failed: number;
    results: ExtractorHealthResult[];
    summary: {
        healthy: string[];
        unhealthy: string[];
    };
}
export declare function healthCheck(params: HealthCheckParams): Promise<HealthCheckResult>;
export declare function getSampleUrls(): Record<string, string>;
export declare function getDefaultExtractors(): string[];
//# sourceMappingURL=health-check.d.ts.map