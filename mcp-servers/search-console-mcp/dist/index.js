#!/usr/bin/env node
/**
 * Google Search Console MCP Proxy Server
 *
 * Proxies tool calls to the upstream `mcp-server-gsc` Node.js server with
 * multi-account support. Each configured account has its own service account
 * credentials file, and tool calls include an `account` parameter to select
 * which credentials to use.
 *
 * Environment variables:
 * - GSC_ACCOUNT_<NAME>_CREDENTIALS_PATH: Path to service account JSON file
 */
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema, } from "@modelcontextprotocol/sdk/types.js";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
// Parse account configurations from environment variables
function getAccountConfigs() {
    const configs = [];
    const accountNames = new Set();
    for (const key of Object.keys(process.env)) {
        const match = key.match(/^GSC_ACCOUNT_([A-Z0-9_]+)_CREDENTIALS_PATH$/);
        if (match) {
            accountNames.add(match[1]);
        }
    }
    for (const name of accountNames) {
        const credentialsPath = process.env[`GSC_ACCOUNT_${name}_CREDENTIALS_PATH`];
        if (credentialsPath && fs.existsSync(credentialsPath)) {
            configs.push({
                name: name.toLowerCase().replace(/_/g, "-"),
                credentialsPath,
            });
        }
    }
    return configs;
}
// Get the path to the mcp-server-gsc binary
function getUpstreamBinaryPath() {
    return (process.env.GSC_MCP_SERVER_PATH ||
        path.resolve(__dirname, "..", "node_modules", ".bin", "mcp-server-gsc"));
}
// Create an MCP client connected to the upstream mcp-server-gsc for a specific account
async function createUpstreamClient(accountConfig) {
    const binaryPath = getUpstreamBinaryPath();
    const transport = new StdioClientTransport({
        command: "node",
        args: [binaryPath],
        env: {
            ...process.env,
            GOOGLE_APPLICATION_CREDENTIALS: accountConfig.credentialsPath,
        },
    });
    const client = new Client({
        name: "search-console-mcp-proxy",
        version: "1.0.0",
    }, {
        capabilities: {},
    });
    await client.connect(transport);
    return client;
}
// Cache for upstream clients keyed by account name
const clientCache = new Map();
async function getOrCreateClient(accountConfig) {
    const cached = clientCache.get(accountConfig.name);
    if (cached) {
        return cached;
    }
    const client = await createUpstreamClient(accountConfig);
    clientCache.set(accountConfig.name, client);
    return client;
}
// Resolve which account to use
function resolveAccount(configs, accountName) {
    if (!accountName) {
        if (configs.length === 1) {
            return configs[0];
        }
        throw new Error(`Multiple accounts configured. Please specify an 'account' parameter. Available: ${configs.map((c) => c.name).join(", ")}`);
    }
    const normalized = accountName.toLowerCase().replace(/_/g, "-");
    const config = configs.find((c) => c.name === normalized);
    if (!config) {
        throw new Error(`Account not found: ${accountName}. Available: ${configs.map((c) => c.name).join(", ")}`);
    }
    return config;
}
// Build the tool list with an added `account` parameter + list-accounts tool
function getTools(accountConfigs) {
    const accountList = accountConfigs.map((a) => a.name).join(", ") || "none configured";
    const accountParam = {
        type: "string",
        description: `Account name to use (${accountList}). ${accountConfigs.length === 1 ? "Optional - defaults to the only configured account." : "Required when multiple accounts are configured."}`,
    };
    // Note: upstream expects dimensions as a comma-separated string, not an array
    const filterOperatorEnum = {
        type: "string",
        enum: [
            "equals",
            "contains",
            "notEquals",
            "notContains",
            "includingRegex",
            "excludingRegex",
        ],
    };
    return [
        {
            name: "gsc-list-accounts",
            description: `List all configured Google Search Console accounts. Currently available: ${accountList}`,
            inputSchema: {
                type: "object",
                properties: {},
                required: [],
            },
        },
        {
            name: "gsc-list-sites",
            description: "List all sites (properties) in Google Search Console accessible by the specified account.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                },
                required: accountConfigs.length > 1 ? ["account"] : [],
            },
        },
        {
            name: "gsc-search-analytics",
            description: "Get search performance data (clicks, impressions, CTR, position) from Google Search Console. Supports filtering by page, query, country, device.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console (e.g. 'https://example.com/' or 'sc-domain:example.com').",
                    },
                    startDate: {
                        type: "string",
                        description: "Start date in YYYY-MM-DD format.",
                    },
                    endDate: {
                        type: "string",
                        description: "End date in YYYY-MM-DD format.",
                    },
                    dimensions: {
                        type: "string",
                        description: "Comma-separated dimensions to group by: query, page, country, device, searchAppearance, date. Example: 'query,page'.",
                    },
                    type: {
                        type: "string",
                        description: "Search type: web, image, video, news, discover, googleNews.",
                    },
                    rowLimit: {
                        type: "number",
                        description: "Maximum rows to return (max 1000).",
                    },
                    pageFilter: {
                        type: "string",
                        description: "Filter by page URL.",
                    },
                    queryFilter: {
                        type: "string",
                        description: "Filter by search query.",
                    },
                    countryFilter: {
                        type: "string",
                        description: "Filter by country code (e.g. 'USA', 'DEU').",
                    },
                    deviceFilter: {
                        type: "string",
                        description: "Filter by device type: DESKTOP, MOBILE, TABLET.",
                    },
                    filterOperator: filterOperatorEnum,
                    aggregationType: {
                        type: "string",
                        description: "Aggregation type: auto, byPage, byProperty.",
                    },
                    dataState: {
                        type: "string",
                        description: "Data state filter: final, all.",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "startDate", "endDate"]
                    : ["siteUrl", "startDate", "endDate"],
            },
        },
        {
            name: "gsc-enhanced-search-analytics",
            description: "Enhanced search analytics with up to 25,000 rows, regex filters, and optional quick wins detection.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    startDate: {
                        type: "string",
                        description: "Start date in YYYY-MM-DD format.",
                    },
                    endDate: {
                        type: "string",
                        description: "End date in YYYY-MM-DD format.",
                    },
                    dimensions: {
                        type: "string",
                        description: "Comma-separated dimensions to group by: query, page, country, device, searchAppearance, date. Example: 'query,page'.",
                    },
                    type: {
                        type: "string",
                        description: "Search type: web, image, video, news.",
                    },
                    rowLimit: {
                        type: "number",
                        description: "Maximum rows (up to 25000).",
                    },
                    pageFilter: { type: "string" },
                    queryFilter: { type: "string" },
                    countryFilter: { type: "string" },
                    deviceFilter: { type: "string" },
                    filterOperator: filterOperatorEnum,
                    aggregationType: { type: "string" },
                    regexFilter: {
                        type: "string",
                        description: "Regex pattern to filter results.",
                    },
                    enableQuickWins: {
                        type: "boolean",
                        description: "Enable quick wins detection in the response.",
                    },
                    quickWinsThresholds: {
                        type: "object",
                        description: "Custom thresholds for quick wins detection (minImpressions, maxCtr, positionRangeMin, positionRangeMax).",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "startDate", "endDate"]
                    : ["siteUrl", "startDate", "endDate"],
            },
        },
        {
            name: "gsc-detect-quick-wins",
            description: "Automatically detect SEO quick wins and optimization opportunities based on search analytics data.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    startDate: {
                        type: "string",
                        description: "Start date in YYYY-MM-DD format.",
                    },
                    endDate: {
                        type: "string",
                        description: "End date in YYYY-MM-DD format.",
                    },
                    minImpressions: {
                        type: "number",
                        description: "Minimum impressions threshold (default: 100).",
                    },
                    maxCtr: {
                        type: "number",
                        description: "Maximum CTR threshold (default: 0.05 = 5%).",
                    },
                    positionRangeMin: {
                        type: "number",
                        description: "Minimum average position (default: 3).",
                    },
                    positionRangeMax: {
                        type: "number",
                        description: "Maximum average position (default: 20).",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "startDate", "endDate"]
                    : ["siteUrl", "startDate", "endDate"],
            },
        },
        {
            name: "gsc-index-inspect",
            description: "Inspect a URL to check its indexing status, crawl info, and rich results in Google Search.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    inspectionUrl: {
                        type: "string",
                        description: "The fully-qualified URL to inspect.",
                    },
                    languageCode: {
                        type: "string",
                        description: "Language code for the inspection (e.g. 'en-US').",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "inspectionUrl"]
                    : ["siteUrl", "inspectionUrl"],
            },
        },
        {
            name: "gsc-list-sitemaps",
            description: "List all sitemaps submitted for a site in Google Search Console.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    sitemapIndex: {
                        type: "string",
                        description: "Optional sitemap index URL to filter.",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl"]
                    : ["siteUrl"],
            },
        },
        {
            name: "gsc-get-sitemap",
            description: "Get details of a specific sitemap for a site in Google Search Console.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    feedpath: {
                        type: "string",
                        description: "The URL of the sitemap to retrieve.",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "feedpath"]
                    : ["siteUrl", "feedpath"],
            },
        },
        {
            name: "gsc-submit-sitemap",
            description: "Submit a sitemap for a site in Google Search Console.",
            inputSchema: {
                type: "object",
                properties: {
                    account: accountParam,
                    siteUrl: {
                        type: "string",
                        description: "The site URL as registered in Search Console.",
                    },
                    feedpath: {
                        type: "string",
                        description: "The URL of the sitemap to submit.",
                    },
                },
                required: accountConfigs.length > 1
                    ? ["account", "siteUrl", "feedpath"]
                    : ["siteUrl", "feedpath"],
            },
        },
    ];
}
// Map our tool names to upstream tool names
const TOOL_NAME_MAP = {
    "gsc-list-sites": "list_sites",
    "gsc-search-analytics": "search_analytics",
    "gsc-enhanced-search-analytics": "enhanced_search_analytics",
    "gsc-detect-quick-wins": "detect_quick_wins",
    "gsc-index-inspect": "index_inspect",
    "gsc-list-sitemaps": "list_sitemaps",
    "gsc-get-sitemap": "get_sitemap",
    "gsc-submit-sitemap": "submit_sitemap",
};
async function main() {
    const accountConfigs = getAccountConfigs();
    console.error("Google Search Console MCP Proxy Server starting...");
    console.error(`Configured accounts: ${accountConfigs.map((a) => a.name).join(", ") || "none"}`);
    console.error(`Upstream binary: ${getUpstreamBinaryPath()}`);
    const server = new Server({
        name: "search-console-mcp",
        version: "1.0.0",
    }, {
        capabilities: {
            tools: {},
        },
    });
    // List available tools
    server.setRequestHandler(ListToolsRequestSchema, async () => ({
        tools: getTools(accountConfigs),
    }));
    // Handle tool calls
    server.setRequestHandler(CallToolRequestSchema, async (request) => {
        const { name, arguments: args } = request.params;
        try {
            // Handle list-accounts locally
            if (name === "gsc-list-accounts") {
                const accounts = accountConfigs.map((a) => ({
                    name: a.name,
                    credentials_path: a.credentialsPath,
                }));
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify({
                                count: accounts.length,
                                accounts,
                                note: accounts.length === 0
                                    ? "No accounts configured. Add Search Console connections in Claude Runner settings."
                                    : undefined,
                            }, null, 2),
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
            const { account: accountName, ...upstreamArgs } = (args || {});
            const accountConfig = resolveAccount(accountConfigs, accountName);
            // Get or create upstream client for this account
            const client = await getOrCreateClient(accountConfig);
            // Forward the tool call to upstream
            const result = await client.callTool({
                name: upstreamName,
                arguments: upstreamArgs,
            });
            return {
                content: result.content,
                isError: result.isError,
            };
        }
        catch (error) {
            const errorMessage = error instanceof Error ? error.message : String(error);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            error: true,
                            message: errorMessage,
                        }, null, 2),
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
            }
            catch {
                // ignore cleanup errors
            }
        }
        process.exit(0);
    });
    // Start server
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("Google Search Console MCP Proxy Server running on stdio");
}
main().catch((error) => {
    console.error("Fatal error:", error);
    process.exit(1);
});
//# sourceMappingURL=index.js.map