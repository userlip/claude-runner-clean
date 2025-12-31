export interface ProjectStatsParams {
    org: string;
    project: string;
    stat?: "received" | "rejected" | "blacklisted" | "generated";
    resolution?: "10s" | "1h" | "1d";
    since?: number;
    until?: number;
}
export interface StatsPoint {
    timestamp: number;
    count: number;
}
export interface ProjectStats {
    project: string;
    stat: string;
    resolution: string;
    data: StatsPoint[];
    total: number;
    start: string;
    end: string;
}
export declare function getProjectStats(params: ProjectStatsParams): Promise<ProjectStats>;
export declare const projectStatsTool: {
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
            stat: {
                type: string;
                enum: string[];
                description: string;
            };
            resolution: {
                type: string;
                enum: string[];
                description: string;
            };
            since: {
                type: string;
                description: string;
            };
            until: {
                type: string;
                description: string;
            };
        };
        required: string[];
    };
};
//# sourceMappingURL=project-stats.d.ts.map