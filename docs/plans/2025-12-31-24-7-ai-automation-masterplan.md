# 24/7 AI Automation Masterplan

**Created:** 2025-12-31
**Status:** Approved
**Timeline:** 8 weeks (Full Architecture)

---

## Executive Summary

**Thesis:** More tokens = more progress. Maximize ROI on Claude Code Max + z.ai subscriptions by running AI systems 24/7.

**Core Problem:** System too dependent on human writing prompts and directing work.

**Solution:** Supervised autonomy — AI proposes work, human approves via Telegram + Dashboard, AI executes.

---

## Projects in Scope

| Project | Type | Revenue Model |
|---------|------|---------------|
| **Scrappa** | API marketplace | RapidAPI sales (later Apify) |
| **LTO2** | Video downloader backend | High-traffic user base |
| **Rezensionsheld** | Review management | Google Maps, Kununu, Trustpilot, DMCA |
| **Claude Runner** | Orchestration hub | Internal (extends to Mission Control) |

*Dynamic priority list — projects can be added/removed at any time.*

---

## Value Streams (All Equally Important)

1. **Revenue Generation** — Build new APIs, publish to marketplaces
2. **Quality & Reliability** — Monitor errors, fix issues proactively
3. **User Growth** — SEO, features that benefit users
4. **Research & Discovery** — Find opportunities, trends, gaps

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLAUDE RUNNER                                 │
│                    (Extended Mission Control)                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐ │
│  │  Projects   │  │   Task      │  │  Research   │  │  Analytics │ │
│  │  Registry   │  │   Queue     │  │  Pipeline   │  │    Hub     │ │
│  └─────────────┘  └─────────────┘  └─────────────┘  └────────────┘ │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                     MCP Server Ecosystem                        ││
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌──────────┐ ││
│  │  │ Sentry  │ │ Google  │ │RapidAPI │ │  Ploi   │ │ Project  │ ││
│  │  │  MCP    │ │ Search  │ │(Playwrt)│ │  CLI    │ │ DB Reader│ ││
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └──────────┘ ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    AI Agent Layer                               ││
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          ││
│  │  │   Auditor    │  │  Researcher  │  │   Builder    │          ││
│  │  │   Agent      │  │    Agent     │  │    Agent     │          ││
│  │  └──────────────┘  └──────────────┘  └──────────────┘          ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                      │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
      ┌──────────────┐           ┌──────────────┐
      │   Telegram   │           │   Dashboard  │
      │     Bot      │           │    Queue     │
      └──────────────┘           └──────────────┘
```

---

## CLI & MCP Server Ecosystem

### Available Now

| Tool | Type | Purpose |
|------|------|---------|
| **Scrappa MCP** | MCP | Multi-API platform (see below) |
| GitHub MCP | MCP | Repo management, PRs, issues |
| Playwright MCP | MCP | Browser automation, RapidAPI testing, scraping |
| Ploi CLI | CLI | Server/site management, deploys |
| Laravel Boost | MCP | Artisan, tinker, docs, DB queries |
| Sentry CLI | CLI | Error tracking (configured) |
| yt-dlp | CLI | Video downloading (1000+ sources) |

### Scrappa MCP (Multi-API Platform)

**Core APIs for Projects:**

| API | Endpoints | Project Use |
|-----|-----------|-------------|
| Google Maps | `advanced_search`, `google_reviews`, `google_business_details` | Rezensionsheld |
| Kununu | `kununu_search`, `kununu_reviews`, `kununu_profiles` | Rezensionsheld |
| Trustpilot | `trustpilot_company_search`, `trustpilot_company_reviews` | Rezensionsheld |
| TrustedShops | `trustedshops_search`, `trustedshops_reviews` | Rezensionsheld expansion |
| YouTube | Full suite (14 endpoints) | LTO2 video metadata |
| Google Search | `search`, `google_search_light`, `autocomplete` | Research pipeline |
| Google News | `google_news` | Research pipeline |

**Additional APIs (Scrappa Marketplace):**

| API | Endpoints | Status |
|-----|-----------|--------|
| Amazon | `amazon_search`, `amazon_product`, `amazon_reviews` | Available |
| Indeed Jobs | `indeed_jobs` | Available |
| Google Flights | Full suite (6 endpoints) | Available |
| Google Jobs | `google_jobs` | Available |
| Vinted | Full suite (9 endpoints) | Available |
| LinkedIn | `linkedin_profile`, `linkedin_company`, `linkedin_search` | Available |
| Immobilienscout24 | Property search & details | Available |
| SimilarWeb | Website analytics | Available |
| Brave Search | Privacy-focused search | Available |
| Bing Search | `bing_search` | Available |
| Google Translate | `google_translate_api` | Available |
| Google Images | `google_images` | Available |
| Google Videos | `google_videos` | Available |

### Custom MCP Servers (Built)

| Server | Location | Tools |
|--------|----------|-------|
| Sentry MCP | `mcp-servers/sentry-mcp/` | Issue management, stats |
| yt-dlp Health | `mcp-servers/ytdlp-health-mcp/` | Extractor health checks |
| Project DB Reader | `mcp-servers/project-db-reader/` | Multi-DB analytics queries |

### Coming Soon

### Need to Build

| Tool | Type | Approach | Phase |
|------|------|----------|-------|
| Sentry MCP | MCP | Wrap Sentry CLI | 1 |
| Project DB Reader | MCP | Multi-DB connector | 1 |
| yt-dlp Health Monitor | MCP | Wrap yt-dlp, test sources | 1 |
| Telegram Bot | Integration | Bot API | 2 |
| Review Platform Scraper | MCP | Uses Playwright | 3 |
| RapidAPI Scanner | MCP | Uses Playwright | 3 |
| Social Listener | MCP | Reddit, HN, Twitter | 3 |

---

## Telegram Bot Configuration

**Bot Username:** @claude_runner_marin_bot (or similar)
**API Token:** `8556995413:AAFwDCyM5ggIFjvQR76kLfwrXaOOR9YPaKU`
**Chat ID:** `395816524`

### Commands

```
/status              - Get system overview
/pending             - List pending approvals
/approve <id>        - Approve task
/reject <id> <reason>- Reject with feedback
/priorities          - Show/adjust project priorities
/pause               - Pause autonomous work
/resume              - Resume autonomous work
```

### Notification Types

```
🔔 New proposal: "Add YouTube Shorts endpoint to Scrappa"
   Priority: High | Est. tokens: 50k
   [Approve] [Reject] [Details]

⚠️ Sentry alert: 47 new errors in LTO2 (Instagram extractor)
   [Create Fix Task] [Snooze 24h] [Ignore]

✅ Task #89 completed: "Fix Trustpilot scraper timeout"
   3 files changed, tests passing
   [View PR] [Deploy to Staging]
```

---

## Phased Implementation Plan

### Phase 1: Observability Foundation (Week 1-2)

*"You can't automate what you can't see"*

| ID | Task | Deliverable |
|----|------|-------------|
| 1.1 | Analytics Audit | Report: what's tracked, what's missing per project |
| 1.2 | Sentry MCP | AI can query errors, trends, filter by project |
| 1.3 | Project DB Reader MCP | AI can query analytics tables across all projects |
| 1.4 | yt-dlp Health Monitor | Daily health check of top 20 video sources |
| 1.5 | Unified Dashboard | Single view: errors, health, metrics per project |

**Exit Criteria:** AI can answer "What's broken?" and "What are we not measuring?"

---

### Phase 2: Communication Layer (Week 2-3)

*"Supervised autonomy needs a communication channel"*

| ID | Task | Deliverable |
|----|------|-------------|
| 2.1 | Telegram Bot Integration | Send/receive messages, inline buttons |
| 2.2 | Task Queue System | Pending/Approved/Running/Complete states |
| 2.3 | Proposal Templates | Consistent: title, priority, effort, rationale |
| 2.4 | Approval Flow | Telegram + Dashboard both work, synced state |
| 2.5 | Notification Rules | Priority routing, batching, quiet hours |

**Exit Criteria:** Approve/reject proposals from Telegram and Dashboard.

---

### Phase 3: Research Pipeline (Week 3-4)

*"Find opportunities while you sleep"*

| ID | Task | Deliverable |
|----|------|-------------|
| 3.1 | Google Search MCP | SEO keywords, rankings, competitor tracking |
| 3.2 | RapidAPI Scanner | Trending APIs, gaps, competitor pricing |
| 3.3 | Review Platform Monitor | Detect changes in Google Maps, Kununu, Trustpilot |
| 3.4 | Social Listener | Pain points, feature requests from Reddit/HN/Twitter |
| 3.5 | Research Agent | Daily: scan → analyze → propose |

**Exit Criteria:** AI generates 3-5 actionable proposals per day.

---

### Phase 4: Autonomous Work Loop (Week 5-6)

*"AI works, you approve, it executes"*

| ID | Task | Deliverable |
|----|------|-------------|
| 4.1 | Builder Agent | Execute approved tasks in workspaces |
| 4.2 | Auditor Agent | Continuous codebase health checks |
| 4.3 | Priority System | Dynamic project priorities |
| 4.4 | Token Budget Manager | Track Claude + z.ai usage |
| 4.5 | Work Scheduler | 24/7 task scheduling |

**Exit Criteria:** System runs autonomously, executes approved tasks.

---

### Phase 5: Intelligence & Optimization (Week 7-8)

*"Learn and improve"*

| ID | Task | Deliverable |
|----|------|-------------|
| 5.1 | Success Tracking | Learn which proposals you approve/reject |
| 5.2 | Revenue Attribution | Link AI work to revenue outcomes |
| 5.3 | Analytics Improvements | Close gaps found in Phase 1 |
| 5.4 | Playbook Library | Reusable workflows for common tasks |
| 5.5 | Self-Improvement Loop | AI suggests system improvements |

**Exit Criteria:** System learns from feedback, tracks ROI.

---

## Timeline

```
Week 1-2: [████████ Phase 1: Observability ████████]
Week 2-3:          [████████ Phase 2: Communication ████████]
Week 3-4:                   [████████ Phase 3: Research ████████]
Week 5-6:                            [████████ Phase 4: Autonomy ████████]
Week 7-8:                                     [████████ Phase 5: Intelligence ████████]

         ──────────────────────────────────────────────────────────►
         Audit → Talk → Research → Execute → Learn
```

---

## Autonomy Model: Supervised

**How it works:**

1. AI agents continuously research, monitor, audit
2. AI creates **proposals** for work
3. Proposals go to **queue** (Dashboard + Telegram notification)
4. Human **approves/rejects** via Telegram (quick) or Dashboard (detailed)
5. Approved work goes to **Builder Agent**
6. Builder executes in isolated workspace
7. Results reported back for review

**Guardrails:**
- No production deploys without approval
- No spending money without approval
- No deleting data without approval
- No external communications without approval

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Proposals generated per day | 3-5 |
| Approval turnaround time | < 4 hours |
| Tasks executed per week | 10-20 |
| Errors detected before users | > 80% |
| New APIs published per month | 4-8 |
| Token utilization (Claude + z.ai) | > 70% of quota |

---

## Next Steps

1. **Commit this plan** to Claude Runner repo
2. **Start Phase 1.1** — Analytics Audit across all projects
3. **Set up Telegram bot** in Claude Runner
4. **Build Sentry MCP** wrapper

---

*This plan is a living document. Update as we learn.*
