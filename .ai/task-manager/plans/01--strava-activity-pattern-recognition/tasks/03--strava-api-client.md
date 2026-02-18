---
id: 3
group: "strava-integration"
dependencies: [1]
status: "completed"
created: 2026-02-18
skills:
  - php
  - api-endpoints
---
# Strava API Client with Token Refresh

## Objective
Implement a Symfony service that wraps the Strava v3 REST API, automatically refreshes the OAuth access token when it is expired, and exposes the three API calls needed by the sync command: list activities, get activity detail (with laps), and get activity streams.

## Skills Required
- PHP service layer / Symfony HttpClient
- OAuth token lifecycle management

## Acceptance Criteria
- [ ] `StravaClient` service is registered in the Symfony container and injectable
- [ ] On instantiation, the client reads token data from `var/strava-token.json` (falls back to `.env.local` values for the first run)
- [ ] `refreshTokenIfNeeded()` is called before every API request; if the access token has expired it fetches a new one from Strava's token endpoint and writes the updated values back to `var/strava-token.json`
- [ ] `getActivities(int $page, int $perPage, ?int $after): array` returns decoded JSON for the `/v3/athlete/activities` endpoint filtered to `type=Run`
- [ ] `getActivity(int $stravaId): array` returns decoded JSON for `/v3/activities/{id}` (includes laps)
- [ ] `getActivityStreams(int $stravaId): array` returns decoded JSON for `/v3/activities/{id}/streams?keys=velocity_smooth,heartrate&key_by_type=true`
- [ ] HTTP errors (non-2xx responses) throw a descriptive `\RuntimeException`
- [ ] A `RateLimitTracker` helper (or internal method) increments a per-window counter and exposes `sleepIfNeeded()` to pause execution when approaching 90 requests in a 15-minute window

## Technical Requirements
- `symfony/http-client` (already installed in Task 01)
- Strava API v3: `https://www.strava.com/api/v3/`
- Token endpoint: `https://www.strava.com/oauth/token` (POST with `grant_type=refresh_token`)
- Rate limit: 100 requests per 15-minute window, 1 000 per day (track per-window only)
- Token storage file: `var/strava-token.json` — JSON with keys `access_token`, `expires_at`, `refresh_token`

## Input Dependencies
- Task 01: Symfony project with `symfony/http-client` installed and `.env.local` containing `STRAVA_CLIENT_ID`, `STRAVA_CLIENT_SECRET`, `STRAVA_REFRESH_TOKEN`

## Output Artifacts
- `src/Strava/StravaClient.php` — injectable service
- `var/strava-token.json` — created at first token refresh (runtime artifact, git-ignored)

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Token file bootstrap**: On service construction, check if `var/strava-token.json` exists. If not, create it from the `.env.local` values (`STRAVA_ACCESS_TOKEN`, `STRAVA_TOKEN_EXPIRES_AT`, `STRAVA_REFRESH_TOKEN`). This allows the user to seed the file from the initial manual OAuth grant.

2. **Token refresh logic**:
   - Read `expires_at` (Unix timestamp) from the token file.
   - If `time() >= expires_at - 60` (60 second buffer), call `POST https://www.strava.com/oauth/token` with:
     ```
     client_id, client_secret, grant_type=refresh_token, refresh_token
     ```
   - Write the response fields `access_token`, `expires_at`, `refresh_token` back to `var/strava-token.json`.

3. **HTTP client usage**: Inject `HttpClientInterface` from Symfony. Set the `Authorization: Bearer {access_token}` header on all Strava requests.

4. **getActivities**: `GET /v3/athlete/activities?per_page={perPage}&page={page}&after={after}`. The `after` parameter is a Unix timestamp; pass `null` to omit it.

5. **getActivityStreams**: Request `velocity_smooth` (pace proxy) and `heartrate` streams. Use `key_by_type=true` so the response is keyed by stream type. The client returns the raw array; callers handle missing keys (e.g., no HR monitor).

6. **Rate limit tracker**: Keep a class-level array of request timestamps. On each request, append `microtime(true)`. Before making a request, count how many timestamps fall within the last 15 minutes. If count >= 90, sleep until the oldest timestamp in the window is >15 minutes old.

7. **Error handling**: Check `$response->getStatusCode()`. For non-2xx, throw `new \RuntimeException("Strava API error {$status}: {$body}")`.

8. **Symfony service wiring**: Declare the service in `config/services.yaml` (or use autowiring). Inject `HttpClientInterface`, `KernelInterface` (to resolve `%kernel.project_dir%`), and the four `env` parameters via constructor arguments bound in `services.yaml`.

</details>
