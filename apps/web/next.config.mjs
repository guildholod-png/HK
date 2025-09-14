import path from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = path.dirname(fileURLToPath(import.meta.url));

/** @type {import('next').NextConfig} */
const nextConfig = {
    experimental: { serverActions: { allowedOrigins: ['*'] } },
    webpack: (config) => {
        config.resolve.alias = { ...(config.resolve.alias || {}), '@': rootDir };
        return config;
    },
};

export default nextConfig;
