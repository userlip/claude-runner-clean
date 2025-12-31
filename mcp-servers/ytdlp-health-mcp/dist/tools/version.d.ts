export interface VersionResult {
    version: string;
    binaryPath: string;
    success: boolean;
    error: string | null;
}
export declare function getVersion(): Promise<VersionResult>;
export interface ExtendedVersionResult extends VersionResult {
    extractorCount: number;
    pythonVersion: string | null;
}
export declare function getExtendedVersion(): Promise<ExtendedVersionResult>;
//# sourceMappingURL=version.d.ts.map