# Smart sektions-synlighed

## Problem
Gæster ser tomme sider (ønskeliste, program osv.) fordi arrangøren ikke vidste de var aktive. Gæstesiden viser altid alle nav-punkter uanset om der er indhold.

## Løsning

### Del 1: Auto-skjul tomme sektioner på gæstesiden

Gæstesidens navigation fjerner sektioner uden indhold. Queries køres i `e/index.php` og filtrerer `$navItems`.

| Sektion | Skjul hvis | Query |
|---------|-----------|-------|
| Program (schedule) | 0 schedule_items | `SELECT COUNT(*) FROM schedule_items WHERE event_id = ?` |
| Ønskeliste (wishlist) | 0 wishlist_items | `SELECT COUNT(*) FROM wishlist_items WHERE event_id = ?` |
| Galleri (photos) | 0 photos | `SELECT COUNT(*) FROM photos WHERE event_id = ?` |
| Minder (memories) | 0 memories | `SELECT COUNT(*) FROM memories WHERE event_id = ?` |
| Indslag (indslag) | 0 toastmaster_items | `SELECT COUNT(*) FROM toastmaster_items WHERE event_id = ?` |

Vises altid: Oversigt (home), Tilmelding (rsvp).

### Del 2: Publicerings-tjekliste i invitation-editoren

I publish-panelet (`app/events/pages/invitation.php`) vises en oversigt over sektioner med status:
- Grøn: "Program — 3 punkter"
- Orange/advarsel: "Ønskeliste — tom (skjules for gæster)"

Ingen ekstra toggles — informativt, ikke styrende.

## Filer der ændres
- `e/index.php` — count-queries + filtrering af navItems
- `app/events/pages/invitation.php` — publish-panel tjekliste

## Gælder for
Begge event-typer (åben og lukket).
