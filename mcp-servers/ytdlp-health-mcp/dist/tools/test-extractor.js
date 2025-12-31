import { spawn } from "child_process";
import path from "path";
import { fileURLToPath } from "url";
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const YT_DLP_PATH = path.resolve(__dirname, "../../yt-dlp");
export async function testExtractor(params) {
    const { url, timeout = 30000 } = params;
    const startTime = Date.now();
    return new Promise((resolve) => {
        const proc = spawn(YT_DLP_PATH, [
            "--simulate",
            "--dump-json",
            "--no-playlist",
            "--no-warnings",
            url,
        ]);
        let stdout = "";
        let stderr = "";
        let timedOut = false;
        const timeoutHandle = setTimeout(() => {
            timedOut = true;
            proc.kill("SIGTERM");
        }, timeout);
        proc.stdout.on("data", (data) => {
            stdout += data.toString();
        });
        proc.stderr.on("data", (data) => {
            stderr += data.toString();
        });
        proc.on("close", (code) => {
            clearTimeout(timeoutHandle);
            const executionTimeMs = Date.now() - startTime;
            if (timedOut) {
                resolve({
                    success: false,
                    extractor: null,
                    title: null,
                    duration: null,
                    formats: 0,
                    error: `Timeout after ${timeout}ms`,
                    executionTimeMs,
                });
                return;
            }
            if (code !== 0 || !stdout.trim()) {
                resolve({
                    success: false,
                    extractor: null,
                    title: null,
                    duration: null,
                    formats: 0,
                    error: stderr.trim() || `yt-dlp exited with code ${code}`,
                    executionTimeMs,
                });
                return;
            }
            try {
                const data = JSON.parse(stdout.trim().split("\n")[0]);
                resolve({
                    success: true,
                    extractor: data.extractor || data.extractor_key || null,
                    title: data.title || null,
                    duration: data.duration || null,
                    formats: data.formats?.length || 0,
                    error: null,
                    executionTimeMs,
                });
            }
            catch (e) {
                resolve({
                    success: false,
                    extractor: null,
                    title: null,
                    duration: null,
                    formats: 0,
                    error: `Failed to parse yt-dlp output: ${e}`,
                    executionTimeMs,
                });
            }
        });
        proc.on("error", (err) => {
            clearTimeout(timeoutHandle);
            resolve({
                success: false,
                extractor: null,
                title: null,
                duration: null,
                formats: 0,
                error: `Failed to spawn yt-dlp: ${err.message}`,
                executionTimeMs: Date.now() - startTime,
            });
        });
    });
}
//# sourceMappingURL=test-extractor.js.map