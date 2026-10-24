# PlainShow Blog

`blog.plainshow.se`: posts, galleries, stories, wines and films for the Week of Bali friends, as a PlainShow extension. It continues [week-of-bali](https://github.com/huggan360/week-of-bali) (tagged `legacy-final`); old `app2.plainshow.se` links redirect here.

## How it fits in

- **Sign in** through login.plainshow.se (`app/auth/plainshow.php`, `PlainShow\Kit\PhpSessionAuth`). The blog keeps a profile row per person in the `woc` database, refreshed from their PlainShow account at every sign-in.
- **Look** from plainshow-kit, with a top navigation bar instead of a sidebar and the blog's own photographs (`public/assets/blog`).
- **API** at `/api/v1`, one entry point (`public/api/v1/index.php`) and one route list (`app/routes/api.php`) with a capability per route, prepared statements and CSRF on every change. Through `plainshow.se/api/blog/v1` the same routes take personal PlainShow tokens; core's gateway signs those calls (`PlainShow\Kit\Signed`). `/api/v1/openapi.json` describes the API from the routes and `app/routes/openapi-docs.php`.
- **Dashboard widgets** `latest-posts` and `new-photos` at `/api/v1/widgets/...`, signed by core.
- **Notices from Split** through `/api/v1/internal/notify`, signed with `config/notify.php`.

## Layout

| Path | What |
|---|---|
| `public/` | Pages, the API entry point and assets (the web root) |
| `app/src/Domain` | Posts, comments, albums, stories, wines, films, notices |
| `app/backend` | Picture checks, rotation and WebP re-encoding for posts and albums |
| `app/views` | Layout and partials |
| `config/` | Database, sign-in, widget and notify settings (not in git) |

Uploads (profile pictures, films, the sand game) still live in the old woc folder and are linked in.

## Release

```sh
bash ops/deploy.sh v2.3.1   # builds the tag into /srv/plainshow/blog/releases and switches
```

Or install a tag from Admin, Updates on plainshow.se.
