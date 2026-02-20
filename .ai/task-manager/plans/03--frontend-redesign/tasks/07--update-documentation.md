---
id: 7
group: "documentation"
dependencies: [1, 2, 3, 4, 5, 6]
status: "completed"
created: "2026-02-19"
skills: ["documentation"]
---
# Update CLAUDE.md Documentation

## Objective
Update `CLAUDE.md` to reflect the new route structure, frontend stack, asset directory structure, and Playwright setup. Ensure all documentation sections accurately describe the redesigned application.

## Skills Required
- `documentation`: Technical documentation writing, Markdown formatting

## Acceptance Criteria
- [ ] "Web Routes" section updated with new routes: `/activities` (calendar), `/activities/pattern`, `/activities/pattern/{signature}`, `/activities/{id}/detail`
- [ ] Frontend stack documented: AssetMapper, Tailwind CSS (symfonycasts/tailwind-bundle), Turbo (symfony/ux-turbo), Stimulus (symfony/stimulus-bundle)
- [ ] `assets/` directory structure added to "Project Structure" section
- [ ] New Composer scripts for Tailwind compilation documented (if any)
- [ ] `playwright-cli` skill usage documented for frontend verification
- [ ] Any new console commands documented

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Edit `CLAUDE.md` at project root
- Preserve existing documentation that hasn't changed
- Follow the existing documentation style and structure

## Input Dependencies
- All other tasks (1–6): Need final route names, directory structure, and command references

## Output Artifacts
- Updated `CLAUDE.md`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Sections to Update

**Project Structure** — Add assets directory:
```
src/
├── ...existing...
assets/
├── app.js              # AssetMapper entry point
├── controllers/        # Stimulus controllers
│   ├── calendar-selection_controller.js
│   ├── sortable-table_controller.js
│   └── comparison-selector_controller.js
└── styles/
    └── app.css         # Tailwind directives
```

**Tech Stack** — Add frontend stack:
- Tailwind CSS via `symfonycasts/tailwind-bundle` (standalone binary, no Node.js)
- Hotwire: Turbo Drive + Turbo Frames (`symfony/ux-turbo`)
- Stimulus (`symfony/stimulus-bundle`) for client-side interactivity
- Symfony AssetMapper with importmap

**Web Routes** — Replace with:
- `/activities` — Calendar view (monthly grid with activity icons)
- `/activities/{id}/detail` — Activity detail Turbo Frame partial
- `/activities/pattern` — Pattern list (alphabetical groups with recent activities)
- `/activities/pattern/{signature}` — Pattern detail with paginated sortable table
- `/activities/compare` — Comparison view with Chart.js (unchanged)

**Local Development** — Add:
```bash
ddev exec php bin/console tailwind:build    # Compile Tailwind CSS
ddev exec php bin/console tailwind:build --watch  # Watch mode for development
```

**New section: Frontend Verification** — Document `playwright-cli` skill usage:
- Use the `playwright-cli` skill for browser verification of frontend changes
- No npm setup required — the skill handles browser management internally
- Can navigate pages, take screenshots, interact with elements, and extract data

### Reading the Current File
Read `CLAUDE.md` first to understand current structure and only modify the relevant sections. Do not rewrite sections that haven't changed.

</details>
