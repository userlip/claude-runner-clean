import { testExtractor } from "./test-extractor.js";
// Sample URLs for popular extractors
const SAMPLE_URLS = {
    youtube: "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    tiktok: "https://www.tiktok.com/@tiktok/video/7106594312292453675",
    instagram: "https://www.instagram.com/reel/C0xFHGRo6hO/",
    twitter: "https://twitter.com/Twitter/status/1445078208190291973",
    x: "https://x.com/X/status/1445078208190291973",
    facebook: "https://www.facebook.com/facebook/videos/10153231379946729/",
    vimeo: "https://vimeo.com/148751763",
    dailymotion: "https://www.dailymotion.com/video/x8m8jn6",
    twitch: "https://www.twitch.tv/videos/1234567890",
    soundcloud: "https://soundcloud.com/rick-astley-official/never-gonna-give-you-up-4",
    reddit: "https://www.reddit.com/r/videos/comments/example",
    linkedin: "https://www.linkedin.com/posts/example",
    pinterest: "https://www.pinterest.com/pin/example",
    tumblr: "https://example.tumblr.com/post/example",
    bilibili: "https://www.bilibili.com/video/BV1GJ411x7h7",
};
const DEFAULT_EXTRACTORS = [
    "youtube",
    "tiktok",
    "instagram",
    "twitter",
    "facebook",
    "vimeo",
    "dailymotion",
];
export async function healthCheck(params) {
    const { extractors = DEFAULT_EXTRACTORS, timeout = 30000, parallel = false, } = params;
    const timestamp = new Date().toISOString();
    const results = [];
    const testOne = async (name) => {
        const url = SAMPLE_URLS[name.toLowerCase()];
        if (!url) {
            return {
                name,
                url: "N/A",
                success: false,
                extractor: null,
                title: null,
                duration: null,
                formats: 0,
                error: `No sample URL configured for extractor: ${name}`,
                executionTimeMs: 0,
            };
        }
        const result = await testExtractor({ url, timeout });
        return {
            name,
            url,
            ...result,
        };
    };
    if (parallel) {
        const promises = extractors.map((name) => testOne(name));
        const testResults = await Promise.all(promises);
        results.push(...testResults);
    }
    else {
        for (const name of extractors) {
            const result = await testOne(name);
            results.push(result);
        }
    }
    const passed = results.filter((r) => r.success).length;
    const failed = results.filter((r) => !r.success).length;
    return {
        timestamp,
        totalTested: results.length,
        passed,
        failed,
        results,
        summary: {
            healthy: results.filter((r) => r.success).map((r) => r.name),
            unhealthy: results.filter((r) => !r.success).map((r) => r.name),
        },
    };
}
export function getSampleUrls() {
    return { ...SAMPLE_URLS };
}
export function getDefaultExtractors() {
    return [...DEFAULT_EXTRACTORS];
}
//# sourceMappingURL=health-check.js.map