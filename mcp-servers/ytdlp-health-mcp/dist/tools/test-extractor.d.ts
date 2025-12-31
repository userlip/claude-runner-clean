export interface TestExtractorParams {
    url: string;
    timeout?: number;
}
export interface TestExtractorResult {
    success: boolean;
    extractor: string | null;
    title: string | null;
    duration: number | null;
    formats: number;
    error: string | null;
    executionTimeMs: number;
}
export declare function testExtractor(params: TestExtractorParams): Promise<TestExtractorResult>;
//# sourceMappingURL=test-extractor.d.ts.map