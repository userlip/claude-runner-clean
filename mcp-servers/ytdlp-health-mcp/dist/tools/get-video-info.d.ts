export interface GetVideoInfoParams {
    url: string;
    listFormats?: boolean;
    timeout?: number;
}
export interface VideoFormat {
    formatId: string;
    ext: string;
    resolution: string | null;
    fps: number | null;
    vcodec: string | null;
    acodec: string | null;
    filesize: number | null;
    tbr: number | null;
    note: string | null;
}
export interface VideoInfo {
    id: string;
    title: string;
    description: string | null;
    uploader: string | null;
    uploaderId: string | null;
    uploaderUrl: string | null;
    duration: number | null;
    viewCount: number | null;
    likeCount: number | null;
    commentCount: number | null;
    thumbnail: string | null;
    uploadDate: string | null;
    extractor: string;
    extractorKey: string;
    webpage_url: string;
    formats: VideoFormat[];
    categories: string[];
    tags: string[];
    ageLimit: number | null;
    isLive: boolean;
    wasLive: boolean;
}
export interface GetVideoInfoResult {
    success: boolean;
    info: VideoInfo | null;
    error: string | null;
    executionTimeMs: number;
}
export declare function getVideoInfo(params: GetVideoInfoParams): Promise<GetVideoInfoResult>;
//# sourceMappingURL=get-video-info.d.ts.map