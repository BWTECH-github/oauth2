# OAuth 2.0

OAuth 2.0 token-based authorization interface for [owncloud.online](https://github.com/BWTECH-github/owncloud.online), maintained by [BW-Tech GmbH](https://bw.tech). This is a PHP 8.4 fork of [owncloud/oauth2](https://github.com/owncloud/oauth2).

The app implements the [OAuth 2.0 Authorization Code Flow](https://tools.ietf.org/html/rfc6749#section-4.1) including the [PKCE extension](https://datatracker.ietf.org/doc/html/rfc7636), and exposes an [OpenID Connect UserInfo](https://openid.net/specs/openid-connect-core-1_0.html#UserInfo) endpoint so existing ownCloud clients (Desktop, Android, iOS) and third-party integrations can authenticate without ever holding the user's password.

## Features

- OAuth 2.0 authorization-code flow with optional PKCE (S256 / plain)
- Implicit (token) flow for legacy clients
- Refresh-token rotation, with one-week post-expiry retention before cleanup
- Trusted clients can skip the consent screen
- Subdomain wildcards on redirect URIs (opt-in per client)
- Per-user revocation from personal settings
- OCC commands for headless administration
- OpenID Connect `/userinfo` endpoint (sub, name, email, picture)

## Requirements

| Component | Version            |
| --------- | ------------------ |
| PHP       | **>= 8.4**         |
| ownCloud  | 11.x (owncloud.online) |
| Database  | MySQL, MariaDB, PostgreSQL, Oracle, or SQLite |

PHP extensions: `gmp`, `intl`, `mbstring` (transitively required by `rowbot/url`).

## Installation

```bash
cd /var/www/owncloud/apps
git clone https://github.com/BWTECH-github/owncloud.online.git oauth2
cd oauth2
composer install --no-dev
chown -R www-data:www-data .
sudo -u www-data php ../../occ app:enable oauth2
```

After enabling, the app's database tables are created automatically via migrations on the next ownCloud request or via `sudo -u www-data php occ upgrade`.

## Configuration

OAuth2 has no app-specific `config.php` keys — clients are managed entirely through the admin UI or OCC commands. The two settings below influence its behavior indirectly:

```php
// config/config.php
'token_auth_enforced' => true,    // require app passwords / tokens for sync clients
'overwrite.cli.url'   => 'https://cloud.example.com',  // canonical URL used in message_url
```

Endpoints (relative to the ownCloud base URL):

| Purpose             | URL                                  | Method    |
| ------------------- | ------------------------------------ | --------- |
| Authorization       | `/index.php/apps/oauth2/authorize`   | GET, POST |
| Token               | `/index.php/apps/oauth2/api/v1/token`| POST      |
| OIDC UserInfo       | `/index.php/apps/oauth2/api/v1/userinfo` | GET   |
| Authorize success   | `/index.php/apps/oauth2/authorization-successful` | GET |

## OCC Commands

Run from the ownCloud root with the web user, e.g. `sudo -u www-data php occ <command>`.

### `oauth2:add-client`

| Argument            | Required | Description |
| ------------------- | :------: | ----------- |
| `name`              | yes      | Display name shown on the consent screen |
| `client-id`         | yes      | Client identifier (>= 32 chars) |
| `client-secret`     | yes      | Client secret (>= 32 chars) |
| `redirect-url`      | yes      | Redirect URI; `http://localhost:*` allows any port |
| `allow-sub-domains` | no       | `true` / `false` (default `false`) |
| `trusted`           | no       | `true` / `false` — skip consent screen (default `false`) |
| `force-trust`       | no       | `true` / `false` — allow trusting `localhost` / `127.0.0.1` |

### `oauth2:list-clients`

Lists all registered clients with their secrets. Supports `--output=json` / `--output=plain`.

### `oauth2:modify-client`

| Argument | Required | Description |
| -------- | :------: | ----------- |
| `name`   | yes      | Existing client name (lookup key) |
| `key`    | yes      | One of: `name`, `client-id`, `client-secret`, `redirect-url`, `allow-sub-domains`, `trusted` |
| `value`  | yes      | New value (validated per key) |

### `oauth2:remove-client`

| Argument    | Required | Description |
| ----------- | :------: | ----------- |
| `client-id` | yes      | The client identifier to delete |

## Daily Usage

### Register a desktop client

```bash
sudo -u www-data php occ oauth2:add-client \
  "Desktop Client" \
  "$(openssl rand -hex 32)" \
  "$(openssl rand -hex 32)" \
  "http://localhost:*"
```

### Authorization-code flow (with PKCE)

```
GET /index.php/apps/oauth2/authorize
  ?response_type=code
  &client_id=<id>
  &redirect_uri=<uri>
  &state=<random>
  &code_challenge=<base64url(sha256(verifier))>
  &code_challenge_method=S256
```

Exchange the returned `code` for tokens:

```
POST /index.php/apps/oauth2/api/v1/token
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code=<code>
&redirect_uri=<uri>
&code_verifier=<verifier>
```

Public clients (no secret) authenticate the token request via PKCE:

```
grant_type=authorization_code&code=<code>&redirect_uri=<uri>
&client_id=<id>&code_verifier=<verifier>
```

### Refresh

```
POST /index.php/apps/oauth2/api/v1/token
grant_type=refresh_token&refresh_token=<refresh_token>
```

Access tokens expire after one hour (`AccessToken::EXPIRATION_TIME = 3600`); authorization codes after 10 minutes (`AuthorizationCode::EXPIRATION_TIME = 600`).

## Troubleshooting

| Symptom                                                              | Likely cause / Fix |
| -------------------------------------------------------------------- | ------------------ |
| `invalid_client` on token endpoint                                   | Wrong client id/secret, or `PHP_AUTH_USER` not forwarded by your reverse proxy. Ensure Apache `SetEnvIf Authorization` / nginx `proxy_set_header Authorization` is in place. |
| `invalid_grant: auth grant redirect uri invalid`                     | The `redirect_uri` in the token request does not match the registered one — protocol, port, host, path, and query must match. |
| `invalid_grant: code verifier invalid`                               | PKCE verifier doesn't match the challenge. Re-check `S256` encoding (`base64url(sha256(verifier))`, no padding). |
| Desktop client 2.4.2 stuck in refresh loop                           | Known interop bug; the app already returns HTTP 200 on refresh failure for `mirall/2.4.2` to break the loop. Update the client. |
| Bearer token rejected on WebDAV after IDP migration                  | `OAuth2Test`-shaped session leak; clear the user's session cookies and retry. |
| `Cannot set localhost as trusted`                                    | Use `--trusted=true --force-trust=true` if you really need a trusted localhost client (testing only). |
| File encryption with OAuth2-authenticated sessions fails             | Only **master-key** encryption is supported — user-key encryption needs the password, which OAuth2 never sees. |
| Tokens not cleaned up                                                | `oauth2:cleanup` runs daily as a `TimedJob`. Check `oc_jobs`; if cron is broken, expired tokens accumulate (still rejected, just not deleted). |

## Attribution

This is a fork of [owncloud/oauth2](https://github.com/owncloud/oauth2), originally developed by Project Seminar "sciebo@Learnweb" of the University of Münster and ownCloud GmbH. Modifications for PHP 8.4 and owncloud.online by BW-Tech GmbH. Licensed under AGPL-3.0; see [COPYING](COPYING).
