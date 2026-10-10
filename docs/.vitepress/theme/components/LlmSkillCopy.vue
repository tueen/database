<template>
  <div class="llm-hub-card">
    <div class="llm-hub-header">
      <div class="llm-hub-icon">🤖</div>
      <div class="llm-hub-info">
        <h3 class="llm-hub-title">Agent Skill & Context Hub for LLMs</h3>
        <p class="llm-hub-subtitle">
          Feed this context to Claude, Cursor, ChatGPT, Antigravity, or Copilot for zero-hallucination code generation.
        </p>
      </div>
    </div>

    <div class="llm-hub-actions">
      <a
        :href="resolvedZipPath"
        download="tueen-database.zip"
        class="llm-btn zip-download"
      >
        <span class="btn-icon">📦</span>
        <span>Download Skill (.zip)</span>
      </a>

      <button
        class="llm-btn primary"
        @click="copySkillPrompt"
        :class="{ active: copiedSkill }"
      >
        <span class="btn-icon">{{ copiedSkill ? '✓' : '📋' }}</span>
        <span>{{ copiedSkill ? 'Skill Copied!' : 'Copy AI Agent Skill' }}</span>
      </button>

      <button
        class="llm-btn secondary"
        @click="copyUrl('llms.txt')"
        :class="{ active: copiedTxtUrl }"
      >
        <span class="btn-icon">{{ copiedTxtUrl ? '✓' : '🔗' }}</span>
        <span>{{ copiedTxtUrl ? 'URL Copied!' : 'Copy llms.txt URL' }}</span>
      </button>

      <button
        class="llm-btn secondary"
        @click="copyUrl('llms-full.txt')"
        :class="{ active: copiedFullUrl }"
      >
        <span class="btn-icon">{{ copiedFullUrl ? '✓' : '📚' }}</span>
        <span>{{ copiedFullUrl ? 'URL Copied!' : 'Copy llms-full.txt URL' }}</span>
      </button>

      <a
        :href="resolvedTxtPath"
        target="_blank"
        rel="noopener noreferrer"
        class="llm-btn outline"
      >
        <span class="btn-icon">↗</span>
        <span>Open Raw llms.txt</span>
      </a>

      <a
        href="https://github.com/tueen/database"
        target="_blank"
        rel="noopener noreferrer"
        class="llm-btn outline"
      >
        <span class="btn-icon">🐙</span>
        <span>GitHub Repo</span>
      </a>
    </div>

    <div class="llm-hub-hint">
      <strong>💡 Tip:</strong> In Cursor, you can add <code>@https://.../llms.txt</code> to your docs, or paste the copied skill prompt directly into your system instructions or <code>.cursorrules</code>. If the documentation or offline context is not sufficient, inspect the source code, entity models, and tests directly on <a href="https://github.com/tueen/database" target="_blank" rel="noopener noreferrer">GitHub</a>.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useData } from 'vitepress'

const { site } = useData()

const copiedSkill = ref(false)
const copiedTxtUrl = ref(false)
const copiedFullUrl = ref(false)

const base = computed(() => site.value.base || '/')

const resolvedTxtPath = computed(() => {
  const cleanBase = base.value.replace(/\/$/, '')
  return `${cleanBase}/llms.txt`
})

const resolvedFullTxtPath = computed(() => {
  const cleanBase = base.value.replace(/\/$/, '')
  return `${cleanBase}/llms-full.txt`
})

const resolvedZipPath = computed(() => {
  const cleanBase = base.value.replace(/\/$/, '')
  return `${cleanBase}/tueen-database.zip`
})

function getAbsoluteUrl(file: string): string {
  if (typeof window === 'undefined') return file
  const cleanBase = base.value.replace(/\/$/, '')
  return `${window.location.origin}${cleanBase}/${file}`
}

async function copyUrl(file: 'llms.txt' | 'llms-full.txt') {
  const url = getAbsoluteUrl(file)
  try {
    await navigator.clipboard.writeText(url)
    if (file === 'llms.txt') {
      copiedTxtUrl.value = true
      setTimeout(() => (copiedTxtUrl.value = false), 2000)
    } else {
      copiedFullUrl.value = true
      setTimeout(() => (copiedFullUrl.value = false), 2000)
    }
  } catch (err) {
    console.error('Failed to copy URL:', err)
  }
}

async function copySkillPrompt() {
  const prompt = `# LLM Skill: tueen/database (The Zero-Config Relational Engine for Modern Telegram Bots)

You are an expert PHP and Database architect specializing in \`tueen/database\`.

## Core Principles:
- PHP 8.4+ only. Always declare strict types: \`declare(strict_types=1);\`.
- Asymmetric visibility: write \`public private(set) int $id;\` for entity IDs and immutable timestamps.
- Property hooks: use hooks for normalized/derived fields (e.g. username sanitization, active status) instead of boilerplate getters/setters.
- Zero-config auto-migrations: automatic lazy schema sync on boot (WordPress dbDelta style) without manual CLI migrations.
- Telegram Domain: Chat IDs are signed 64-bit BigInts (user IDs > 0, groups/channels < 0). Never unsigned! Strip leading '@' on usernames.
- Financial Precision: DECIMAL(36, 18) stored as strings. NEVER use floating-point math for currencies. Always use BCMath (bcadd, bcsub, bccomp, bcmul, bcdiv).
- Multi-database agnostic: Flawless execution across SQLite (:memory: and file), MySQL 8+, MariaDB, and PostgreSQL.

## Documentation & Repository Reference:
- Offline Skill ZIP: ${getAbsoluteUrl('tueen-database.zip')}
- llms.txt summary: ${getAbsoluteUrl('llms.txt')}
- Full Context: ${getAbsoluteUrl('llms-full.txt')}
- GitHub Repository: https://github.com/tueen/database

When asked to design entities, write repositories, or handle bot data transactions, strictly follow modern PHP 8.4 standards and the official tueen/database patterns. If you need deeper inspection of entity models, repositories, tests, or examples, inspect the official GitHub repository: https://github.com/tueen/database`

  try {
    await navigator.clipboard.writeText(prompt)
    copiedSkill.value = true
    setTimeout(() => (copiedSkill.value = false), 2000)
  } catch (err) {
    console.error('Failed to copy skill prompt:', err)
  }
}
</script>

<style scoped>
.llm-hub-card {
  margin: 1.5rem 0 2rem;
  padding: 1.5rem;
  border-radius: 14px;
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-border);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.llm-hub-card:hover {
  border-color: var(--vp-c-brand-1);
}

.llm-hub-header {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.llm-hub-icon {
  font-size: 2rem;
  line-height: 1;
  padding: 0.5rem;
  background: var(--vp-c-bg-mute);
  border-radius: 10px;
  border: 1px solid var(--vp-c-border);
}

.llm-hub-title {
  margin: 0 0 0.25rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--vp-c-text-1);
}

.llm-hub-subtitle {
  margin: 0;
  font-size: 0.9rem;
  color: var(--vp-c-text-2);
  line-height: 1.4;
}

.llm-hub-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.llm-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none !important;
  border: 1px solid transparent;
}

.llm-btn.zip-download {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  border-color: #059669;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.llm-btn.zip-download:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
  transform: translateY(-1px);
}

.llm-btn.primary {
  background: var(--vp-c-brand-1);
  color: #fff;
  border-color: var(--vp-c-brand-1);
}

.llm-btn.primary:hover {
  background: var(--vp-c-brand-2);
}

.llm-btn.primary.active {
  background: #10b981;
  border-color: #10b981;
}

.llm-btn.secondary {
  background: var(--vp-c-bg-mute);
  color: var(--vp-c-text-1);
  border-color: var(--vp-c-border);
}

.llm-btn.secondary:hover {
  border-color: var(--vp-c-brand-1);
  color: var(--vp-c-brand-1);
}

.llm-btn.secondary.active {
  background: #10b981;
  color: #fff;
  border-color: #10b981;
}

.llm-btn.outline {
  background: transparent;
  color: var(--vp-c-text-2);
  border-color: var(--vp-c-border);
}

.llm-btn.outline:hover {
  border-color: var(--vp-c-text-1);
  color: var(--vp-c-text-1);
}

.btn-icon {
  font-size: 1rem;
}

.llm-hub-hint {
  font-size: 0.85rem;
  color: var(--vp-c-text-2);
  padding-top: 0.75rem;
  border-top: 1px dashed var(--vp-c-border);
}

.llm-hub-hint code {
  background: var(--vp-c-bg-mute);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  font-size: 0.8rem;
}
</style>
