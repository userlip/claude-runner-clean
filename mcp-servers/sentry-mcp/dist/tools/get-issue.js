import { sentryApiRequest } from "../utils/sentry-cli.js";
export async function getIssue(params) {
    // The Sentry CLI doesn't have a "show" command for issues,
    // so we use the REST API directly
    const issue = await sentryApiRequest(`issues/${params.issueId}/`);
    return issue;
}
export const getIssueTool = {
    name: "sentry-get-issue",
    description: "Get detailed information about a specific Sentry issue including metadata, occurrence count, and assignment",
    inputSchema: {
        type: "object",
        properties: {
            issueId: {
                type: "string",
                description: "The issue ID (numeric ID or short ID like 'PROJECTNAME-123')",
            },
        },
        required: ["issueId"],
    },
};
//# sourceMappingURL=get-issue.js.map