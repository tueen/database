---
name: tueen-documentation-standards
description: >-
  Rules, information architecture guidelines, tone standards, and quality checklists for writing
  clear, professional, developer-friendly documentation for tueen/telegram and VitePress suites.
---

# Tueen Documentation Standards & Style Guide

This skill governs the standards, structure, voice, and quality requirements for authoring and maintaining documentation in `tueen/telegram`.

---

## 🏛️ 1. Information Architecture & Learning Progression

Documentation must be structured following an intuitive developer journey, moving logically from beginner onboarding to daily essentials, then to advanced patterns, and finally to API catalogs.

### The Standard Reading Progression:
1. **🚀 Getting Started:** Installation, requirements, client initialization, and sending a first message ("Hello World").
2. **💬 Core Essentials:** The day-to-day tools every bot uses:
   - Invoking methods and understanding response types
   - Building Inline and Reply Keyboards
   - Text formatting and escaping (HTML & MarkdownV2)
   - Handling file uploads and downloads
3. **⚡ Updates & Routing:** Receiving and dispatching Telegram events:
   - Running modes (Polling vs Webhook vs AutoMode)
   - Update routing, command dispatching, and attribute controllers
   - Update helpers and contextual parameters
4. **🧠 Conversational Flows:** Multi-step dialogues, finite state machines, screens, and persistent state storage.
5. **🛡️ Application & Production:** Full application orchestration, middleware, error handling, and testing:
   - Zero-Config App & Web Dashboard
   - Extensible pipeline middlewares
   - Error handling & typed catchers
   - Testing with TelegramFake
   - Enums reference
6. **📖 API Catalog & References:** Exhaustive method signatures and property cards.

> [!IMPORTANT]
> **Separation Rule:** Never mix massive, 400-line API catalogs directly into introductory guides. Keep conceptual guides focused on clear examples; place exhaustive signature listings in dedicated reference sections or cards.

---

## ✍️ 2. Voice, Tone & Writing Guidelines

### The Rule of Clear Engineering Prose
Write like modern, developer-loved documentation (such as Vue.js, Laravel, or Stripe): direct, concise, friendly, and practical.

#### Strict Bans & Tone Don'ts:
* ❌ **No Promotional / AI Fluff:** Avoid hyperbolic buzzwords like *"monolithic God Object"*, *"Royal Majesty"*, *"Bulletproof"*, or overly poetic descriptions.
* ❌ **No Variable Names in Titles:** Never put code tokens or variable names in page titles or sidebar entries (e.g. write `The Telegram Client`, **never** `The Telegram Client ($bot)`).
* ❌ **No Aggressive Callout Headers:** Do not write `::: tip MANDATORY BEST PRACTICE: ALWAYS DO THIS`. Use calm, professional phrasing: `::: tip Recommended: Use PHP Named Arguments`.
* ❌ **No Robotic Repetition:** Avoid restating the same concept across three consecutive paragraphs. Get straight to the code and explanation.
* ❌ **No Premature Jargon in Bullet Summaries:** Never throw unexplained terms (e.g. "Controllers", "DI injection", "State Store") at a reader in introductory chapters without context, code, and links.

#### Tone Do's:
* ✅ **Focus on DX & Clarity:** Explain *why* something is designed a certain way in 1–2 sentences, then immediately show how to use it.
* ✅ **Context-First Best Practices:** Every recommended best practice must provide: (1) a concrete rationale, (2) a working code snippet (or before/after comparison), and (3) a direct link to the relevant in-depth guide.
* ✅ **Provide Next Steps:** At the end of every major guide, include a short "Next Steps" navigation block pointing to the next logical topics.

---

## 💻 3. Code Example Standards

All code snippets presented in documentation must meet the highest engineering standards:

1. **PHP 8.4+ Strict Typing:**
   - Always use named arguments for method calls (`chatId: $chatId, text: '...'`).
   - Use Enums directly (e.g. `ParseMode::HTML`, `UpdateType::Message`).
   - Use PHP 8.4 property hooks or asymmetric visibility correctly without redundant keywords (`private(set)`).
2. **Realistic Business Scenarios:**
   - Use realistic bot interactions (e.g. user registration, sending a report, handling a callback button, order confirmation).
   - Avoid gimmicky jokes or non-standard dummy strings in production code examples.
3. **Self-Contained & Functional:**
   - Import necessary classes (`use Tueen\Telegram\...`) at the beginning of examples so developers can copy-paste without errors.
   - Show how to handle errors or check `$response->ok()` where relevant.

---

## 🧹 4. Structural Hygiene & VitePress Checklist

Whenever adding, renaming, or refactoring documentation pages, follow this mandatory checklist:

- [ ] **Single H1 Title:** Exactly one `# Title` at the very top of each markdown file.
- [ ] **Heading Hierarchy:** Strictly `#` -> `##` -> `###`. Never duplicate headings within the same page.
- [ ] **Sidebar Sync:** Update `getSidebar()` in `docs/.vitepress/config.ts` to place the page in its logical category.
- [ ] **LLM Pipeline Sync:** Update `docSections` in `docs/scripts/generate-llms.mjs` to keep `llms.txt`, `llms-full.txt`, and the offline skill ZIP in sync.
- [ ] **Mermaid Validation:** Verify any Mermaid diagram starts directly after the code fence (e.g. `flowchart TD`) and quotes special characters in node labels.
- [ ] **Production Build Verification:** Always run `npm run docs:build` in `docs/` to confirm that all cross-links, assets, and components render with zero errors.
