# Pyroshape showcase

A bilingual single-page site for `pyroshape.tuxweb.it`. The site uses static HTML, CSS, and browser JavaScript modules. English is the default and `?lang=it` selects Italian. The session viewer explains Core behavior without making API requests.

Preview from the repository root:

```sh
python3 -m http.server 8080 --bind 127.0.0.1 --directory site
```

Open <http://127.0.0.1:8080>. Check English and Italian, the request/refresh/logout controls, copying the code snippets, and the page at mobile widths.

The files in this directory are the static site. The eventual virtual host can serve this directory directly, with `index.html` as the document index. Geist and Geist Mono are self-hosted under the SIL Open Font License. See the license files in `fonts/`.

Publish `robots.txt`, `sitemap.xml`, `llms.txt`, and `index.md` beside `index.html`. The sitemap lists the canonical showcase URL. Keep the Markdown overview and its documentation links aligned with the Core README and app guides.
