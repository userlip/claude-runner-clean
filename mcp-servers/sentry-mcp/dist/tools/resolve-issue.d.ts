export interface ResolveIssueParams {
    issueId: string;
    org?: string;
    project?: string;
    nextRelease?: boolean;
}
export interface ResolveResult {
    success: boolean;
    issueId: string;
    status: string;
    message: string;
}
export declare function resolveIssue(params: ResolveIssueParams): Promise<ResolveResult>;
export declare const resolveIssueTool: {
    name: string;
    description: string;
    inputSchema: {
        type: "object";
        properties: {
            issueId: {
                type: string;
                description: string;
            };
            org: {
                type: string;
                description: string;
            };
            project: {
                type: string;
                description: string;
            };
            nextRelease: {
                type: string;
                description: string;
            };
        };
        required: string[];
    };
};
//# sourceMappingURL=resolve-issue.d.ts.map