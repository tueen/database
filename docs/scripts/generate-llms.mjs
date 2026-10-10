import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import JSZip from 'jszip';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const docsDir = path.resolve(__dirname, '..');
const publicDir = path.resolve(docsDir, 'public');
const llmsOutputFile = path.resolve(publicDir, 'llms.txt');
const llmsFullOutputFile = path.resolve(publicDir, 'llms-full.txt');
const zipOutputFile = path.resolve(publicDir, 'tueen-database.zip');
const llmsPageFile = path.resolve(docsDir, 'llms.md');

// Determine canonical online documentation site URL
export function getCanonicalSiteUrl() {
  if (process.env.DOCS_URL) return process.env.DOCS_URL.replace(/\/$/, '');
  if (process.env.SITE_URL) return process.env.SITE_URL.replace(/\/$/, '');

  const cnameFile = path.resolve(publicDir, 'CNAME');
  if (fs.existsSync(cnameFile)) {
    const cname = fs.readFileSync(cnameFile, 'utf-8').trim();
    if (cname) return `https://${cname}`;
  }

  if (process.env.GITHUB_REPOSITORY) {
    const [owner, repo] = process.env.GITHUB_REPOSITORY.split('/');
    if (repo && owner) {
      if (repo.toLowerCase() === `${owner.toLowerCase()}.github.io`) {
        return `https://${owner}.github.io`;
      }
      return `https://${owner}.github.io/${repo}`;
    }
  }

  return 'https://database.tueen.dev';
}

const siteUrl = getCanonicalSiteUrl();

// Helper to slugify headings to match VitePress anchor IDs
function slugify(text) {
  return text
    .trim()
    .toLowerCase()
    .replace(/<[^>]+>/g, '') // remove HTML tags
    .replace(/[^\w\s-]/g, '') // remove non-word chars except space & hyphen
    .replace(/\s+/g, '-') // spaces to hyphen
    .replace(/--+/g, '-') // collapse hyphens
    .replace(/^-+|-+$/g, ''); // trim hyphens
}

// Ordered structure based on documentation sidebar
const docSections = [
  {
    category: '🚀 Getting Started',
    files: [
      { file: 'guide/getting-started.md', relLink: 'guide/getting-started' },
      { file: 'guide/architecture.md', relLink: 'guide/architecture' },
      { file: 'guide/zero-config-migrations.md', relLink: 'guide/zero-config-migrations' },
      { file: 'guide/multi-database.md', relLink: 'guide/multi-database' },
    ],
  },
  {
    category: '💬 Bot Domain & Essentials',
    files: [
      { file: 'guide/chats-and-contacts.md', relLink: 'guide/chats-and-contacts' },
      { file: 'guide/wallets-and-treasury.md', relLink: 'guide/wallets-and-treasury' },
      { file: 'guide/orders-and-coupons.md', relLink: 'guide/orders-and-coupons' },
      { file: 'guide/subscriptions.md', relLink: 'guide/subscriptions' },
      { file: 'guide/mini-app-sessions.md', relLink: 'guide/mini-app-sessions' },
      { file: 'guide/broadcast-engine.md', relLink: 'guide/broadcast-engine' },
      { file: 'guide/content-entities.md', relLink: 'guide/content-entities' },
      { file: 'guide/options-and-cache.md', relLink: 'guide/options-and-cache' },
      { file: 'guide/audit-histories.md', relLink: 'guide/audit-histories' },
    ],
  },
  {
    category: '🛡️ Advanced & Operations',
    files: [
      { file: 'guide/caching-and-performance.md', relLink: 'guide/caching-and-performance' },
      { file: 'guide/cli-tooling.md', relLink: 'guide/cli-tooling' },
      { file: 'guide/extending.md', relLink: 'guide/extending' },
      { file: 'guide/symfony-integration.md', relLink: 'guide/symfony-integration' },
    ],
  },
  {
    category: '📖 API Catalog & Reference',
    files: [
      { file: 'reference/entities.md', relLink: 'reference/entities' },
      { file: 'reference/repositories.md', relLink: 'reference/repositories' },
      { file: 'reference/enums.md', relLink: 'reference/enums' },
    ],
  },
];

// Extract metadata and headings from markdown
function parseMarkdownDoc(filePath, relLink) {
  if (!fs.existsSync(filePath)) {
    return null;
  }

  const rawContent = fs.readFileSync(filePath, 'utf-8');
  const lines = rawContent.split(/\r?\n/);

  let title = '';
  let description = '';
  const headings = [];

  let inCodeBlock = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    if (line.trim().startsWith('```')) {
      inCodeBlock = !inCodeBlock;
      continue;
    }
    if (inCodeBlock) continue;

    // Match H1
    if (!title) {
      const h1Match = line.match(/^#\s+(.+)$/);
      if (h1Match) {
        title = h1Match[1].trim();
        continue;
      }
    }

    // Capture first substantive paragraph as description
    if (title && !description && line.trim() && !line.startsWith('#') && !line.startsWith('<') && !line.startsWith('---') && !line.startsWith('![')) {
      description = line.trim().replace(/[*_`]/g, '');
    }

    // Match H2 and H3
    const h2Match = line.match(/^##\s+(.+)$/);
    if (h2Match) {
      const headingText = h2Match[1].trim();
      headings.push({
        level: 2,
        text: headingText,
        anchor: slugify(headingText),
      });
      continue;
    }

    const h3Match = line.match(/^###\s+(.+)$/);
    if (h3Match) {
      const headingText = h3Match[1].trim();
      headings.push({
        level: 3,
        text: headingText,
        anchor: slugify(headingText),
      });
    }
  }

  return {
    title: title || path.basename(filePath, '.md'),
    description,
    headings,
    rawContent,
    relLink,
    filePath,
  };
}

// Generate the standalone SKILL.md packaged inside the zip
function generateSkillContent(parsedSections) {
  let skill = `---
name: tueen-database
description: >-
  The official agent skill for tueen/database (The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots).
  Provides complete architectural guidelines, PHP 8.4+ property hooks, asymmetric visibility,
  zero-config auto-migrations, multi-currency ledger, domain repositories, and complete offline documentation.
---

# Tueen Database Skill & Development Guide

Welcome to the **tueen/database** agent skill. This skill packages the complete offline documentation, architectural rules, and coding standards for developing Telegram bots and standalone applications using modern PHP 8.4+.

---

## 🏛️ Library Identity & Tenets

- **Package Name:** \`tueen/database\`
- **GitHub Repository:** [tueen/database on GitHub](https://github.com/tueen/database)
- **Slogan:** *The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots.*
- **Target Runtime:** PHP 8.4+ (strict types, property hooks \`get => ...\` / \`set => ...\`, asymmetric visibility \`public private(set)\`).
- **Zero-Config Auto-Migration:** Lazy schema synchronization on boot / first access via \`AutoMigrator\` and \`SchemaSynchronizer\` (WordPress \`dbDelta\` style).
- **Financial Precision:** \`DECIMAL(36, 18)\` or \`DECIMAL(40, 18)\` string math using \`bcadd()\`, \`bcsub()\`, \`bccomp()\`, \`bcmul()\`, \`bcdiv()\`. Never native floats!
- **Telegram Domain Requirements:** Signed 64-bit BigInt for chat IDs (positive user IDs, negative supergroup/channel IDs), UTF-8 / utf8mb4 emojis, automatic \`@\` stripping via property hooks.
- **Multi-Database Agnostic:** SQLite (\`:memory:\` and file), MySQL 8+, MariaDB, and PostgreSQL.
- **Online Documentation Base URL:** ${siteUrl}/

> **🔎 Deep Source Inspection & Repository Fallback:**
> If the bundled documentation guides or offline references are not sufficient for your use case, or if you need to inspect raw class implementations, method signatures, test suites, or latest features, refer directly to the official GitHub repository:
> **https://github.com/tueen/database**

---

## 💎 Core Architectural Rules for AI Agents

When writing, refactoring, or generating code using \`tueen/database\`, AI assistants MUST adhere to these rules:

1. **Strict Types & PHP 8.4+ Standards:**
   - Always declare \`declare(strict_types=1);\` at the top of every PHP file.
   - Use asymmetric visibility \`public private(set)\` for entity IDs and immutable timestamps:
     \`\`\`php
     public private(set) int $id;
     public private(set) string $publicId;
     public private(set) \\DateTimeImmutable $createdAt;
     \`\`\`
   - Use property hooks for normalization, validation, and derived states:
     \`\`\`php
     public ?string $username {
         set (?string $value) => $value !== null && ($val = strtolower(ltrim(trim($value), '@'))) !== '' ? $val : null;
     }

     public bool $isActive {
         get => $this->expiresAt > new \\DateTimeImmutable();
     }
     \`\`\`
   - Never use parentheses when chaining new instances: \`new DateTimeImmutable()->modify(...)\`.

2. **Zero-Config Lazy Migrations (WordPress-Style):**
   - The engine automatically inspects and synchronizes schema diffs on boot/first access via \`AutoMigrator\` and \`SchemaSynchronizer\`.
   - End-users must never be forced to run manual CLI migration commands to get a bot up and running.

3. **Telegram Chat ID Sign:**
   - Chat IDs are **signed 64-bit BigInts** (user IDs > 0, group/supergroup/channel IDs < 0). Always map them as \`Types::BIGINT\` and never unsigned.

4. **Multi-Currency Ledger & Financial Math:**
   - All balances and monetary values are stored as strings using \`DECIMAL(36, 18)\` or \`DECIMAL(40, 18)\`.
   - Never perform floating-point math on currencies. Always use BCMath (\`bcadd\`, \`bcsub\`, \`bccomp\`, \`bcmul\`, \`bcdiv\`).
   - Use \`WalletRepository::credit()\`, \`debit()\`, or \`transfer()\` inside transactional wrappers.

5. **Repository & Source Inspection Fallback:**
   - If the documentation or guides do not cover a specific edge case or internal implementation details, inspect the source code, unit tests, and examples directly on GitHub: https://github.com/tueen/database

---

## 📚 Complete Offline Documentation Map

All documentation guides are bundled alongside this skill in the \`./docs/\` folder:

`;

  for (const sec of parsedSections) {
    skill += `\n### ${sec.category}\n`;
    for (const item of sec.items) {
      const localDocPath = `docs/${item.relLink}.md`;
      skill += `- [${item.title}](./${localDocPath}): ${item.description || item.title}\n`;
      if (item.headings.length > 0) {
        for (const h of item.headings) {
          const indent = h.level === 3 ? '    * ' : '  - ';
          skill += `${indent}${h.text}\n`;
        }
      }
    }
  }

  skill += `\n---\n*Generated automatically from the official tueen/database documentation suite.*\n`;
  return skill;
}

// Safe file write helpers
function safeWrite(dest, content) {
  if (fs.existsSync(dest)) {
    const existing = fs.readFileSync(dest, 'utf-8');
    if (existing === content) return false;
  }
  fs.writeFileSync(dest, content, 'utf-8');
  return true;
}

function safeWriteBuffer(dest, buffer) {
  if (fs.existsSync(dest)) {
    const existing = fs.readFileSync(dest);
    if (existing.equals(buffer)) return false;
  }
  fs.writeFileSync(dest, buffer);
  return true;
}

export async function generateLlms() {
  fs.mkdirSync(publicDir, { recursive: true });

  const parsedSections = [];
  let fullConcatenatedContent = `# tueen/database — Full Documentation Context
# The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots (PHP 8.4+)
# Official Repository: https://github.com/tueen/database
# Documentation Base URL: ${siteUrl}/
#
# Note for AI Models: If any detail, method signature, or edge-case behavior is not
# fully covered in this documentation, consult the official GitHub repository:
# https://github.com/tueen/database
\n`;

  for (const section of docSections) {
    const items = [];
    for (const item of section.files) {
      const absPath = path.resolve(docsDir, item.file);
      const parsed = parseMarkdownDoc(absPath, item.relLink);
      if (parsed) {
        items.push(parsed);

        // Clean raw content for llms-full.txt
        let cleanedContent = parsed.rawContent
          .replace(/<Mermaid\s+code="([^"]+)"\s*\/>/g, (_, code) => {
            return '```mermaid\n' + decodeURIComponent(code) + '\n```';
          })
          .replace(/<p align="center"[^>]*>[\s\S]*?<\/p>/g, '');

        fullConcatenatedContent += `\n\n# ==============================================================================\n`;
        fullConcatenatedContent += `# File: /${parsed.relLink}\n`;
        fullConcatenatedContent += `# Section: ${section.category} > ${parsed.title}\n`;
        fullConcatenatedContent += `# ==============================================================================\n\n`;
        fullConcatenatedContent += cleanedContent.trim() + '\n';
      }
    }
    parsedSections.push({
      category: section.category,
      items,
    });
  }

  // 1. Build llms.txt (llmstxt.org specification)
  let llmsTxt = `# tueen/database
> The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots (PHP 8.4+)

Package Name: tueen/database
GitHub Repository: https://github.com/tueen/database
Base URL: ${siteUrl}/
Documentation Root: ${siteUrl}/guide/
Architecture: Zero-Config WordPress-style auto-migrations, PHP 8.4 property hooks, signed 64-bit BigInt chat IDs, DECIMAL(36, 18) BCMath ledgers.

> Note: All documentation links below are relative to \`Base URL\`. If the documentation or guides are not sufficient for your task, inspect the source code, tests, and examples directly on GitHub: https://github.com/tueen/database

## AI Agent Skill & Coding Guidelines
When writing code for \`tueen/database\`, AI assistants and agents MUST adhere to these architectural rules:
1. Always enable strict types: \`declare(strict_types=1);\`.
2. Property access & visibility: Use asymmetric visibility \`public private(set)\` for entity primary keys and timestamps.
3. Property hooks: Prefer property hooks (\`get => ...\`, \`set => ...\`) over repetitive getter/setter methods.
4. Telegram Chat IDs: Chat IDs are signed 64-bit integers (user IDs > 0, groups/channels < 0). Never map chat_id as unsigned!
5. Multi-Currency Ledger: Monetary values are stored as \`DECIMAL(36, 18)\` strings. Never use native float arithmetic; always use BCMath.
6. Zero-Config Migrations: Automatic lazy schema synchronization on boot without requiring manual CLI migration runs.
7. Multi-Database Support: Ensure compatibility with SQLite (:memory: and file), MySQL 8+, MariaDB, and PostgreSQL.
8. Repository Inspection Fallback: If documentation is insufficient or missing edge-case details, check the GitHub repository at https://github.com/tueen/database for full source code, types, and test suites.

## Complete Documentation Map & Topic Outline
`;

  for (const sec of parsedSections) {
    llmsTxt += `\n### ${sec.category}\n`;
    for (const item of sec.items) {
      llmsTxt += `- [${item.title}](/${item.relLink}): ${item.description || item.title}\n`;
      if (item.headings.length > 0) {
        for (const h of item.headings) {
          const indent = h.level === 3 ? '    * ' : '  - ';
          llmsTxt += `${indent}[${h.text}](/${item.relLink}#${h.anchor})\n`;
        }
      }
    }
  }

  llmsTxt += `\n## Downloadable Packages, Full Context & Source Code\n`;
  llmsTxt += `- [Download Complete Agent Skill ZIP](/tueen-database.zip): Standalone skill folder (tueen-database/) with SKILL.md and offline docs/.\n`;
  llmsTxt += `- [Full Documentation (Single Context)](/llms-full.txt): Complete, concatenated markdown documentation for one-shot LLM ingestion.\n`;
  llmsTxt += `- [GitHub Repository (Source Code, Tests & Examples)](https://github.com/tueen/database): Official GitHub repository for source code inspection, test suites, and issue tracking when documentation is insufficient.\n`;

  // 2. Build ZIP Archive (tueen-database.zip)
  const zip = new JSZip();
  const zipRoot = zip.folder('tueen-database');

  // Put SKILL.md at the root of the folder
  const skillFileContent = generateSkillContent(parsedSections);
  zipRoot.file('SKILL.md', skillFileContent);

  // Put all docs into docs/ inside the zip
  const zipDocs = zipRoot.folder('docs');
  for (const sec of parsedSections) {
    for (const item of sec.items) {
      const fileName = `${item.relLink}.md`;

      // Clean custom tags so offline markdown is standard
      const cleanRaw = item.rawContent.replace(/<Mermaid\s+code="([^"]+)"\s*\/>/g, (_, code) => {
        return '```mermaid\n' + decodeURIComponent(code) + '\n```';
      });

      zipDocs.file(fileName, cleanRaw);
    }
  }

  const zipBuffer = await zip.generateAsync({
    type: 'nodebuffer',
    compression: 'DEFLATE',
    compressionOptions: { level: 9 },
  });

  // 3. Build docs/llms.md
  let llmsMd = `---
title: LLMs & AI Context Hub
description: Official machine-readable context, prompt skills, and offline packages for AI coding assistants.
---

<script setup>
import LlmSkillCopy from './.vitepress/theme/components/LlmSkillCopy.vue'
</script>

# 🤖 LLM & AI Context Hub

Official machine-readable documentation, prompt skills, and offline packages for Large Language Models and AI coding assistants (**Cursor**, **Claude**, **ChatGPT**, **Copilot**, **Antigravity**).

<LlmSkillCopy />

---

## ⚡ Quick Integration

::: tip 💡 Cursor & VS Code
Add the **llms.txt** URL (\`/llms.txt\`) to Cursor Docs (\`Cursor Settings > Features > Docs\`), or extract the **Skill Package (.zip)** into your project's \`.agents/skills/\` directory.
:::

::: tip 🧠 Claude Projects & ChatGPT
Upload \`/llms-full.txt\` directly as Project Knowledge, or click **Copy AI Agent Skill** above and paste into your Custom Instructions.
:::

::: tip 🐙 GitHub Repository & Deep Inspection
If the documentation does not fully cover your scenario or you need to inspect raw source code, entity models, repository methods, test suites, or open issues, explore the repository on GitHub:
👉 **[github.com/tueen/database](https://github.com/tueen/database)**
:::

---

## 🏛️ Core Architectural Tenets for AI

When generating code for \`tueen/database\`, models MUST follow these principles:

- **PHP 8.4+ Strict Types:** Always start files with \`declare(strict_types=1);\`.
- **Clean Asymmetric Visibility:** Use \`public private(set)\` for primary keys and creation dates (e.g. \`public private(set) int $id;\`).
- **Property Hooks:** Use \`get => ...\` and \`set => ...\` for validation, normalization, and computed properties instead of repetitive getters/setters.
- **Telegram Signed 64-bit Chat IDs:** Telegram user IDs are positive integers, but group, supergroup, and channel IDs are negative numbers (e.g. \`-1001234567890\`). Never use unsigned for \`chat_id\`!
- **Username Normalization:** Automatically strip leading \`@\` using backed property hooks (\`set (?string $value) => $value !== null ? ltrim(trim($value), '@') : null;\`).
- **Zero-Config Lazy Migrations:** The engine automatically inspects and synchronizes schema diffs on boot/first access via \`AutoMigrator\` and \`SchemaSynchronizer\` (WordPress \`dbDelta\` style).
- **Ledger & Arbitrary-Precision Math:** All balances and currencies are stored as \`DECIMAL(36, 18)\` strings. Never perform floating-point math; always use BCMath (\`bcadd\`, \`bcsub\`, \`bccomp\`, \`bcmul\`, \`bcdiv\`).
- **Multi-Database Agnostic:** Support SQLite (\`:memory:\` and file), MySQL 8+, MariaDB, and PostgreSQL.
- **Repository Inspection:** Check [github.com/tueen/database](https://github.com/tueen/database) for edge cases, entity mappings, repository implementations, and source code details.

---

## 📄 Machine-Readable Assets

| Asset | Format | Purpose | Link |
| :--- | :--- | :--- | :--- |
| **Agent Skill Package** | \`.zip\` archive | Offline skill with \`SKILL.md\` and complete \`docs/\` | [Download ZIP](/tueen-database.zip) |
| **Standard LLM Summary** | \`llms.txt\` | [llmstxt.org](https://llmstxt.org) structured outline & docs map | [View \`/llms.txt\`](/llms.txt) |
| **Consolidated Context** | \`llms-full.txt\` | Full single-file markdown for context ingestion | [View \`/llms-full.txt\`](/llms-full.txt) |
| **GitHub Repository** | Source Code & Tests | Official repository for source inspection, unit tests & issues | [github.com/tueen/database](https://github.com/tueen/database) |
`;

  // Safely write text and buffer outputs
  safeWrite(llmsOutputFile, llmsTxt);
  safeWrite(llmsFullOutputFile, fullConcatenatedContent);
  safeWrite(llmsPageFile, llmsMd);
  safeWriteBuffer(zipOutputFile, zipBuffer);

  console.log('✅ Generated llms.txt, llms-full.txt, llms.md, and tueen-database.zip successfully.');
}

// Direct execution
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  generateLlms();
}
