import { runSentryCli, sentryApiRequest } from "../utils/sentry-cli.js";

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

export async function resolveIssue(
  params: ResolveIssueParams
): Promise<ResolveResult> {
  // Try CLI first for single issue resolution
  const args = ["issues", "resolve", "-i", params.issueId];

  if (params.nextRelease) {
    args.push("-n");
  }

  try {
    await runSentryCli(args, {
      org: params.org,
      project: params.project,
    });

    return {
      success: true,
      issueId: params.issueId,
      status: "resolved",
      message: `Issue ${params.issueId} has been resolved`,
    };
  } catch {
    // Fallback to API if CLI fails
    try {
      interface UpdateResponse {
        id: string;
        status: string;
      }

      const response = await sentryApiRequest<UpdateResponse>(
        `issues/${params.issueId}/`,
        {
          method: "PUT",
          body: {
            status: "resolved",
            statusDetails: params.nextRelease
              ? { inNextRelease: true }
              : {},
          },
        }
      );

      return {
        success: true,
        issueId: params.issueId,
        status: response.status,
        message: `Issue ${params.issueId} has been resolved via API`,
      };
    } catch (apiError) {
      return {
        success: false,
        issueId: params.issueId,
        status: "error",
        message:
          apiError instanceof Error
            ? apiError.message
            : "Unknown error occurred",
      };
    }
  }
}

export const resolveIssueTool = {
  name: "sentry-resolve-issue",
  description:
    "Mark a Sentry issue as resolved. Can optionally mark as resolved in next release.",
  inputSchema: {
    type: "object" as const,
    properties: {
      issueId: {
        type: "string",
        description:
          "The issue ID (numeric ID or short ID like 'PROJECTNAME-123')",
      },
      org: {
        type: "string",
        description: "Organization slug (defaults to configured org)",
      },
      project: {
        type: "string",
        description: "Project slug",
      },
      nextRelease: {
        type: "boolean",
        description:
          "Mark as resolved in the next release instead of immediately",
      },
    },
    required: ["issueId"],
  },
};
