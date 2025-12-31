import { exec } from "child_process";
import { promisify } from "util";

const execAsync = promisify(exec);

export interface SentryCliOptions {
  org?: string;
  project?: string;
}

/**
 * Execute a sentry-cli command and return the output
 */
export async function runSentryCli(
  args: string[],
  options: SentryCliOptions = {}
): Promise<string> {
  const cmdParts = ["sentry-cli"];

  if (options.org) {
    cmdParts.push("-o", options.org);
  }

  if (options.project) {
    cmdParts.push("-p", options.project);
  }

  cmdParts.push(...args);

  const command = cmdParts.join(" ");

  try {
    const { stdout, stderr } = await execAsync(command, {
      maxBuffer: 10 * 1024 * 1024, // 10MB buffer
    });

    if (stderr && !stdout) {
      throw new Error(stderr);
    }

    return stdout;
  } catch (error) {
    if (error instanceof Error) {
      throw new Error(`sentry-cli error: ${error.message}`);
    }
    throw error;
  }
}

/**
 * Parse table output from sentry-cli into structured data
 */
export function parseCliTable(output: string): Record<string, string>[] {
  const lines = output.trim().split("\n");
  if (lines.length < 3) {
    return [];
  }

  // Find header line (first line with actual content after the border)
  const headerLine = lines.find(
    (line) => line.startsWith("|") && !line.includes("---") && !line.includes("+-")
  );
  if (!headerLine) {
    return [];
  }

  // Parse headers
  const headers = headerLine
    .split("|")
    .map((h) => h.trim())
    .filter((h) => h.length > 0)
    .map((h) => h.toLowerCase().replace(/\s+/g, "_"));

  // Parse data rows
  const results: Record<string, string>[] = [];
  for (const line of lines) {
    if (
      line.startsWith("|") &&
      !line.includes("---") &&
      !line.includes("+-") &&
      line !== headerLine
    ) {
      const values = line
        .split("|")
        .map((v) => v.trim())
        .filter((_, i, arr) => i > 0 && i < arr.length - 1);

      if (values.length === headers.length) {
        const row: Record<string, string> = {};
        headers.forEach((header, index) => {
          row[header] = values[index];
        });
        results.push(row);
      }
    }
  }

  return results;
}

/**
 * Get Sentry API base URL and token from configuration
 */
export async function getSentryConfig(): Promise<{
  url: string;
  token: string;
}> {
  const fs = await import("fs");
  const path = await import("path");
  const os = await import("os");

  const configPath = path.join(os.default.homedir(), ".sentryclirc");

  try {
    const content = fs.default.readFileSync(configPath, "utf-8");
    const lines = content.split("\n");

    let url = "https://sentry.io";
    let token = "";

    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith("url=")) {
        url = trimmed.substring(4).replace(/\/$/, "");
      } else if (trimmed.startsWith("token=")) {
        token = trimmed.substring(6);
      }
    }

    return { url, token };
  } catch {
    throw new Error("Could not read Sentry configuration from ~/.sentryclirc");
  }
}

/**
 * Make a request to the Sentry API
 */
export async function sentryApiRequest<T>(
  endpoint: string,
  options: {
    method?: string;
    body?: unknown;
  } = {}
): Promise<T> {
  const config = await getSentryConfig();

  const url = `${config.url}/api/0/${endpoint}`;
  const method = options.method || "GET";

  const headers: Record<string, string> = {
    Authorization: `Bearer ${config.token}`,
    "Content-Type": "application/json",
  };

  const fetchOptions: RequestInit = {
    method,
    headers,
  };

  if (options.body) {
    fetchOptions.body = JSON.stringify(options.body);
  }

  const response = await fetch(url, fetchOptions);

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(
      `Sentry API error (${response.status}): ${errorText}`
    );
  }

  return response.json() as Promise<T>;
}
