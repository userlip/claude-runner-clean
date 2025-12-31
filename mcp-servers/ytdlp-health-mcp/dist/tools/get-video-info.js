import { spawn } from "child_process";
import path from "path";
import { fileURLToPath } from "url";
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const YT_DLP_PATH = path.resolve(__dirname, "../../yt-dlp");
export async function getVideoInfo(params) {
    const { url, listFormats = true, timeout = 60000 } = params;
    const startTime = Date.now();
    return new Promise((resolve) => {
        const args = [
            "--simulate",
            "--dump-json",
            "--no-playlist",
            "--no-warnings",
        ];
        if (!listFormats) {
            args.push("--skip-download");
        }
        args.push(url);
        const proc = spawn(YT_DLP_PATH, args);
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
                    info: null,
                    error: `Timeout after ${timeout}ms`,
                    executionTimeMs,
                });
                return;
            }
            if (code !== 0 || !stdout.trim()) {
                resolve({
                    success: false,
                    info: null,
                    error: stderr.trim() || `yt-dlp exited with code ${code}`,
                    executionTimeMs,
                });
                return;
            }
            try {
                const data = JSON.parse(stdout.trim().split("\n")[0]);
                const formats = (data.formats || []).map((f) => ({
                    formatId: f.format_id || "unknown",
                    ext: f.ext || "unknown",
                    resolution: f.resolution || null,
                    fps: typeof f.fps === "number" ? f.fps : null,
                    vcodec: typeof f.vcodec === "string" ? f.vcodec : null,
                    acodec: typeof f.acodec === "string" ? f.acodec : null,
                    filesize: typeof f.filesize === "number" ? f.filesize : null,
                    tbr: typeof f.tbr === "number" ? f.tbr : null,
                    note: typeof f.format_note === "string" ? f.format_note : null,
                }));
                const info = {
                    id: data.id || "unknown",
                    title: data.title || "Unknown",
                    description: data.description || null,
                    uploader: data.uploader || null,
                    uploaderId: data.uploader_id || null,
                    uploaderUrl: data.uploader_url || null,
                    duration: typeof data.duration === "number" ? data.duration : null,
                    viewCount: typeof data.view_count === "number" ? data.view_count : null,
                    likeCount: typeof data.like_count === "number" ? data.like_count : null,
                    commentCount: typeof data.comment_count === "number" ? data.comment_count : null,
                    thumbnail: data.thumbnail || null,
                    uploadDate: data.upload_date || null,
                    extractor: data.extractor || "unknown",
                    extractorKey: data.extractor_key || "unknown",
                    webpage_url: data.webpage_url || url,
                    formats,
                    categories: Array.isArray(data.categories) ? data.categories : [],
                    tags: Array.isArray(data.tags) ? data.tags : [],
                    ageLimit: typeof data.age_limit === "number" ? data.age_limit : null,
                    isLive: Boolean(data.is_live),
                    wasLive: Boolean(data.was_live),
                };
                resolve({
                    success: true,
                    info,
                    error: null,
                    executionTimeMs,
                });
            }
            catch (e) {
                resolve({
                    success: false,
                    info: null,
                    error: `Failed to parse yt-dlp output: ${e}`,
                    executionTimeMs,
                });
            }
        });
        proc.on("error", (err) => {
            clearTimeout(timeoutHandle);
            resolve({
                success: false,
                info: null,
                error: `Failed to spawn yt-dlp: ${err.message}`,
                executionTimeMs: Date.now() - startTime,
            });
        });
    });
}
//# sourceMappingURL=get-video-info.js.map