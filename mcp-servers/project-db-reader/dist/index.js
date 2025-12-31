#!/usr/bin/env node
/**
 * Project DB Reader MCP Server
 *
 * Provides readonly access to project databases for analytics queries.
 * Supports multiple project connections via environment variables.
 *
 * Environment variables:
 * - PROJECT_DB_<NAME>_HOST: Database host
 * - PROJECT_DB_<NAME>_PORT: Database port (default: 3306)
 * - PROJECT_DB_<NAME>_DATABASE: Database name
 * - PROJECT_DB_<NAME>_USERNAME: Database username
 * - PROJECT_DB_<NAME>_PASSWORD: Database password
 *
 * Example:
 * - PROJECT_DB_SCRAPPA_HOST=localhost
 * - PROJECT_DB_SCRAPPA_DATABASE=scrappa
 * - PROJECT_DB_SCRAPPA_USERNAME=readonly
 * - PROJECT_DB_SCRAPPA_PASSWORD=secret
 */
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema, } from "@modelcontextprotocol/sdk/types.js";
import mysql from "mysql2/promise";
// Parse project configurations from environment variables
function getProjectConfigs() {
    const configs = [];
    const projectNames = new Set();
    // Find all project names from environment variables
    for (const key of Object.keys(process.env)) {
        const match = key.match(/^PROJECT_DB_([A-Z0-9_]+)_HOST$/);
        if (match) {
            projectNames.add(match[1]);
        }
    }
    // Build config for each project
    for (const name of projectNames) {
        const prefix = `PROJECT_DB_${name}_`;
        const host = process.env[`${prefix}HOST`];
        const database = process.env[`${prefix}DATABASE`];
        const username = process.env[`${prefix}USERNAME`];
        const password = process.env[`${prefix}PASSWORD`];
        if (host && database && username) {
            configs.push({
                name: name.toLowerCase(),
                host,
                port: parseInt(process.env[`${prefix}PORT`] || "3306", 10),
                database,
                username,
                password: password || "",
            });
        }
    }
    return configs;
}
// Create database connection pool for a project
async function createConnection(config) {
    return mysql.createConnection({
        host: config.host,
        port: config.port,
        database: config.database,
        user: config.username,
        password: config.password,
        connectTimeout: 10000,
        // Readonly settings
        multipleStatements: false,
    });
}
// Validate query is readonly (SELECT only)
function isReadonlyQuery(sql) {
    const normalized = sql.trim().toUpperCase();
    // Only allow SELECT, SHOW, DESCRIBE, EXPLAIN
    const allowedPrefixes = ["SELECT", "SHOW", "DESCRIBE", "DESC", "EXPLAIN"];
    const startsWithAllowed = allowedPrefixes.some(prefix => normalized.startsWith(prefix));
    if (!startsWithAllowed) {
        return false;
    }
    // Block dangerous patterns even in SELECT
    const dangerousPatterns = [
        /INTO\s+OUTFILE/i,
        /INTO\s+DUMPFILE/i,
        /LOAD_FILE/i,
        /BENCHMARK/i,
        /SLEEP\s*\(/i,
    ];
    return !dangerousPatterns.some(pattern => pattern.test(sql));
}
// Execute readonly query against a project database
async function executeQuery(config, sql, limit = 100) {
    if (!isReadonlyQuery(sql)) {
        throw new Error("Only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed");
    }
    const connection = await createConnection(config);
    const startTime = Date.now();
    try {
        // Add LIMIT if not present and it's a SELECT query
        let finalSql = sql;
        const normalizedSql = sql.trim().toUpperCase();
        if (normalizedSql.startsWith("SELECT") && !normalizedSql.includes("LIMIT")) {
            finalSql = `${sql.trim().replace(/;$/, "")} LIMIT ${limit}`;
        }
        const [rows, fields] = await connection.execute(finalSql);
        const executionTime = Date.now() - startTime;
        const rowArray = Array.isArray(rows) ? rows : [rows];
        const fieldNames = Array.isArray(fields)
            ? fields.map(f => f.name)
            : [];
        return {
            rows: rowArray,
            fields: fieldNames,
            rowCount: rowArray.length,
            executionTime,
        };
    }
    finally {
        await connection.end();
    }
}
// Get table schema
async function getTableSchema(config, tableName) {
    const connection = await createConnection(config);
    try {
        const [columns] = await connection.execute(`DESCRIBE \`${tableName.replace(/`/g, "")}\``);
        const [indexes] = await connection.execute(`SHOW INDEX FROM \`${tableName.replace(/`/g, "")}\``);
        return {
            columns: columns,
            indexes: indexes,
        };
    }
    finally {
        await connection.end();
    }
}
// Get list of tables in database
async function listTables(config) {
    const connection = await createConnection(config);
    try {
        const [rows] = await connection.execute("SHOW TABLES");
        return rows.map(row => Object.values(row)[0]);
    }
    finally {
        await connection.end();
    }
}
// Get database statistics
async function getDatabaseStats(config) {
    const connection = await createConnection(config);
    try {
        const [sizeResult] = await connection.execute(`
      SELECT
        table_schema AS database_name,
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
        COUNT(*) AS table_count
      FROM information_schema.tables
      WHERE table_schema = ?
      GROUP BY table_schema
    `, [config.database]);
        const [tableStats] = await connection.execute(`
      SELECT
        table_name,
        table_rows AS estimated_rows,
        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
        update_time
      FROM information_schema.tables
      WHERE table_schema = ?
      ORDER BY data_length + index_length DESC
      LIMIT 20
    `, [config.database]);
        return {
            overview: sizeResult[0] || {},
            topTables: tableStats,
        };
    }
    finally {
        await connection.end();
    }
}
// Define MCP tools
function getTools(projectConfigs) {
    const projectList = projectConfigs.map(p => p.name).join(", ") || "none configured";
    return [
        {
            name: "db-list-projects",
            description: `List all configured project databases. Currently available: ${projectList}`,
            inputSchema: {
                type: "object",
                properties: {},
                required: [],
            },
        },
        {
            name: "db-query",
            description: "Execute a readonly SQL query against a project database. Only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed.",
            inputSchema: {
                type: "object",
                properties: {
                    project: {
                        type: "string",
                        description: `Project name (${projectList})`,
                    },
                    sql: {
                        type: "string",
                        description: "SQL query to execute (SELECT only)",
                    },
                    limit: {
                        type: "number",
                        description: "Maximum rows to return (default: 100, max: 1000)",
                        default: 100,
                    },
                },
                required: ["project", "sql"],
            },
        },
        {
            name: "db-list-tables",
            description: "List all tables in a project database",
            inputSchema: {
                type: "object",
                properties: {
                    project: {
                        type: "string",
                        description: `Project name (${projectList})`,
                    },
                },
                required: ["project"],
            },
        },
        {
            name: "db-describe-table",
            description: "Get schema details for a specific table (columns and indexes)",
            inputSchema: {
                type: "object",
                properties: {
                    project: {
                        type: "string",
                        description: `Project name (${projectList})`,
                    },
                    table: {
                        type: "string",
                        description: "Table name",
                    },
                },
                required: ["project", "table"],
            },
        },
        {
            name: "db-stats",
            description: "Get database statistics including size and top tables by size",
            inputSchema: {
                type: "object",
                properties: {
                    project: {
                        type: "string",
                        description: `Project name (${projectList})`,
                    },
                },
                required: ["project"],
            },
        },
        {
            name: "db-analytics-query",
            description: "Run common analytics queries (counts, trends, aggregations) with templated safety",
            inputSchema: {
                type: "object",
                properties: {
                    project: {
                        type: "string",
                        description: `Project name (${projectList})`,
                    },
                    queryType: {
                        type: "string",
                        enum: ["count", "daily_counts", "hourly_counts", "top_values", "recent_records"],
                        description: "Type of analytics query",
                    },
                    table: {
                        type: "string",
                        description: "Table to query",
                    },
                    dateColumn: {
                        type: "string",
                        description: "Column containing dates (for time-based queries)",
                    },
                    groupColumn: {
                        type: "string",
                        description: "Column to group by (for top_values)",
                    },
                    days: {
                        type: "number",
                        description: "Number of days to look back (default: 7)",
                        default: 7,
                    },
                    limit: {
                        type: "number",
                        description: "Number of results to return (default: 10)",
                        default: 10,
                    },
                },
                required: ["project", "queryType", "table"],
            },
        },
    ];
}
// Get project config by name
function getProjectConfig(configs, name) {
    return configs.find(c => c.name === name.toLowerCase());
}
// Sanitize identifier (table/column name)
function sanitizeIdentifier(name) {
    return name.replace(/[^a-zA-Z0-9_]/g, "");
}
// Build analytics query
function buildAnalyticsQuery(queryType, table, options) {
    const safeTable = sanitizeIdentifier(table);
    const safeDateCol = options.dateColumn ? sanitizeIdentifier(options.dateColumn) : "created_at";
    const safeGroupCol = options.groupColumn ? sanitizeIdentifier(options.groupColumn) : "";
    const days = options.days || 7;
    const limit = Math.min(options.limit || 10, 100);
    switch (queryType) {
        case "count":
            return `SELECT COUNT(*) as total FROM \`${safeTable}\``;
        case "daily_counts":
            return `
        SELECT
          DATE(\`${safeDateCol}\`) as date,
          COUNT(*) as count
        FROM \`${safeTable}\`
        WHERE \`${safeDateCol}\` >= DATE_SUB(CURDATE(), INTERVAL ${days} DAY)
        GROUP BY DATE(\`${safeDateCol}\`)
        ORDER BY date DESC
        LIMIT ${limit}
      `;
        case "hourly_counts":
            return `
        SELECT
          DATE_FORMAT(\`${safeDateCol}\`, '%Y-%m-%d %H:00') as hour,
          COUNT(*) as count
        FROM \`${safeTable}\`
        WHERE \`${safeDateCol}\` >= DATE_SUB(NOW(), INTERVAL ${days} DAY)
        GROUP BY hour
        ORDER BY hour DESC
        LIMIT ${limit}
      `;
        case "top_values":
            if (!safeGroupCol) {
                throw new Error("groupColumn is required for top_values query");
            }
            return `
        SELECT
          \`${safeGroupCol}\` as value,
          COUNT(*) as count
        FROM \`${safeTable}\`
        GROUP BY \`${safeGroupCol}\`
        ORDER BY count DESC
        LIMIT ${limit}
      `;
        case "recent_records":
            return `
        SELECT *
        FROM \`${safeTable}\`
        ORDER BY \`${safeDateCol}\` DESC
        LIMIT ${limit}
      `;
        default:
            throw new Error(`Unknown query type: ${queryType}`);
    }
}
// Main server setup
async function main() {
    const projectConfigs = getProjectConfigs();
    console.error(`Project DB Reader MCP Server starting...`);
    console.error(`Configured projects: ${projectConfigs.map(p => p.name).join(", ") || "none"}`);
    const server = new Server({
        name: "project-db-reader",
        version: "1.0.0",
    }, {
        capabilities: {
            tools: {},
        },
    });
    // List available tools
    server.setRequestHandler(ListToolsRequestSchema, async () => ({
        tools: getTools(projectConfigs),
    }));
    // Handle tool calls
    server.setRequestHandler(CallToolRequestSchema, async (request) => {
        const { name, arguments: args } = request.params;
        try {
            switch (name) {
                case "db-list-projects": {
                    const projects = projectConfigs.map(p => ({
                        name: p.name,
                        host: p.host,
                        database: p.database,
                    }));
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    count: projects.length,
                                    projects,
                                    note: projects.length === 0
                                        ? "No projects configured. Set PROJECT_DB_<NAME>_HOST/DATABASE/USERNAME/PASSWORD environment variables."
                                        : undefined,
                                }, null, 2),
                            },
                        ],
                    };
                }
                case "db-query": {
                    const project = args?.project;
                    const sql = args?.sql;
                    const limit = Math.min(args?.limit || 100, 1000);
                    const config = getProjectConfig(projectConfigs, project);
                    if (!config) {
                        throw new Error(`Project not found: ${project}. Available: ${projectConfigs.map(p => p.name).join(", ")}`);
                    }
                    const result = await executeQuery(config, sql, limit);
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    project: config.name,
                                    database: config.database,
                                    query: sql,
                                    ...result,
                                }, null, 2),
                            },
                        ],
                    };
                }
                case "db-list-tables": {
                    const project = args?.project;
                    const config = getProjectConfig(projectConfigs, project);
                    if (!config) {
                        throw new Error(`Project not found: ${project}`);
                    }
                    const tables = await listTables(config);
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    project: config.name,
                                    database: config.database,
                                    tableCount: tables.length,
                                    tables,
                                }, null, 2),
                            },
                        ],
                    };
                }
                case "db-describe-table": {
                    const project = args?.project;
                    const table = args?.table;
                    const config = getProjectConfig(projectConfigs, project);
                    if (!config) {
                        throw new Error(`Project not found: ${project}`);
                    }
                    const schema = await getTableSchema(config, table);
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    project: config.name,
                                    database: config.database,
                                    table,
                                    ...schema,
                                }, null, 2),
                            },
                        ],
                    };
                }
                case "db-stats": {
                    const project = args?.project;
                    const config = getProjectConfig(projectConfigs, project);
                    if (!config) {
                        throw new Error(`Project not found: ${project}`);
                    }
                    const stats = await getDatabaseStats(config);
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    project: config.name,
                                    database: config.database,
                                    ...stats,
                                }, null, 2),
                            },
                        ],
                    };
                }
                case "db-analytics-query": {
                    const project = args?.project;
                    const queryType = args?.queryType;
                    const table = args?.table;
                    const config = getProjectConfig(projectConfigs, project);
                    if (!config) {
                        throw new Error(`Project not found: ${project}`);
                    }
                    const sql = buildAnalyticsQuery(queryType, table, {
                        dateColumn: args?.dateColumn,
                        groupColumn: args?.groupColumn,
                        days: args?.days,
                        limit: args?.limit,
                    });
                    const result = await executeQuery(config, sql, 100);
                    return {
                        content: [
                            {
                                type: "text",
                                text: JSON.stringify({
                                    project: config.name,
                                    database: config.database,
                                    queryType,
                                    table,
                                    generatedSql: sql.trim(),
                                    ...result,
                                }, null, 2),
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
    // Start server
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("Project DB Reader MCP Server running on stdio");
}
main().catch((error) => {
    console.error("Fatal error:", error);
    process.exit(1);
});
//# sourceMappingURL=index.js.map