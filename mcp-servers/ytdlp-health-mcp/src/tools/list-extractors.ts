import { spawn } from "child_process";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const YT_DLP_PATH = path.resolve(__dirname, "../../yt-dlp");

export interface ListExtractorsParams {
  filter?: string;
  limit?: number;
}

export interface ExtractorInfo {
  name: string;
  description?: string;
}

export interface ListExtractorsResult {
  extractors: ExtractorInfo[];
  total: number;
  filtered: number;
}

export async function listExtractors(
  params: ListExtractorsParams
): Promise<ListExtractorsResult> {
  const { filter, limit } = params;

  return new Promise((resolve, reject) => {
    const proc = spawn(YT_DLP_PATH, ["--list-extractors"]);
    let stdout = "";
    let stderr = "";

    proc.stdout.on("data", (data) => {
      stdout += data.toString();
    });

    proc.stderr.on("data", (data) => {
      stderr += data.toString();
    });

    proc.on("close", (code) => {
      if (code !== 0) {
        reject(new Error(`yt-dlp exited with code ${code}: ${stderr}`));
        return;
      }

      const lines = stdout.trim().split("\n").filter(Boolean);
      let extractors: ExtractorInfo[] = lines.map((line) => ({
        name: line.trim(),
      }));

      const total = extractors.length;

      // Apply filter if provided
      if (filter) {
        const filterLower = filter.toLowerCase();
        extractors = extractors.filter((e) =>
          e.name.toLowerCase().includes(filterLower)
        );
      }

      const filtered = extractors.length;

      // Apply limit if provided
      if (limit && limit > 0) {
        extractors = extractors.slice(0, limit);
      }

      resolve({
        extractors,
        total,
        filtered,
      });
    });

    proc.on("error", (err) => {
      reject(new Error(`Failed to spawn yt-dlp: ${err.message}`));
    });
  });
}
