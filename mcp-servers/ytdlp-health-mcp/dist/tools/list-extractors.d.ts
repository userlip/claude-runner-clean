export interface ListExtractorsParams {
    filter?: string;
    limit?: number;
}
export interface ExtractorInfo {
    name: string;
    description?: string;
}
export interface ListExtractorsResult {
    extractors: ExtractorInfo[];
    total: number;
    filtered: number;
}
export declare function listExtractors(params: ListExtractorsParams): Promise<ListExtractorsResult>;
//# sourceMappingURL=list-extractors.d.ts.map