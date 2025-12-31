import { runSentryCli, parseCliTable } from "../utils/sentry-cli.js";

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

export async function listIssues(params: ListIssuesParams): Promise<Issue[]> {
  const args = ["issues", "list"];

  if (params.status) {
    args.push("-s", params.status);
  }

  if (params.query) {
    args.push("--query", params.query);
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
    issue_id: row.issue_id || "",
    short_id: row.short_id || "",
    title: row.title || "",
    last_seen: row.last_seen || "",
    status: row.status || "",
    level: row.level || "",
  }));
}

export const listIssuesTool = {
  name: "sentry-list-issues",
  description:
    "List recent issues/errors from Sentry with optional filters for project, status, and search query",
  inputSchema: {
    type: "object" as const,
    properties: {
      org: {
        type: "string",
        description: "Organization slug (defaults to configured org)",
      },
      project: {
        type: "string",
        description: "Project slug to filter issues",
      },
      status: {
        type: "string",
        enum: ["resolved", "muted", "unresolved"],
        description: "Filter by issue status",
      },
      query: {
        type: "string",
        description:
          'Search query using Sentry search syntax (e.g., "is:unresolved browser:Chrome")',
      },
      maxRows: {
        type: "number",
        description: "Maximum number of issues to return (default: 25)",
      },
    },
  },
};
