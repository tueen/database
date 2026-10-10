---
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
Add the **llms.txt** URL (`/llms.txt`) to Cursor Docs (`Cursor Settings > Features > Docs`), or extract the **Skill Package (.zip)** into your project's `.agents/skills/` directory.
:::

::: tip 🧠 Claude Projects & ChatGPT
Upload `/llms-full.txt` directly as Project Knowledge, or click **Copy AI Agent Skill** above and paste into your Custom Instructions.
:::

::: tip 🐙 GitHub Repository & Deep Inspection
If the documentation does not fully cover your scenario or you need to inspect raw source code, entity models, repository methods, test suites, or open issues, explore the repository on GitHub:
👉 **[github.com/tueen/database](https://github.com/tueen/database)**
:::

---

## 🏛️ Core Architectural Tenets for AI

When generating code for `tueen/database`, models MUST follow these principles:

- **PHP 8.4+ Strict Types:** Always start files with `declare(strict_types=1);`.
- **Clean Asymmetric Visibility:** Use `public private(set)` for primary keys and creation dates (e.g. `public private(set) int $id;`).
- **Property Hooks:** Use `get => ...` and `set => ...` for validation, normalization, and computed properties instead of repetitive getters/setters.
- **Telegram Signed 64-bit Chat IDs:** Telegram user IDs are positive integers, but group, supergroup, and channel IDs are negative numbers (e.g. `-1001234567890`). Never use unsigned for `chat_id`!
- **Username Normalization:** Automatically strip leading `@` using backed property hooks (`set (?string $value) => $value !== null ? ltrim(trim($value), '@') : null;`).
- **Zero-Config Lazy Migrations:** The engine automatically inspects and synchronizes schema diffs on boot/first access via `AutoMigrator` and `SchemaSynchronizer` (WordPress `dbDelta` style).
- **Ledger & Arbitrary-Precision Math:** All balances and currencies are stored as `DECIMAL(36, 18)` strings. Never perform floating-point math; always use BCMath (`bcadd`, `bcsub`, `bccomp`, `bcmul`, `bcdiv`).
- **Multi-Database Agnostic:** Support SQLite (`:memory:` and file), MySQL 8+, MariaDB, and PostgreSQL.
- **Repository Inspection:** Check [github.com/tueen/database](https://github.com/tueen/database) for edge cases, entity mappings, repository implementations, and source code details.

---

## 📄 Machine-Readable Assets

| Asset | Format | Purpose | Link |
| :--- | :--- | :--- | :--- |
| **Agent Skill Package** | `.zip` archive | Offline skill with `SKILL.md` and complete `docs/` | [Download ZIP](/tueen-database.zip) |
| **Standard LLM Summary** | `llms.txt` | [llmstxt.org](https://llmstxt.org) structured outline & docs map | [View `/llms.txt`](/llms.txt) |
| **Consolidated Context** | `llms-full.txt` | Full single-file markdown for context ingestion | [View `/llms-full.txt`](/llms-full.txt) |
| **GitHub Repository** | Source Code & Tests | Official repository for source inspection, unit tests & issues | [github.com/tueen/database](https://github.com/tueen/database) |
