# Changelog

## v2.8.0

- The blog follows the theme colour chosen in the dashboard (kit 1.13), with Bree Serif titles and dark grey text in the light theme.

## v2.7.0

- Every address comes from the platform file (kit 1.12), so the blog runs on any domain; Split's parts of the overview only show when Split is installed.
- Phone pushes through Firebase are removed, with their device tokens; notices in the blog stay.
- The blog's tables are in `database/migrations` and `php app/console.php migrate` sets them up on a new server (deploy runs it).
- Deploy uses this server's web server and web user.

## v2.6.2

- Page headings are a real frame with its inner panel; the main page's heading has the orange ring around the panel, as on widgets (kit 1.11.1).

## v2.6.1

- Page headings sit in a frame of their own; the posts page has the orange ring (kit 1.11).

## v2.6.0

- Svenska, English och Nederlands: webbläsarens språk som standard, eller valt i kontomenyn.
- The account menu is the same in every app: Account, the app's own items, Language, Theme, Sign out.

## 2.4.0

- Opening the blog while signed in to PlainShow signs you in here too, without a click: public pages ask the login service once, silently (kit 1.2).
- Frosted frames from kit 1.2.

## 2.3.1

- plainshow-kit 1.0. A README.

## 2.3.0

- Removed the old form handler `logic.php`, the old login page and the Split redirect pages. Their addresses still answer: /login goes to sign in, /split links go to split.plainshow.se, and /logic.php answers 410 Gone.

## 2.2.1

- The API description is built by the kit (0.7), shared with the other services.

## 2.2.0

- The blog API is reachable at plainshow.se/api/blog/v1 with a personal API token: calls signed by core's gateway act as the person, without a session or CSRF token.
- `/api/v1/openapi.json` describes the API, built from the router's own routes.

## 2.1.1

- Outfit for all text and the serif for titles, post text and post excerpts (kit 0.5).

## 1.1.0

- Continues week-of-bali as the PlainShow blog extension, at blog.plainshow.se. Old app2 links redirect.
- Sign-in goes through login.plainshow.se. The blog no longer stores passwords; each person's blog profile is created or refreshed from their PlainShow account when they sign in.
- Account administration moved to plainshow.se/admin/users.
- The split app shows PlainShow profile pictures.
- `vendor/` is installed by composer instead of being committed.
