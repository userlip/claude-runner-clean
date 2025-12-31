import { runSentryCli, parseCliTable, sentryApiRequest } from "../utils/sentry-cli.js";
export async function listEvents(params) {
    // If issueId is provided, use the API to get events for that specific issue
    if (params.issueId) {
        const events = await sentryApiRequest(`issues/${params.issueId}/events/`);
        return events.slice(0, params.maxRows || 25).map((event) => ({
            event_id: event.eventID,
            message: event.message || event.title || "",
            timestamp: event.dateCreated,
            user: event.user
                ? event.user.email || event.user.username || event.user.id || "anonymous"
                : undefined,
            tags: event.tags ? event.tags.map((t) => `${t.key}:${t.value}`).join(", ") : undefined,
            level: undefined,
            platform: event.platform,
        }));
    }
    // Otherwise use the CLI to list all events
    const args = ["events", "list"];
    if (params.showUser) {
        args.push("-U");
    }
    if (params.showTags) {
        args.push("-T");
    }
    if (params.maxRows) {
        args.push("--max-rows", String(params.maxRows));
    }
    const output = await runSentryCli(args, {
        org: params.org,
        project: params.project,
    });
    const parsed = parseCliTable(output);
    return parsed.map((row) => ({
        event_id: row.event_id || row.id || "",
        message: row.message || row.title || "",
        timestamp: row.timestamp || row.date || "",
        user: row.user,
        tags: row.tags,
        level: row.level,
        platform: row.platform,
    }));
}
export const listEventsTool = {
    name: "sentry-list-events",
    description: "List events/occurrences, optionally filtered to a specific issue. Shows when errors occurred and includes user/tag information.",
    inputSchema: {
        type: "object",
        properties: {
            org: {
                type: "string",
                description: "Organization slug (defaults to configured org)",
            },
            project: {
                type: "string",
                description: "Project slug to filter events",
            },
            issueId: {
                type: "string",
                description: "Issue ID to list events for (if not provided, lists all recent events)",
            },
            showUser: {
                type: "boolean",
                description: "Include user information in the output",
            },
            showTags: {
                type: "boolean",
                description: "Include tag information in the output",
            },
            maxRows: {
                type: "number",
                description: "Maximum number of events to return (default: 25)",
            },
        },
    },
};
//# sourceMappingURL=list-events.js.map