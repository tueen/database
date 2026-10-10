import { defineConfig } from 'vitepress';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateLlms, getCanonicalSiteUrl } from '../scripts/generate-llms.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const publicDir = path.resolve(__dirname, '../public');

function llmsPlugin() {
  return {
    name: 'vitepress-llms-generator',
    async buildStart() {
      await generateLlms();
    },
    async handleHotUpdate({ file }: { file: string }) {
      if (file.endsWith('.md') && !file.endsWith('llms.md')) {
        await generateLlms();
      }
    },
    configureServer(server: any) {
      server.middlewares.use((req: any, res: any, next: any) => {
        const url = req.url?.split('?')[0];
        if (url === '/llms.txt' || url?.endsWith('/llms.txt')) {
          const host = req.headers.host || '127.0.0.1:5173';
          const protocol = req.headers['x-forwarded-proto'] || 'http';
          const currentOrigin = `${protocol}://${host}`;
          const filePath = path.resolve(publicDir, 'llms.txt');
          if (fs.existsSync(filePath)) {
            const content = fs.readFileSync(filePath, 'utf-8');
            const dynamicContent = content.replaceAll(getCanonicalSiteUrl(), currentOrigin);
            res.setHeader('Content-Type', 'text/plain; charset=utf-8');
            res.end(dynamicContent);
            return;
          }
        }
        if (url === '/llms-full.txt' || url?.endsWith('/llms-full.txt')) {
          const host = req.headers.host || '127.0.0.1:5173';
          const protocol = req.headers['x-forwarded-proto'] || 'http';
          const currentOrigin = `${protocol}://${host}`;
          const filePath = path.resolve(publicDir, 'llms-full.txt');
          if (fs.existsSync(filePath)) {
            const content = fs.readFileSync(filePath, 'utf-8');
            const dynamicContent = content.replaceAll(getCanonicalSiteUrl(), currentOrigin);
            res.setHeader('Content-Type', 'text/plain; charset=utf-8');
            res.end(dynamicContent);
            return;
          }
        }
        next();
      });
    },
  };
}

// Determine base path for GitHub Pages or custom domain:
// 1. If public/CNAME exists, custom domain is used -> '/'
// 2. Explicit non-empty VITEPRESS_BASE environment variable -> normalized with leading & trailing slashes
// 3. In GitHub Actions without a custom CNAME:
//    - if repo is <user>.github.io -> '/'
//    - otherwise -> '/<repo>/' (e.g. '/database/')
// 4. Otherwise default to '/' (local development or preview)
const hasCustomDomain = fs.existsSync(path.resolve(publicDir, 'CNAME'));

function resolveBase(): string {
  if (hasCustomDomain) {
    return '/';
  }

  const envBase = process.env.VITEPRESS_BASE?.trim();
  if (envBase) {
    const withLeading = envBase.startsWith('/') ? envBase : `/${envBase}`;
    return withLeading.endsWith('/') ? withLeading : `${withLeading}/`;
  }

  if (process.env.GITHUB_ACTIONS) {
    if (process.env.GITHUB_REPOSITORY) {
      const [owner, repo] = process.env.GITHUB_REPOSITORY.split('/');
      if (repo && owner && repo.toLowerCase() === `${owner.toLowerCase()}.github.io`) {
        return '/';
      }
      if (repo) {
        return `/${repo}/`;
      }
    }
    return '/database/';
  }

  return '/';
}

const base = resolveBase();

export default defineConfig({
  title: 'Tueen Database',
  description: 'The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots.',
  base,
  cleanUrls: true,
  lastUpdated: true,

  vite: {
    plugins: [llmsPlugin()],
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#3eaf7c' }],
  ],

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Tueen Database',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Architecture', link: '/guide/architecture' },
      { text: 'Reference', link: '/reference/entities' },
      { text: 'CLI', link: '/guide/cli-tooling' },
      { text: '🤖 LLMs', link: '/llms' },
      { text: 'v1.0.0-alpha.1', link: 'https://github.com/tueen/database' },
    ],

    sidebar: [
      {
        text: '🚀 Getting Started',
        items: [
          { text: 'Introduction & Setup', link: '/guide/getting-started' },
          { text: 'Architecture & Engine', link: '/guide/architecture' },
          { text: 'Zero-Config Auto-Migrations', link: '/guide/zero-config-migrations' },
          { text: 'Multi-Database Setup', link: '/guide/multi-database' },
        ],
      },
      {
        text: '💬 Bot Domain & Essentials',
        items: [
          { text: 'Chats & Contacts', link: '/guide/chats-and-contacts' },
          { text: 'Wallets & Treasury Ledger', link: '/guide/wallets-and-treasury' },
          { text: 'Orders, Billing & Coupons', link: '/guide/orders-and-coupons' },
          { text: 'Subscriptions & VIP', link: '/guide/subscriptions' },
          { text: 'Mini App Sessions & Auth', link: '/guide/mini-app-sessions' },
          { text: 'High-Volume Broadcasts', link: '/guide/broadcast-engine' },
          { text: 'Content Entities & CMS', link: '/guide/content-entities' },
          { text: 'Options & State Cache', link: '/guide/options-and-cache' },
          { text: 'Audit Trails & Histories', link: '/guide/audit-histories' },
        ],
      },
      {
        text: '🛡️ Advanced & Operations',
        items: [
          { text: 'Caching & Performance', link: '/guide/caching-and-performance' },
          { text: 'CLI Tooling (tueen-db)', link: '/guide/cli-tooling' },
          { text: 'Extending Entities & Schemas', link: '/guide/extending' },
          { text: 'Symfony DI & Events', link: '/guide/symfony-integration' },
        ],
      },
      {
        text: '📖 API Catalog & Reference',
        items: [
          { text: 'Entity Models Catalog', link: '/reference/entities' },
          { text: 'Domain Repositories', link: '/reference/repositories' },
          { text: 'Enums & Value Objects', link: '/reference/enums' },
        ],
      },
      {
        text: '🤖 LLMs & AI Integration',
        items: [
          { text: 'Context Hub & Skills', link: '/llms' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/tueen/database' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 Tueen Ecosystem Team',
    },

    search: {
      provider: 'local',
    },
  },
});
