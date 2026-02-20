---
id: 1
group: "frontend-infrastructure"
dependencies: []
status: "pending"
created: "2026-02-19"
skills: ["symfony", "tailwind-css"]
complexity_score: 5
complexity_notes: "Multiple packages to install and configure, base template rebuild, Tailwind theme definition. High integration surface but well-documented Symfony recipes."
---
# Set Up Frontend Asset Pipeline and Base Template

## Objective
Install and configure Symfony AssetMapper, Tailwind CSS (via symfonycasts/tailwind-bundle), Turbo (symfony/ux-turbo), and Stimulus (symfony/stimulus-bundle). Rebuild `base.html.twig` with AssetMapper entry points, Tailwind stylesheet, Turbo meta tags, and a responsive Strava-inspired navigation bar. Migrate Chart.js from CDN script tag to importmap.

## Skills Required
- `symfony`: Composer package installation, AssetMapper configuration, bundle setup, importmap management
- `tailwind-css`: Theme configuration with custom color palette, responsive breakpoints, utility-class design

## Acceptance Criteria
- [ ] `symfony/asset-mapper`, `symfonycasts/tailwind-bundle`, `symfony/ux-turbo`, and `symfony/stimulus-bundle` are installed via Composer
- [ ] AssetMapper is configured to serve from `assets/` directory with importmap support
- [ ] `tailwind.config.js` exists with Strava-inspired theme (primary orange `#FC4C02`, dark backgrounds, neutral grays, typography scale) and references Twig template paths for class purging
- [ ] Stimulus controllers directory `assets/controllers/` exists and is auto-discovered
- [ ] Chart.js is added to the importmap (replacing any CDN script tag)
- [ ] `base.html.twig` includes AssetMapper entry point, Tailwind compiled stylesheet, and Turbo meta tags
- [ ] `base.html.twig` contains a responsive top navigation bar with links to Calendar (`/activities`) and Patterns (`/activities/pattern`), with active-page highlighting using Strava accent color
- [ ] Navigation collapses to a hamburger menu on mobile (below `md` breakpoint)
- [ ] `ddev exec php bin/console asset-map:compile` runs successfully
- [ ] Tailwind CSS compiles without errors (`ddev exec php bin/console tailwind:build`)
- [ ] PHPStan level 8 and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Symfony AssetMapper with importmap-based JavaScript modules
- `symfonycasts/tailwind-bundle` manages standalone Tailwind CLI binary (no npm for build)
- Turbo Drive enabled globally for SPA-like navigation
- Stimulus auto-discovery from `assets/controllers/`
- Twig block structure preserved: `title`, `stylesheets`, `body`, `javascripts` (or equivalent existing blocks)

## Input Dependencies
None — this is the foundational task.

## Output Artifacts
- Configured `composer.json` with new dependencies
- `assets/` directory with `app.js` entry point, `styles/app.css` with Tailwind directives
- `assets/controllers/` directory (empty, ready for Stimulus controllers)
- `tailwind.config.js` with Strava theme
- `importmap.php` with Chart.js mapping
- Rebuilt `base.html.twig` with navigation bar and asset pipeline integration

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Install Composer packages
```bash
ddev composer require symfony/asset-mapper symfonycasts/tailwind-bundle symfony/ux-turbo symfony/stimulus-bundle
```
Each package has a Symfony Flex recipe that scaffolds configuration. Run the recipes and accept defaults.

### Step 2: Configure AssetMapper
After installation, verify `config/packages/asset_mapper.yaml` exists and maps `assets/` directory. The entry point should be `assets/app.js`. Verify `importmap.php` exists.

### Step 3: Set up Tailwind
Run `ddev exec php bin/console tailwind:init` to generate `tailwind.config.js` and the initial CSS file. Edit `tailwind.config.js` to:
- Set `content` paths to include `templates/**/*.html.twig`
- Add custom theme extending `colors` with Strava palette:
  - `strava: { orange: '#FC4C02', 'orange-dark': '#E34402' }`
  - Neutral grays, white backgrounds, dark text
- Add typography scale if needed

Create `assets/styles/app.css` with Tailwind directives:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### Step 4: Configure Stimulus
Verify `assets/controllers/` directory exists and `assets/controllers.json` is created by the recipe. The Stimulus bundle auto-discovers controllers from this directory.

### Step 5: Add Chart.js to importmap
```bash
ddev exec php bin/console importmap:require chart.js
```
Remove any CDN `<script>` tags for Chart.js from existing templates.

### Step 6: Rebuild base.html.twig
Read the current `templates/base.html.twig` to understand existing block structure. Rebuild it to include:
- `{% block stylesheets %}` with `{{ ux_controller_link_tags() }}` if needed
- `{{ importmap('app') }}` in the head or before closing body
- Turbo meta tag: the `symfony/ux-turbo` package auto-adds Turbo when imported in `app.js`
- In `app.js`, add: `import '@symfony/ux-turbo';` and `import './styles/app.css';`

Build the navigation bar using Tailwind classes:
- Top bar with app name "Strava Analyser" on the left
- Links: "Calendar" (href="/activities"), "Patterns" (href="/activities/pattern")
- Active link highlighted with `text-strava-orange` or `border-b-2 border-strava-orange`
- Use `{{ app.request.pathInfo starts with '/activities/pattern' ? 'active-class' : '' }}` for active detection
- Mobile: hide links behind a hamburger button, show/hide via a simple Stimulus controller or Tailwind `peer` trick

### Step 7: Verify
- Run `ddev exec php bin/console tailwind:build` to compile Tailwind
- Run `ddev exec php bin/console asset-map:compile` to verify asset mapping
- Run `ddev composer phpstan` and `ddev composer php-cs-fixer` to ensure code quality
- Load the site in browser to verify navigation renders

### Color Palette Reference
Define these in `tailwind.config.js` theme extend:
```js
colors: {
  strava: {
    orange: '#FC4C02',
    'orange-dark': '#E34402',
    'orange-light': '#FF6B35',
  },
  // Pattern type colors (used in calendar icons)
  pattern: {
    steady: '#3B82F6',     // blue-500
    intervals: '#F97316',  // orange-500
    tempo: '#EF4444',      // red-500
    'long-run': '#22C55E', // green-500
    unclassified: '#6B7280', // gray-500
  }
}
```

</details>
