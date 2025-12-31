import { sentryApiRequest } from "../utils/sentry-cli.js";

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

export async function getIssue(params: GetIssueParams): Promise<IssueDetails> {
  // The Sentry CLI doesn't have a "show" command for issues,
  // so we use the REST API directly
  const issue = await sentryApiRequest<IssueDetails>(
    `issues/${params.issueId}/`
  );

  return issue;
}

export const getIssueTool = {
  name: "sentry-get-issue",
  description:
    "Get detailed information about a specific Sentry issue including metadata, occurrence count, and assignment",
  inputSchema: {
    type: "object" as const,
    properties: {
      issueId: {
        type: "string",
        description:
          "The issue ID (numeric ID or short ID like 'PROJECTNAME-123')",
      },
    },
    required: ["issueId"],
  },
};
