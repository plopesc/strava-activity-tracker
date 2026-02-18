# Strava Activity Pattern Recognition

A Symfony 7 web application that syncs Strava activities and visualises activity patterns using Chart.js.

## Prerequisites

- [Git](https://git-scm.com/)
- [DDEV](https://ddev.readthedocs.io/en/stable/) (v1.22+)

## Setup

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd strava
   ```

2. **Start DDEV**

   ```bash
   ddev start
   ```

3. **Install PHP dependencies**

   ```bash
   ddev composer install
   ```

4. **Configure local environment**

   Copy the example environment file and fill in your Strava credentials:

   ```bash
   cp .env.local.example .env.local
   ```

   Edit `.env.local` and fill in the Strava credentials (see below for how to obtain them).

5. **Run database migrations**

   ```bash
   ddev exec php bin/console doctrine:migrations:migrate
   ```

6. **Open the app**

   ```bash
   ddev launch
   ```

## Obtaining Strava Credentials

1. Register a Strava API application at [https://www.strava.com/settings/api](https://www.strava.com/settings/api).
   - Set the **Authorization Callback Domain** to `strava.ddev.site` (or your local domain).
   - Note your **Client ID** and **Client Secret**.

2. Use the OAuth 2.0 authorization flow to obtain an initial refresh token:
   - Redirect the user to:
     ```
     https://www.strava.com/oauth/authorize?client_id=<CLIENT_ID>&response_type=code&redirect_uri=http://strava.ddev.site/strava/callback&approval_prompt=force&scope=activity:read_all
     ```
   - After authorizing, Strava will redirect to your callback URL with a `code` query parameter.
   - Exchange the code for tokens:
     ```bash
     curl -X POST https://www.strava.com/oauth/token \
       -d client_id=<CLIENT_ID> \
       -d client_secret=<CLIENT_SECRET> \
       -d code=<CODE> \
       -d grant_type=authorization_code
     ```
   - The response contains `access_token`, `refresh_token`, and `expires_at`. Copy these into `.env.local`.

3. Fill in `.env.local`:

   ```dotenv
   STRAVA_CLIENT_ID=your_client_id
   STRAVA_CLIENT_SECRET=your_client_secret
   STRAVA_REFRESH_TOKEN=your_refresh_token
   STRAVA_ACCESS_TOKEN=your_access_token
   STRAVA_TOKEN_EXPIRES_AT=1700000000
   ```

## Syncing Activities

Run the sync command to fetch activities from Strava:

```bash
ddev exec php bin/console strava:sync
```

## Accessing the App

```bash
ddev launch
```

Or navigate to [https://strava.ddev.site](https://strava.ddev.site) in your browser.

## Development

- **PHP console**: `ddev exec php bin/console <command>`
- **Composer**: `ddev composer <command>`
- **Database migrations**: `ddev exec php bin/console doctrine:migrations:migrate`
- **Migration status**: `ddev exec php bin/console doctrine:migrations:status`
