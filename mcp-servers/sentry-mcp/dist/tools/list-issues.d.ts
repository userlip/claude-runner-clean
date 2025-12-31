export interface ListIssuesParams {
    org?: string;
    project?: string;
    status?: "resolved" | "muted" | "unresolved";
    query?: string;
    maxRows?: number;
}
export interface Issue {
    issue_id: string;
    short_id: string;
    title: string;
    last_seen: string;
    status: string;
    level: string;
}
export declare function listIssues(params: ListIssuesParams): Promise<Issue[]>;
export declare const listIssuesTool: {
    name: string;
    description: string;
    inputSchema: {
        type: "object";
        properties: {
            org: {
                type: string;
                description: string;
            };
            project: {
                type: string;
                description: string;
            };
            status: {
                type: string;
                enum: string[];
                description: string;
            };
            query: {
                type: string;
                description: string;
            };
            maxRows: {
                type: string;
                description: string;
            };
        };
    };
};
//# sourceMappingURL=list-issues.d.ts.map