#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

import { listIssues, listIssuesTool, ListIssuesParams } from "./tools/list-issues.js";
import { getIssue, getIssueTool, GetIssueParams } from "./tools/get-issue.js";
import { listEvents, listEventsTool, ListEventsParams } from "./tools/list-events.js";
import {
  getProjectStats,
  projectStatsTool,
  ProjectStatsParams,
} from "./tools/project-stats.js";
import {
  resolveIssue,
  resolveIssueTool,
  ResolveIssueParams,
} from "./tools/resolve-issue.js";

const server = new Server(
  {
    name: "sentry-mcp",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// List available tools
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      listIssuesTool,
      getIssueTool,
      listEventsTool,
      projectStatsTool,
      resolveIssueTool,
    ],
  };
});

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case "sentry-list-issues": {
        const params = args as unknown as ListIssuesParams;
        const issues = await listIssues(params);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(issues, null, 2),
            },
          ],
        };
      }

      case "sentry-get-issue": {
        const params = args as unknown as GetIssueParams;
        const issue = await getIssue(params);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(issue, null, 2),
            },
          ],
        };
      }

      case "sentry-list-events": {
        const params = args as unknown as ListEventsParams;
        const events = await listEvents(params);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(events, null, 2),
            },
          ],
        };
      }

      case "sentry-project-stats": {
        const params = args as unknown as ProjectStatsParams;
        const stats = await getProjectStats(params);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(stats, null, 2),
            },
          ],
        };
      }

      case "sentry-resolve-issue": {
        const params = args as unknown as ResolveIssueParams;
        const result = await resolveIssue(params);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(result, null, 2),
            },
          ],
        };
      }

      default:
        return {
          content: [
            {
              type: "text",
              text: `Unknown tool: ${name}`,
            },
          ],
          isError: true,
        };
    }
  } catch (error) {
    const errorMessage =
      error instanceof Error ? error.message : "Unknown error occurred";
    return {
      content: [
        {
          type: "text",
          text: `Error executing ${name}: ${errorMessage}`,
        },
      ],
      isError: true,
    };
  }
});

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Sentry MCP server running on stdio");
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
