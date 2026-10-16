# Changelog

Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [0.9.2] - 2026-10-16

Redesign-Linie: übernimmt aus main zwei Fixes der Anmeldeseite (unter 0.9.0,
Fixed). Das Kopfelement `<data key="oauth2" …>` bekommt einen leeren Text,
damit auch Kerne ohne `OC_Template::renderHeaders()` – darunter der
Redesign-Kern bis zur Übernahme – es mit Endtag ausgeben und die
Anmeldekarte mittig bleibt. Eine verfälschte Rücksprungadresse
(`redirect_url[]=…`, `client_id[]=…` oder `user[]=…`) führt nicht mehr zu
HTTP 500. Ohne neue Migration genügt der Sprung in der dritten Stelle.

## [0.9.1] - 2026-10-14

Redesign-Linie: enthält main bis 0.9.0 (Loopback-Weiterleitung nach RFC 8252,
dieselbe `redirect_uri` am Token-Endpunkt, Migration `Version20261014120000`)
und die Redesign-Fixes bis 0.8.1. Die Nummer liegt über 0.9.0; der Sprung von
0.8.1 in der zweiten Stelle lässt den Kern die neue Migration ausführen.

## [0.9.0] - 2026-10-14

Server-Seite für den Desktop-Client, der nach RFC 8252 auf
`http://127.0.0.1:<Port>` statt `http://localhost:<Port>` zurückleiten soll
(Client-Audit M5). Diese Version muss auf dem Server sein, bevor ein Client
mit der Umstellung ausgeliefert wird – sonst endet dessen Anmeldung auf der
Fehlerseite. Die zweite Stelle steigt wegen der neuen Migration: Bei einem
Sprung nur in der dritten Stelle führt der Kern sie beim Update nicht aus
(`OC_App::shouldUpgrade`, siehe 0.8.0).

### Added

- **Loopback-Weiterleitung mit beliebigem Port (RFC 8252 §7.3).** Clients mit
  Loopback-Registrierung – `http://localhost:*` wie der eingebaute
  Desktop-Client, `http://127.0.0.1` oder `http://[::1]` – dürfen auf
  `http://127.0.0.1:<Port>` und `http://[::1]:<Port>` zurückleiten; Pfad und
  Query müssen wie registriert sein. Die vorhandene Registrierung des
  Desktop-Clients bleibt, wie sie ist. `localhost` selbst verhält sich wie
  bisher, ein fester localhost-Port bleibt fest, und für alle anderen Hosts
  ändert sich nichts.
- `http://127.0.0.1:*` und `http://[::1]:*` lassen sich wie
  `http://localhost:*` registrieren.

### Security

- **Der Token-Endpunkt verlangt dieselbe `redirect_uri` wie die
  Autorisierung (RFC 6749 §4.1.3).** Bisher genügte eine URI, die zur
  Registrierung passte – bei `http://localhost:*` also jeder Port, mit
  Subdomain-Erlaubnis jede Subdomain. Der Code merkt sich jetzt die URI
  seiner Autorisierung (neue Spalte `redirect_uri` in `oauth2_auth_codes`,
  Migration `Version20261014120000`); eine andere lehnt der Token-Endpunkt mit
  `invalid_grant` / `auth grant redirect uri mismatch` ab. Gleichwertige
  Schreibweisen wie `http://127.0.0.1:49152` und `http://127.0.0.1:49152/`
  gelten als dieselbe. Codes von vor dem Update tragen keine URI und werden
  bis zu ihrem Ablauf (zehn Minuten) nur gegen die Registrierung geprüft.
- **Benutzer-Info in der `redirect_uri` wird abgelehnt** (`http://user@…`),
  außer sie ist genau so registriert.
- **Loopback-Clients mit `[::1]` lassen sich nur noch mit `force-trust` als
  vertrauenswürdig eintragen**, wie bisher `localhost` und `127.0.0.1`. Mit
  beliebigem Port bekämen sie den Code ohne Zustimmung an jeden Port des
  Rechners.

### Changed

- Der Platzhalter-Port greift nur noch, wenn auf `:*` das Ende, `/`, `?`
  oder `#` folgt. `http://localhost:*.example.com` las die App bisher als
  Host `localhost.example.com` mit beliebigem Port.

### Fixed

- **Die Anmeldekarte bleibt mittig, wenn ein Client die Anmeldung öffnet.**
  Für `js/login.js` legt die App auf der Anmeldeseite das Kopfelement
  `<data key="oauth2" …>` ab, bisher ohne Text. Kerne ohne
  `OC_Template::renderHeaders()` – Core- und SaaS-Tags bis v11.0.22,
  DEV-Tags bis v11.0.37 und der Redesign-Kern bis zur Übernahme – geben es
  dann als `<data …/>` aus. Selbstschließend sind in HTML aber nur leere
  Elemente wie `<meta>`; `<data>` blieb offen, umschloss die Anmeldekarte, und
  die stand links oben statt mittig – etwa sobald sich der Desktop-Client
  anmeldete. Die App übergibt jetzt einen leeren Text, jeder Kern gibt
  `<data …></data>` aus; auf Kernen mit `renderHeaders()` ist die Ausgabe
  unverändert.
- **Die Anmeldeseite bricht bei verfälschter Rücksprungadresse nicht mehr mit
  HTTP 500 ab.** Mit `redirect_url[]=…` oder mit `client_id[]=…` bzw.
  `user[]=…` in der `redirect_url` endete der Aufruf in einem Serverfehler
  (TypeError in `urldecode()`, InvalidArgumentException aus
  `ClientMapper::findByIdentifier()`), noch bevor sich jemand anmelden konnte.
  Solche Werte übergeht die App jetzt; die Anmeldeseite erscheint wie ohne
  sie.

## [0.8.1] - 2026-10-07

Redesign-Linie: enthält main bis 0.8.0 (Migrationen für Datenbanken aus oauth2 0.1.0) und die Redesign-Fixes 0.7.3/0.7.4. Die Nummer liegt über 0.8.0, damit ein Update von 0.7.x die Migrationen ausführt.

### Fixed

- Anrede: Die Autorisierungsseite mischte in de Du und Sie („Du bist eingeloggt als …“, aber „Zugriff auf Ihren Benutzernamen“), die Rückfrage beim Widerrufen siezte. de und de_CH duzen jetzt, de_DE siezt.
- Sprache: „Delete“, „Revoke authorization“ (Tooltip und Sprachausgabe), „Yes“/„No“ (Sprachausgabe der Client-Liste) und „Client id is unknown“ fehlten in allen deutschen Katalogen.
- „Deleting...“ heißt „Wird gelöscht …“ (de_DE duzte mit „Lösche...“), „Secret“ einheitlich „Geheimer Schlüssel“.
- de_CH: zehn genutzte Texte aus de ergänzt (u. a. Weiterleitungsseite nach der Autorisierung).

## [0.8.0] - 2026-09-29

Inhaltlich gleich 0.7.3, geändert ist nur die Versionsnummer. `redesign`
steht bei 0.7.4 und braucht beim Übertrag eine Nummer über 0.8.0.

### Changed

- **Versionsnummer auf 0.8.0 angehoben, damit die Migration aus 0.7.3 beim
  Update läuft.** Der Kern aktualisiert eine App nach Ordnertausch und
  `occ upgrade` nur, wenn sich mindestens die zweite Stelle ihrer
  Versionsnummer ändert (`OC_App::shouldUpgrade`). Ändert sich nur die dritte
  Stelle (0.7.2 → 0.7.3), trägt er die neue Nummer ein und führt weder
  Migrationen noch Repair-Schritte aus. `Version20260926120000` blieb so
  offen, und der verwaiste Hintergrundjob aus oauth2 0.1.0 blieb stehen, bis
  jemand `occ migrations:migrate oauth2` von Hand aufrief. Mit 0.8.0 läuft
  die Migration beim Update von 0.7.2 wie von 0.7.3 aus. Am Code ändert sich
  nichts. Anders als bei einem Sprung in der dritten Stelle zeigt die Instanz
  zwischen Ordnertausch und `occ upgrade` statt der Anmeldung die
  Update-Seite.

## [0.7.3] - 2026-09-26

Nicht zu verwechseln mit 0.7.3 auf `redesign` (dort ein CSP-Fix). Beim
Übertrag nach `redesign` bekommt dieser Stand dort eine eigene, höhere Nummer.

### Fixed

- **Eine aus oauth2 0.1.0 übernommene Datenbank brach das Update ab.** 0.1.0
  (ownCloud 10.0, 2017) legte die Tabelle für Autorisierungscodes noch als
  `oauth2_authorization_codes` an; 0.2.0 hat sie in `oauth2_auth_codes`
  umbenannt, aber `Version20161122085340` stieg bei vorhandener
  `oauth2_clients` einfach aus. Die Tabelle entstand so nie, und
  `Version20201126140622` scheiterte an ihr mit `TableDoesNotExist` – damit
  das ganze App-Update. Die fehlende Tabelle wird jetzt mit der Definition aus
  der `database.xml` angelegt und protokolliert; die alte Tabelle bleibt
  unberührt (Autorisierungscodes gelten zehn Minuten, zu übernehmen gibt es
  dort nichts). Clients, Zugriffs- und Refresh-Tokens bleiben, wie sie sind.
  Das gilt auch für eine 0.1.0-Datenbank, die unter ownCloud 10 schon auf
  0.2.x bis 0.4.x gehoben wurde: Dort ist `Version20161122085340` bereits
  (ohne Wirkung) verbucht, deshalb legt `Version20201126140622` die Tabelle
  bei Bedarf selbst an.
- **Standard-Clients doppelten einen von Hand angelegten Eintrag.**
  `Version20170329194544` verließ sich auf den eindeutigen Namensindex, den es
  in 0.1.0 noch nicht gab; auf der Kennung gab es nie einen. Wer den Desktop-
  oder Mobil-Client damals selbst eingetragen hatte, bekam einen zweiten
  Datensatz mit derselben Kennung, und `findByIdentifier()` scheiterte danach
  an `MultipleObjectsReturnedException`. Die Migration überspringt jetzt jeden
  Standard-Client, dessen Kennung oder Name schon vorhanden ist, und lässt den
  vorhandenen Eintrag unverändert. Ist nur der Name belegt, fehlt danach die
  offizielle Kennung und die jeweilige App kann sich nicht per OAuth2
  anmelden; das meldet die Migration jetzt als Warnung (Ausgabe und
  Protokoll) samt den `occ`-Befehlen, mit denen sich der Client nachtragen
  lässt.
- **Ein Hintergrundjob aus oauth2 0.1.0 blieb für immer liegen.** 0.1.0 trug
  seinen Aufräumjob als `OC\BackgroundJob\Legacy\RegularJob` mit dem Argument
  `["OCA\\OAuth2\\BackgroundJob\\CleanUp","run"]` ein. Die Kern-Klasse gibt es
  nicht mehr; der Eintrag wurde bei jedem Versuch übersprungen und
  protokolliert. Die neue Migration `Version20260926120000` entfernt genau
  diesen Eintrag; aufgeräumt wird weiter über den Job
  `OCA\OAuth2\BackgroundJob\CleanUp`.

## [0.7.4] - 2026-09-23

### Fixed

- Vertrauenswürdige Clients: Hatte der Nutzer noch keine Sitzung, lief die
  Kette Anmelde- bzw. 2FA-Formular -> 303 /authorize -> 303 redirect_uri.
  Chromium prüft die CSP-Direktive form-action der Formularseite ('self') auch
  gegen die letzte Weiterleitung und brach sie ab; der Code kam beim Client
  (http://localhost:<port>, oc://…) nie an. /authorize antwortet in diesem Fall
  jetzt mit einer eigenen Weiterleitungsseite (Skript plus sichtbarer Link).
  Direkt geöffnete Adressen (Sec-Fetch-Site: none) werden weiter per HTTP
  weitergeleitet.

## [0.7.3] - 2026-09-22

### Fixed

- Freigabeseite: Herkunft der redirect_uri wird in der CSP-Direktive form-action freigegeben. Chromium brach die Weiterleitung zum Desktop-Client (http://localhost:…) und zu den Apps (oc://…) sonst ab, die Anmeldung blieb auf der Freigabeseite stehen.

## [0.7.2] - 2026-09-16

### Security

- Der Client-Schlüssel (client secret) wird jetzt unabhängig von PKCE verlangt.
  Bisher übersprang der Token-Endpunkt die Prüfung des Schlüssels, sobald ein
  `client_id` und ein `code_verifier` mitgeschickt wurden – auch beim
  `refresh_token`-Grant. Wer ein gestohlenes Refresh-Token besaß, konnte damit
  den Schlüssel eines vertraulichen Clients umgehen. Ob der Schlüssel Pflicht
  ist, entscheidet jetzt der Client-Datensatz (vertraulich vs. öffentlich);
  öffentliche Clients müssen beim `authorization_code`-Grant PKCE nutzen.
  Dieser Fix lief seit dem 03.07.2026 nur im SaaS-Bündel und fehlte hier und
  im Marktplatz-Paket.

## [0.7.1] - 2026-08-13

### Changed

- Produktname, Beschreibung und uebersetzte Zeichenketten nennen owncloud.online;
  Verweise auf Fehlerbereich, Repository und Dokumentation zeigen auf das eigene
  Repository. Screenshots aus fremden Repositories entfernt.

## [Unreleased]

### Changed

- Forked to BWTECH-github/owncloud.online and rebranded for owncloud.online
- Bumped minimum PHP version to 8.4
- Modernized codebase to PHP 8.4 idioms (constructor property promotion, readonly properties, typed properties, `match`, `#[\Override]`, FQCN imports)
- Replaced reusable workflows from `owncloud/reusable-workflows` with self-contained CI that clones owncloud.online as core
- Removed Transifex translation sync workflow (secrets unavailable in fork)


## [0.6.1] - 2023-08-31

### FIXED

- [#359](https://github.com/owncloud/oauth2/pull/359) - fix: harden subdomain validation


## [0.6.0] - 2023-07-27

### Changed

- [#353](https://github.com/owncloud/oauth2/pull/353) - Always return an int from Symfony Command execute method
- Minimum core version 10.11, minimum php version 7.4
- Download URL string changes
- Bump libraries


## [0.5.3] - 2022-06-02

### Fixed

- [#327](https://github.com/owncloud/oauth2/pull/327) - Use userid on api calls, show username in login form 
- [#331](https://github.com/owncloud/oauth2/pull/331) - Add missing token index
- [#337](https://github.com/owncloud/oauth2/pull/337) - Don't require client secret when using PKCE

### Changed 

- [#328](https://github.com/owncloud/oauth2/pull/328) - Fill login_hint with username


## [0.5.2] - 2021-11-02

- [#313](https://github.com/owncloud/oauth2/issues/313) - Add occ oauth2:list command
- [#315](https://github.com/owncloud/oauth2/issues/315) - Sanitize output of command oauth2:list-clients
- [#314](https://github.com/owncloud/oauth2/issues/314) - Add occ oauth2:modify-client command


## [0.5.1] - 2021-10-05

### Fixed

- Fix user lookup in authmodule [#307](https://github.com/owncloud/oauth2/issues/307)


## [0.5.0] - 2020-08-16

### Added

- Add support for trusted clients [#298](https://github.com/owncloud/oauth2/issues/298)
- Support setups with user login name != internal UID [#286](https://github.com/owncloud/oauth2/issues/286)
- Implement the oauth2 pkce extension [#276](https://github.com/owncloud/oauth2/issues/276)

### Changed

- Return proper error description for auth-core/refresh-token errors [#279](https://github.com/owncloud/oauth2/issues/279)
- Adjust PHP dependencies for composer 2.0 [#273](https://github.com/owncloud/oauth2/issues/273)

## [0.4.4] - 2020-03-03

### Fixed

- No exception in case the token is unknown and the openid connect app is enabled - [#255](https://github.com/owncloud/oauth2/issues/255)

## [0.4.3] - 2019-12-20

### Changed

- Improve adding a new client - [#237](https://github.com/owncloud/oauth2/issues/237)
- Drop PHP Support 7.0 - [#238](https://github.com/owncloud/oauth2/issues/238)

## [0.4.2] - 2019-11-21

### Changed

- Handle refresh token of a disabled user - [#225](https://github.com/owncloud/oauth2/issues/225)
- Use a WHATWG URL spec compliant URL parser - see https://url.spec.wha… - [#229](https://github.com/owncloud/oauth2/issues/229)
- Remove back button from error page, add logging and return proper err… - [#220](https://github.com/owncloud/oauth2/issues/220)
- Check user status - [#209](https://github.com/owncloud/oauth2/issues/209)

## [0.4.1] - 2019-05-15

### Fixed

- The redirect_uri needs to be encoded in the logout url - [#197](https://github.com/owncloud/oauth2/issues/197)

## [0.4.0] - 2019-02-13

### Added

- Display currently logged in user display name in authorization page - [#188](https://github.com/owncloud/oauth2/issues/188)
- Added option for switching user in authorization page - [#188](https://github.com/owncloud/oauth2/issues/188)

## [0.3.0] - 2018-12-07

### Added

- Added autofocus to buttons - [#173](https://github.com/owncloud/oauth2/issues/173)
- Add occ oauth2:remove-client to remove a client by its id - [#178](https://github.com/owncloud/oauth2/issues/178)
- Support for PHP 7.1 and 7.2 - [#161](https://github.com/owncloud/oauth2/issues/161)
- Support for implicit grant - [#166](https://github.com/owncloud/oauth2/issues/166)

### Changed

- Set max version to 10 because core platform is switching to Semver - [#180](https://github.com/owncloud/oauth2/issues/180)

### Fixed

- Don't fail if the client was already added - [#176](https://github.com/owncloud/oauth2/issues/176)
- Use markdown properly in description - [#153](https://github.com/owncloud/oauth2/issues/153)

## [0.2.3] - 2018-08-09

### Fixed

- Erroneous ownCloud 2.4.2 client behavior causing service interruptions [#145](https://github.com/owncloud/oauth2/pull/145)
- Initialization for password-less sessions [#129](https://github.com/owncloud/oauth2/pull/129)

## [0.2.2]

### Added

- OpenID Connect UserInfo endpoint [#115](https://github.com/owncloud/oauth2/pull/115)

### Fixed

- Expired token causing server failures  [#118](https://github.com/owncloud/oauth2/pull/118)

## [0.2.1] - 2017-11-28

### Fixed

- OAuth app blocking public uploads [#100](https://github.com/owncloud/oauth2/pull/100)

## [0.2.0] - 2017-10-13

### Added

- Oracle and 4-byte MySQL support - [#42](https://github.com/owncloud/oauth2/pull/42)
- Predefined client ids for mobile and desktop clients - [#38](https://github.com/owncloud/oauth2/pull/38)

### Changed

- Allow multiple tokens per client - [#65](https://github.com/owncloud/oauth2/pull/65)

### Fixed

- Security Hardening - [#71](https://github.com/owncloud/oauth2/pull/71)
- Verify Bearer token even if the session is still valid - [#53](https://github.com/owncloud/oauth2/pull/53)
- Use displayname on switch user screen - [#90](https://github.com/owncloud/oauth2/pull/90)

[Unreleased]: https://github.com/owncloud/oauth2/compare/v0.6.1...master
[0.6.1]: https://github.com/owncloud/oauth2/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/owncloud/oauth2/compare/v0.5.3...v0.6.0
[0.5.3]: https://github.com/owncloud/oauth2/compare/v0.5.2...v0.5.3
[0.5.2]: https://github.com/owncloud/oauth2/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/owncloud/oauth2/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/owncloud/oauth2/compare/v0.4.4...v0.5.0
[0.4.4]: https://github.com/owncloud/oauth2/compare/v0.4.3...v0.4.4
[0.4.3]: https://github.com/owncloud/oauth2/compare/v0.4.2...v0.4.3
[0.4.2]: https://github.com/owncloud/oauth2/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/owncloud/oauth2/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/owncloud/oauth2/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/owncloud/oauth2/compare/v0.2.3...v0.3.0
[0.2.3]: https://github.com/owncloud/oauth2/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/owncloud/oauth2/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/owncloud/oauth2/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/owncloud/oauth2/compare/v0.1.0...v0.2.0
