# MIRO Pulmonology — WordPress z Adwise Blueprint (lokalnie)

Czysty WordPress na motywie Adwise z Blueprinta (bez rebrandu — motyw zostaje „Adwise”, witryna nazywa się MIRO Pulmonology).
Makiety lo-fi strony: https://makiety-miro-pulmonology.adwisedev.cfolks.pl/ (przegląd DEV z 01.10.2026 — patrz pamięć sesji / raport).

## Dostęp
- Front: http://localhost:8108
- Panel: http://localhost:8108/wp-admin — `tremlein` / `PasswordAdWise!` (strona logowania = login KV AdWise z motywu)
- Mailpit (cała poczta z WP): http://localhost:8131

## Stack
- Docker: `MiroPulmonology_wp` (wordpress:php8.3-apache, :8108), `MiroPulmonology_db` (mariadb:11), `MiroPulmonology_mail` (Mailpit, :8131)
- WordPress pl_PL, `blog_public=0`, bezpośrednie odnośniki `/%postname%/`, strefa Europe/Warsaw
- Motyw: `wp-content/themes/adwise` — klon Blueprinta (remote gita `blueprint` = Adwise-development/Blueprint;
  `git pull blueprint main` pobiera zmiany blueprintu). Własnego repo projektu jeszcze nie ma.
- Instrukcje: `wp-content/themes/adwise/CLAUDE.md` + `docs/` (Blueprint + figma-workflow, page-workflow, login-page,
  SLA z `~/Allinel/docs`; dokumenty innych projektów w `docs/referencje/`). **Do pracy nad blokami otwieraj folder motywu.**
- Wtyczki: `novamira`, `novamira-pro` (dostęp MCP/CLI AdWise; opcje `novamira_ai_abilities_enabled=1`, `novamira_ai_abilities_domain=localhost`;
  hasło aplikacyjne `novamira-mcp-localhost` → serwer MCP `novamira-miro-localhost` w Claude Code).
- `mu-plugins/local-mail.php` — tylko lokalnie: poczta do Mailpita + nadawca `wordpress@localhost.test` (PHPMailer odrzuca `@localhost`).
- `docs/first-run.md` (wybór ścieżki designu, kickoff, `project.md`) — jeszcze NIE wykonany: czeka na projekt graficzny.

## Komendy
```bash
cd ~/MiroPulmonology
docker compose up -d                          # start
docker compose down                           # stop (dane w wolumenach zostają)
docker compose run --rm wpcli <komenda>       # WP-CLI, np. `plugin list`
cd wp-content/themes/adwise && npm run build  # po każdej zmianie bloku (WP czyta render.php z build/)
```
