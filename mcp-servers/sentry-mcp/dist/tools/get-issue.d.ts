export interface GetIssueParams {
    issueId: string;
}
export interface IssueDetails {
    id: string;
    shortId: string;
    title: string;
    culprit: string;
    permalink: string;
    level: string;
    status: string;
    platform: string;
    project: {
        id: string;
        name: string;
        slug: string;
    };
    type: string;
    metadata: {
        type?: string;
        value?: string;
        filename?: string;
        function?: string;
    };
    count: string;
    userCount: number;
    firstSeen: string;
    lastSeen: string;
    isPublic: boolean;
    isBookmarked: boolean;
    assignedTo: {
        type: string;
        id: string;
        name: string;
        email?: string;
    } | null;
    hasSeen: boolean;
    annotations: string[];
    isUnhandled: boolean;
}
export declare function getIssue(params: GetIssueParams): Promise<IssueDetails>;
export declare const getIssueTool: {
    name: string;
    description: string;
    inputSchema: {
        type: "object";
        properties: {
            issueId: {
                type: string;
                description: string;
            };
        };
        required: string[];
    };
};
//# sourceMappingURL=get-issue.d.ts.map