export interface ListEventsParams {
    org?: string;
    project?: string;
    issueId?: string;
    showUser?: boolean;
    showTags?: boolean;
    maxRows?: number;
}
export interface Event {
    event_id: string;
    message?: string;
    timestamp?: string;
    user?: string;
    tags?: string;
    level?: string;
    platform?: string;
}
export interface EventFromApi {
    eventID: string;
    id: string;
    message?: string;
    title?: string;
    culprit?: string;
    dateCreated: string;
    dateReceived: string;
    user?: {
        id?: string;
        email?: string;
        username?: string;
        ipAddress?: string;
    };
    tags: Array<{
        key: string;
        value: string;
    }>;
    context?: Record<string, unknown>;
    contexts?: Record<string, unknown>;
    sdk?: {
        name: string;
        version: string;
    };
    platform?: string;
}
export declare function listEvents(params: ListEventsParams): Promise<Event[]>;
export declare const listEventsTool: {
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
            issueId: {
                type: string;
                description: string;
            };
            showUser: {
                type: string;
                description: string;
            };
            showTags: {
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
//# sourceMappingURL=list-events.d.ts.map