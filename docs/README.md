# Laravel Page Speed Documentation Hub

This directory centralizes the architecture notes, tuning guides, and operational playbooks for the package. Use it as a map to choose the depth of information your team needs.

## Audience
- Backend engineers responsible for Blade front-ends or REST APIs running on Laravel.
- SRE and DevOps teams onboarding workloads to Kubernetes, ECS, or VM auto-scaling groups.
- Technical leaders evaluating the ROI of performance optimization initiatives.

## Guide Structure

| Document                | Focus area                                                             |
|-------------------------|------------------------------------------------------------------------|
| `WEB-OPTIMIZATION.md`   | HTML/CSS/JS pipeline, TTFB impact, compatibility, and diagnostics.     |
| `API-OPTIMIZATION.md`   | REST middleware stack, headers, resilience patterns, test strategy.    |
| `API-CACHE.md`          | Cache architecture, dynamic tagging, invalidation semantics.           |
| `CONFIGURATION.md`      | Complete reference for `config/laravel-page-speed.php`.                |
| `API-EXAMPLES.md`       | Domain-specific playbooks (e-commerce, SaaS, microservices).           |
| `PACKAGE-SUMMARY.md`    | Executive briefing, prerequisites, adoption scorecards.                |

## Conventions
- Examples assume Laravel 10 or newer with PHP 8.2/8.3.
- `cache` refers to the store defined in `config/cache.php`; Redis is the recommended driver for advanced tagging.
- Shell snippets note PowerShell (`pwsh`) and Bash variants when behaviour differs.

## Navigation Tips
- New to the package? Start with `PACKAGE-SUMMARY.md`, then move to `CONFIGURATION.md`.
- Focused on HTML delivery? Jump directly to `WEB-OPTIMIZATION.md`.
- Running APIs with strict SLAs? Read `API-OPTIMIZATION.md` and follow up with `API-CACHE.md`.

Documentation pull requests follow the same standards as code: include reproducible examples, link to automated tests when applicable, and describe the rationale behind recommendations.
