#!/usr/bin/env node
/**
 * Google Analytics MCP Proxy Server
 *
 * Proxies tool calls to the upstream `analytics-mcp` Python server with
 * multi-account support. Each configured account has its own service account
 * credentials file, and tool calls include an `account` parameter to select
 * which credentials to use.
 *
 * Environment variables:
 * - GA_ACCOUNT_<NAME>_CREDENTIALS_PATH: Path to service account JSON file
 * - GA_ANALYTICS_MCP_PATH: Path to analytics-mcp binary (default: ~/.local/bin/analytics-mcp)
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
  type Tool,
} from "@modelcontextprotocol/sdk/types.js";
import { spawn } from "child_process";
import fs from "fs";
import path from "path";
import os from "os";

interface AccountConfig {
  name: string;
  credentialsPath: string;
}

// Parse account configurations from environment variables
function getAccountConfigs(): AccountConfig[] {
  const configs: AccountConfig[] = [];
  const accountNames = new Set<string>();

  for (const key of Object.keys(process.env)) {
    const match = key.match(/^GA_ACCOUNT_([A-Z0-9_]+)_CREDENTIALS_PATH$/);
    if (match) {
      accountNames.add(match[1]);
    }
  }

  for (const name of accountNames) {
    const credentialsPath = process.env[`GA_ACCOUNT_${name}_CREDENTIALS_PATH`];
    if (credentialsPath && fs.existsSync(credentialsPath)) {
      configs.push({
        name: name.toLowerCase().replace(/_/g, "-"),
        credentialsPath,
      });
    }
  }

  return configs;
}

// Get the path to the analytics-mcp binary
function getAnalyticsMcpPath(): string {
  return (
    process.env.GA_ANALYTICS_MCP_PATH ||
    path.join(os.homedir(), ".local", "bin", "analytics-mcp")
  );
}

// Create an MCP client connected to the upstream analytics-mcp for a specific account
async function createUpstreamClient(
  accountConfig: AccountConfig
): Promise<Client> {
  const mcpPath = getAnalyticsMcpPath();

  const transport = new StdioClientTransport({
    command: mcpPath,
    env: {
      ...process.env,
      GOOGLE_APPLICATION_CREDENTIALS: accountConfig.credentialsPath,
    },
  });

  const client = new Client(
    {
      name: "google-analytics-mcp-proxy",
      version: "1.0.0",
    },
    {
      capabilities: {},
    }
  );

  await client.connect(transport);
  return client;
}

// Cache for upstream clients keyed by account name
const clientCache = new Map<string, Client>();

async function getOrCreateClient(
  accountConfig: AccountConfig
): Promise<Client> {
  const cached = clientCache.get(accountConfig.name);
  if (cached) {
    return cached;
  }

  const client = await createUpstreamClient(accountConfig);
  clientCache.set(accountConfig.name, client);
  return client;
}

// Resolve which account to use
function resolveAccount(
  configs: AccountConfig[],
  accountName?: string
): AccountConfig {
  if (!accountName) {
    if (configs.length === 1) {
      return configs[0];
    }
    throw new Error(
      `Multiple accounts configured. Please specify an 'account' parameter. Available: ${configs.map((c) => c.name).join(", ")}`
    );
  }

  const normalized = accountName.toLowerCase().replace(/_/g, "-");
  const config = configs.find((c) => c.name === normalized);
  if (!config) {
    throw new Error(
      `Account not found: ${accountName}. Available: ${configs.map((c) => c.name).join(", ")}`
    );
  }
  return config;
}

// Build the tool list - upstream tools with an added `account` parameter + list-accounts tool
function getTools(accountConfigs: AccountConfig[]): Tool[] {
  const accountList =
    accountConfigs.map((a) => a.name).join(", ") || "none configured";
  const accountParam = {
    type: "string" as const,
    description: `Account name to use (${accountList}). ${accountConfigs.length === 1 ? "Optional - defaults to the only configured account." : "Required when multiple accounts are configured."}`,
  };

  return [
    {
      name: "ga-list-accounts",
      description: `List all configured Google Analytics accounts. Currently available: ${accountList}`,
      inputSchema: {
        type: "object" as const,
        properties: {},
        required: [],
      },
    },
    {
      name: "ga-get-account-summaries",
      description:
        "Retrieves information about the Google Analytics accounts and properties accessible by the specified account's credentials.",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
        },
        required: accountConfigs.length > 1 ? ["account"] : [],
      },
    },
    {
      name: "ga-get-property-details",
      description:
        "Returns details about a GA4 property including name, time zone, currency, and industry category.",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
          property_id: {
            type: "string" as const,
            description:
              'The Google Analytics property ID. Accepted formats: a number or "properties/" followed by a number.',
          },
        },
        required: accountConfigs.length > 1
          ? ["account", "property_id"]
          : ["property_id"],
      },
    },
    {
      name: "ga-run-report",
      description:
        "Runs a Google Analytics Data API report with dimensions, metrics, date ranges, filters, and ordering. Use snake_case for field names (protobuf format).",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
          property_id: {
            type: "string" as const,
            description: "The Google Analytics property ID.",
          },
          date_ranges: {
            type: "array" as const,
            description:
              'List of date ranges. Each object has start_date, end_date, and optional name. Supports relative dates like "yesterday", "today", "30daysAgo".',
            items: {
              type: "object" as const,
              properties: {
                start_date: { type: "string" as const },
                end_date: { type: "string" as const },
                name: { type: "string" as const },
              },
              required: ["start_date", "end_date"],
            },
          },
          dimensions: {
            type: "array" as const,
            description:
              "List of dimension names (e.g. 'date', 'country', 'pagePath').",
            items: { type: "string" as const },
          },
          metrics: {
            type: "array" as const,
            description:
              "List of metric names (e.g. 'activeUsers', 'sessions', 'screenPageViews').",
            items: { type: "string" as const },
          },
          dimension_filter: {
            type: "object" as const,
            description: "FilterExpression for dimensions.",
          },
          metric_filter: {
            type: "object" as const,
            description: "FilterExpression for metrics.",
          },
          order_bys: {
            type: "array" as const,
            description: "List of OrderBy objects.",
            items: { type: "object" as const },
          },
          limit: {
            type: "number" as const,
            description: "Maximum rows to return (max 250000).",
          },
          offset: {
            type: "number" as const,
            description: "Row offset for pagination.",
          },
          currency_code: {
            type: "string" as const,
            description: "ISO4217 currency code (e.g. USD, EUR).",
          },
          return_property_quota: {
            type: "boolean" as const,
            description: "Whether to return property quota info.",
          },
        },
        required: accountConfigs.length > 1
          ? ["account", "property_id", "date_ranges", "dimensions", "metrics"]
          : ["property_id", "date_ranges", "dimensions", "metrics"],
      },
    },
    {
      name: "ga-run-realtime-report",
      description:
        "Runs a Google Analytics realtime report with dimensions and metrics.",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
          property_id: {
            type: "string" as const,
            description: "The Google Analytics property ID.",
          },
          dimensions: {
            type: "array" as const,
            description: "List of realtime dimension names.",
            items: { type: "string" as const },
          },
          metrics: {
            type: "array" as const,
            description: "List of realtime metric names.",
            items: { type: "string" as const },
          },
          dimension_filter: {
            type: "object" as const,
            description: "FilterExpression for dimensions.",
          },
          metric_filter: {
            type: "object" as const,
            description: "FilterExpression for metrics.",
          },
          order_bys: {
            type: "array" as const,
            description: "List of OrderBy objects.",
            items: { type: "object" as const },
          },
          limit: {
            type: "number" as const,
            description: "Maximum rows to return.",
          },
          offset: {
            type: "number" as const,
            description: "Row offset for pagination.",
          },
          return_property_quota: {
            type: "boolean" as const,
            description: "Whether to return realtime property quota.",
          },
        },
        required: accountConfigs.length > 1
          ? ["account", "property_id", "dimensions", "metrics"]
          : ["property_id", "dimensions", "metrics"],
      },
    },
    {
      name: "ga-get-custom-dimensions-and-metrics",
      description:
        "Returns custom dimensions and metrics for a GA4 property.",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
          property_id: {
            type: "string" as const,
            description: "The Google Analytics property ID.",
          },
        },
        required: accountConfigs.length > 1
          ? ["account", "property_id"]
          : ["property_id"],
      },
    },
    {
      name: "ga-list-google-ads-links",
      description:
        "Returns links to Google Ads accounts for a GA4 property.",
      inputSchema: {
        type: "object" as const,
        properties: {
          account: accountParam,
          property_id: {
            type: "string" as const,
            description: "The Google Analytics property ID.",
          },
        },
        required: accountConfigs.length > 1
          ? ["account", "property_id"]
          : ["property_id"],
      },
    },
  ];
}

// Map our tool names to upstream tool names
const TOOL_NAME_MAP: Record<string, string> = {
  "ga-get-account-summaries": "get_account_summaries",
  "ga-get-property-details": "get_property_details",
  "ga-run-report": "run_report",
  "ga-run-realtime-report": "run_realtime_report",
  "ga-get-custom-dimensions-and-metrics": "get_custom_dimensions_and_metrics",
  "ga-list-google-ads-links": "list_google_ads_links",
};

async function main() {
  const accountConfigs = getAccountConfigs();

  console.error("Google Analytics MCP Proxy Server starting...");
  console.error(
    `Configured accounts: ${accountConfigs.map((a) => a.name).join(", ") || "none"}`
  );
  console.error(`Analytics MCP path: ${getAnalyticsMcpPath()}`);

  const server = new Server(
    {
      name: "google-analytics-mcp",
      version: "1.0.0",
    },
    {
      capabilities: {
        tools: {},
      },
    }
  );

  // List available tools
  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: getTools(accountConfigs),
  }));

  // Handle tool calls
  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;

    try {
      // Handle list-accounts locally
      if (name === "ga-list-accounts") {
        const accounts = accountConfigs.map((a) => ({
          name: a.name,
          credentials_path: a.credentialsPath,
        }));

        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  count: accounts.length,
                  accounts,
                  note:
                    accounts.length === 0
                      ? "No accounts configured. Add Google Analytics connections in Claude Runner settings."
                      : undefined,
                },
                null,
                2
              ),
            },
          ],
        };
      }

      // For all other tools, proxy to upstream
      const upstreamName = TOOL_NAME_MAP[name];
      if (!upstreamName) {
        throw new Error(`Unknown tool: ${name}`);
      }

      // Extract and resolve account
      const { account: accountName, ...upstreamArgs } = (args || {}) as Record<
        string,
        unknown
      >;
      const accountConfig = resolveAccount(
        accountConfigs,
        accountName as string | undefined
      );

      // Get or create upstream client for this account
      const client = await getOrCreateClient(accountConfig);

      // Forward the tool call to upstream
      const result = await client.callTool({
        name: upstreamName,
        arguments: upstreamArgs,
      });

      return {
        content: result.content as Array<{ type: string; text: string }>,
        isError: result.isError,
      };
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : String(error);
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(
              {
                error: true,
                message: errorMessage,
              },
              null,
              2
            ),
          },
        ],
        isError: true,
      };
    }
  });

  // Clean up upstream clients on exit
  process.on("SIGINT", async () => {
    for (const [, client] of clientCache) {
      try {
        await client.close();
      } catch {
        // ignore cleanup errors
      }
    }
    process.exit(0);
  });

  // Start server
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Google Analytics MCP Proxy Server running on stdio");
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
