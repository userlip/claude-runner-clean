#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema, } from "@modelcontextprotocol/sdk/types.js";
import { listExtractors } from "./tools/list-extractors.js";
import { testExtractor } from "./tools/test-extractor.js";
import { healthCheck, getSampleUrls, getDefaultExtractors, } from "./tools/health-check.js";
import { getVideoInfo } from "./tools/get-video-info.js";
import { getVersion, getExtendedVersion } from "./tools/version.js";
const server = new Server({
    name: "ytdlp-health-mcp",
    version: "1.0.0",
}, {
    capabilities: {
        tools: {},
    },
});
// List available tools
server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
        tools: [
            {
                name: "ytdlp-list-extractors",
                description: "List all available yt-dlp extractors. Can filter by name and limit results. yt-dlp supports 1000+ video sources.",
                inputSchema: {
                    type: "object",
                    properties: {
                        filter: {
                            type: "string",
                            description: "Filter extractors by name (case-insensitive substring match)",
                        },
                        limit: {
                            type: "number",
                            description: "Limit the number of results returned",
                        },
                    },
                },
            },
            {
                name: "ytdlp-test-extractor",
                description: "Test a specific extractor/video source by providing a URL. Returns whether the extractor can successfully parse the video metadata.",
                inputSchema: {
                    type: "object",
                    properties: {
                        url: {
                            type: "string",
                            description: "The video URL to test",
                        },
                        timeout: {
                            type: "number",
                            description: "Timeout in milliseconds (default: 30000)",
                        },
                    },
                    required: ["url"],
                },
            },
            {
                name: "ytdlp-health-check",
                description: "Run health checks on multiple popular video extractors (YouTube, TikTok, Instagram, Twitter, Facebook, Vimeo, etc.) using known sample URLs. Returns a summary of which extractors are working.",
                inputSchema: {
                    type: "object",
                    properties: {
                        extractors: {
                            type: "array",
                            items: { type: "string" },
                            description: `Specific extractors to test. Available: ${Object.keys(getSampleUrls()).join(", ")}. Default: ${getDefaultExtractors().join(", ")}`,
                        },
                        timeout: {
                            type: "number",
                            description: "Timeout per extractor in milliseconds (default: 30000)",
                        },
                        parallel: {
                            type: "boolean",
                            description: "Run tests in parallel (faster but may hit rate limits). Default: false",
                        },
                    },
                },
            },
            {
                name: "ytdlp-get-video-info",
                description: "Get detailed metadata for a video URL without downloading. Returns title, description, uploader, duration, formats, and more.",
                inputSchema: {
                    type: "object",
                    properties: {
                        url: {
                            type: "string",
                            description: "The video URL to get info for",
                        },
                        listFormats: {
                            type: "boolean",
                            description: "Include available formats in the response (default: true)",
                        },
                        timeout: {
                            type: "number",
                            description: "Timeout in milliseconds (default: 60000)",
                        },
                    },
                    required: ["url"],
                },
            },
            {
                name: "ytdlp-version",
                description: "Get yt-dlp version information, including the number of supported extractors.",
                inputSchema: {
                    type: "object",
                    properties: {
                        extended: {
                            type: "boolean",
                            description: "Get extended version info including extractor count (default: false)",
                        },
                    },
                },
            },
        ],
    };
});
// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    try {
        switch (name) {
            case "ytdlp-list-extractors": {
                const params = (args || {});
                const result = await listExtractors(params);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(result, null, 2),
                        },
                    ],
                };
            }
            case "ytdlp-test-extractor": {
                const params = (args || {});
                if (!params.url) {
                    throw new Error("url is required");
                }
                const result = await testExtractor(params);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(result, null, 2),
                        },
                    ],
                };
            }
            case "ytdlp-health-check": {
                const params = (args || {});
                const result = await healthCheck(params);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(result, null, 2),
                        },
                    ],
                };
            }
            case "ytdlp-get-video-info": {
                const params = (args || {});
                if (!params.url) {
                    throw new Error("url is required");
                }
                const result = await getVideoInfo(params);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(result, null, 2),
                        },
                    ],
                };
            }
            case "ytdlp-version": {
                const extended = args?.extended ?? false;
                const result = extended
                    ? await getExtendedVersion()
                    : await getVersion();
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
                throw new Error(`Unknown tool: ${name}`);
        }
    }
    catch (error) {
        const errorMessage = error instanceof Error ? error.message : String(error);
        return {
            content: [
                {
                    type: "text",
                    text: JSON.stringify({ error: errorMessage }, null, 2),
                },
            ],
            isError: true,
        };
    }
});
// Start server
async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("yt-dlp Health Monitor MCP server running on stdio");
}
main().catch((error) => {
    console.error("Fatal error:", error);
    process.exit(1);
});
//# sourceMappingURL=index.js.map