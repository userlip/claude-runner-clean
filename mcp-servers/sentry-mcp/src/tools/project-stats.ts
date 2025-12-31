import { sentryApiRequest } from "../utils/sentry-cli.js";

export interface ProjectStatsParams {
  org: string;
  project: string;
  stat?: "received" | "rejected" | "blacklisted" | "generated";
  resolution?: "10s" | "1h" | "1d";
  since?: number;
  until?: number;
}

export interface StatsPoint {
  timestamp: number;
  count: number;
}

export interface ProjectStats {
  project: string;
  stat: string;
  resolution: string;
  data: StatsPoint[];
  total: number;
  start: string;
  end: string;
}

interface StatsResponse {
  start: string;
  end: string;
  intervals: string[];
  groups: Array<{
    by: Record<string, string>;
    totals: Record<string, number>;
    series: Record<string, number[]>;
  }>;
}

export async function getProjectStats(
  params: ProjectStatsParams
): Promise<ProjectStats> {
  const stat = params.stat || "received";
  const resolution = params.resolution || "1h";

  // Calculate time range (default: last 24 hours)
  const now = Math.floor(Date.now() / 1000);
  const since = params.since || now - 24 * 60 * 60;
  const until = params.until || now;

  // Use the organization stats endpoint which is more reliable
  const queryParams = new URLSearchParams({
    field: `sum(quantity)`,
    category: "error",
    project: params.project,
    interval: resolution,
    statsPeriod: "24h",
  });

  try {
    const response = await sentryApiRequest<StatsResponse>(
      `organizations/${params.org}/stats_v2/?${queryParams.toString()}`
    );

    const data: StatsPoint[] = [];
    let total = 0;

    if (response.groups && response.groups.length > 0) {
      const group = response.groups[0];
      const seriesKey = Object.keys(group.series)[0];
      const series = group.series[seriesKey] || [];

      response.intervals.forEach((interval, index) => {
        const count = series[index] || 0;
        data.push({
          timestamp: new Date(interval).getTime() / 1000,
          count,
        });
        total += count;
      });
    }

    return {
      project: params.project,
      stat,
      resolution,
      data,
      total,
      start: new Date(since * 1000).toISOString(),
      end: new Date(until * 1000).toISOString(),
    };
  } catch {
    // Fallback: try the legacy project stats endpoint
    const legacyQueryParams = new URLSearchParams({
      stat,
      resolution,
      since: String(since),
      until: String(until),
    });

    interface LegacyStatsResponse extends Array<[number, number]> {}

    const legacyResponse = await sentryApiRequest<LegacyStatsResponse>(
      `projects/${params.org}/${params.project}/stats/?${legacyQueryParams.toString()}`
    );

    const data: StatsPoint[] = legacyResponse.map(([timestamp, count]) => ({
      timestamp,
      count,
    }));

    const total = data.reduce((sum, point) => sum + point.count, 0);

    return {
      project: params.project,
      stat,
      resolution,
      data,
      total,
      start: new Date(since * 1000).toISOString(),
      end: new Date(until * 1000).toISOString(),
    };
  }
}

export const projectStatsTool = {
  name: "sentry-project-stats",
  description:
    "Get error counts and trends for a project over time. Useful for monitoring error rates and identifying spikes.",
  inputSchema: {
    type: "object" as const,
    properties: {
      org: {
        type: "string",
        description: "Organization slug",
      },
      project: {
        type: "string",
        description: "Project slug",
      },
      stat: {
        type: "string",
        enum: ["received", "rejected", "blacklisted", "generated"],
        description: "Type of stat to retrieve (default: received)",
      },
      resolution: {
        type: "string",
        enum: ["10s", "1h", "1d"],
        description: "Time resolution for data points (default: 1h)",
      },
      since: {
        type: "number",
        description: "Unix timestamp for start of time range",
      },
      until: {
        type: "number",
        description: "Unix timestamp for end of time range",
      },
    },
    required: ["org", "project"],
  },
};
