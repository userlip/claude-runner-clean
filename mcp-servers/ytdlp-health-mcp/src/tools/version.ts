import { spawn } from "child_process";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const YT_DLP_PATH = path.resolve(__dirname, "../../yt-dlp");

export interface VersionResult {
  version: string;
  binaryPath: string;
  success: boolean;
  error: string | null;
}

export async function getVersion(): Promise<VersionResult> {
  return new Promise((resolve) => {
    const proc = spawn(YT_DLP_PATH, ["--version"]);
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
        resolve({
          version: "unknown",
          binaryPath: YT_DLP_PATH,
          success: false,
          error: stderr.trim() || `yt-dlp exited with code ${code}`,
        });
        return;
      }

      resolve({
        version: stdout.trim(),
        binaryPath: YT_DLP_PATH,
        success: true,
        error: null,
      });
    });

    proc.on("error", (err) => {
      resolve({
        version: "unknown",
        binaryPath: YT_DLP_PATH,
        success: false,
        error: `Failed to spawn yt-dlp: ${err.message}`,
      });
    });
  });
}

export interface ExtendedVersionResult extends VersionResult {
  extractorCount: number;
  pythonVersion: string | null;
}

export async function getExtendedVersion(): Promise<ExtendedVersionResult> {
  const versionResult = await getVersion();

  if (!versionResult.success) {
    return {
      ...versionResult,
      extractorCount: 0,
      pythonVersion: null,
    };
  }

  // Get extractor count
  const extractorCount = await new Promise<number>((resolve) => {
    const proc = spawn(YT_DLP_PATH, ["--list-extractors"]);
    let stdout = "";

    proc.stdout.on("data", (data) => {
      stdout += data.toString();
    });

    proc.on("close", () => {
      const lines = stdout.trim().split("\n").filter(Boolean);
      resolve(lines.length);
    });

    proc.on("error", () => {
      resolve(0);
    });
  });

  // Get verbose output to extract Python version
  const pythonVersion = await new Promise<string | null>((resolve) => {
    const proc = spawn(YT_DLP_PATH, ["--verbose", "--version"]);
    let stderr = "";

    proc.stderr.on("data", (data) => {
      stderr += data.toString();
    });

    proc.on("close", () => {
      const match = stderr.match(/python\s+(\d+\.\d+\.\d+)/i);
      resolve(match ? match[1] : null);
    });

    proc.on("error", () => {
      resolve(null);
    });
  });

  return {
    ...versionResult,
    extractorCount,
    pythonVersion,
  };
}
