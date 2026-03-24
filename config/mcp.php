<?php

$home = env('HOME');
$defaultExportPath = $home
    ? rtrim($home, '/').'/.claude.json'
    : base_path('.mcp.json');

return [
    'export_path' => env('MCP_EXPORT_PATH', $defaultExportPath),
    'managed_state_path' => env('MCP_MANAGED_STATE_PATH', storage_path('app/private/mcp-managed-servers.json')),
];
