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
export {};
//# sourceMappingURL=index.d.ts.map